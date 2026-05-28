<?php

namespace App\Http\Controllers;

use App\Models\MailAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\MailClient\Services\EmailFetcher;

class MailAccountController extends Controller
{
    public function index(Request $request): View
    {
        $accounts = MailAccount::where('user_id', $request->user()->id)
            ->orderBy('created_at')
            ->get();
        return view('mail-accounts.index', compact('accounts'));
    }

    public function create(): View
    {
        return view('mail-accounts.form', ['account' => new MailAccount()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;
        $account = MailAccount::create($data);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status' => 'ok',
                'message' => 'メールアカウントを追加しました。',
                'id' => $account->id,
                'redirect' => route('mail-accounts.edit', $account),
            ]);
        }
        return redirect()->route('mail-accounts.index')->with('status', 'メールアカウントを追加しました。');
    }

    public function edit(Request $request, MailAccount $mailAccount): View
    {
        $this->authorizeOwnership($request, $mailAccount);
        return view('mail-accounts.form', ['account' => $mailAccount]);
    }

    public function update(Request $request, MailAccount $mailAccount)
    {
        $this->authorizeOwnership($request, $mailAccount);
        $data = $this->validated($request, $mailAccount);
        // 空のパスワードは更新しない (既存値を保持)
        foreach (['imap_password', 'pop_password', 'smtp_password'] as $pw) {
            if (empty($data[$pw])) {
                unset($data[$pw]);
            }
        }
        $mailAccount->update($data);

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'status' => 'ok',
                'message' => 'メールアカウントを更新しました。',
                'id' => $mailAccount->id,
            ]);
        }
        return redirect()->route('mail-accounts.index')->with('status', 'メールアカウントを更新しました。');
    }

    public function destroy(Request $request, MailAccount $mailAccount): RedirectResponse
    {
        $this->authorizeOwnership($request, $mailAccount);
        $mailAccount->delete();
        return redirect()->route('mail-accounts.index')->with('status', 'メールアカウントを削除しました。');
    }

    public function fetchNow(Request $request, MailAccount $mailAccount, EmailFetcher $fetcher): RedirectResponse
    {
        $this->authorizeOwnership($request, $mailAccount);
        try {
            $count = $fetcher->fetchForAccount($mailAccount);
            return redirect()->route('mail-accounts.index')->with('status', "{$count} 件のメールを取得しました。");
        } catch (\Throwable $e) {
            return redirect()->route('mail-accounts.index')->with('error', '取得失敗: ' . $e->getMessage());
        }
    }

    /**
     * 接続テスト: 入力されたフォーム値 (or 既存アカウント) で受信/送信の認証まで実際に試す。
     * UI からは「テスト」ボタンが叩く。JSON で受信・送信 別の結果を返す。
     */
    public function testConnection(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'mail_account_id' => ['nullable', 'integer', 'exists:mail_accounts,id'],
            'inbox_protocol' => ['required', 'in:imap,pop3,disabled'],
            'imap_host'      => ['nullable', 'string', 'max:255'],
            'imap_port'      => ['nullable', 'integer'],
            'imap_encryption' => ['nullable', 'in:ssl,tls,null'],
            'imap_username'  => ['nullable', 'string', 'max:255'],
            'imap_password'  => ['nullable', 'string'],
            'imap_folder'    => ['nullable', 'string', 'max:255'],
            'pop_host'       => ['nullable', 'string', 'max:255'],
            'pop_port'       => ['nullable', 'integer'],
            'pop_encryption' => ['nullable', 'in:ssl,null'],
            'pop_username'   => ['nullable', 'string', 'max:255'],
            'pop_password'   => ['nullable', 'string'],

            'smtp_enabled'    => ['nullable', 'boolean'],
            'smtp_host'       => ['nullable', 'string', 'max:255'],
            'smtp_port'       => ['nullable', 'integer'],
            'smtp_encryption' => ['nullable', 'in:tls,ssl,null'],
            'smtp_username'   => ['nullable', 'string', 'max:255'],
            'smtp_password'   => ['nullable', 'string'],
        ]);

        // 既存アカウントの編集モード: パスワード未入力なら保存済みパスワードを補完する.
        // mail_account_id は本人所有のものに限定 (他人の認証情報で外部接続を試行できないように).
        $existing = null;
        if (!empty($data['mail_account_id'])) {
            $existing = MailAccount::find($data['mail_account_id']);
            if (!$existing || $existing->user_id !== $request->user()->id) {
                abort(403, 'このメールアカウントへのアクセス権がありません。');
            }
            foreach (['imap_password', 'pop_password', 'smtp_password'] as $pw) {
                if (empty($data[$pw])) {
                    $data[$pw] = (string) ($existing->{$pw} ?? '');
                }
            }
        }

        $result = [
            'inbox' => null, // null = テストせず (disabled)
            'smtp'  => null,
        ];

        // ===== 受信テスト =====
        if (in_array($data['inbox_protocol'], ['imap', 'pop3'], true)) {
            $result['inbox'] = $this->testInbox($data);
        }

        // ===== 送信テスト =====
        if (!empty($data['smtp_enabled'])) {
            $result['smtp'] = $this->testSmtp($data);
        }

        return response()->json($result);
    }

    /**
     * IMAP / POP3 サーバへの接続 + 認証 + フォルダ一覧取得まで実施。
     */
    protected function testInbox(array $data): array
    {
        $protocol = $data['inbox_protocol'];
        if ($protocol === 'pop3') {
            $config = [
                'host'          => $data['pop_host'] ?? '',
                'port'          => (int) ($data['pop_port'] ?? 995),
                'encryption'    => ($data['pop_encryption'] ?? 'ssl') === 'null' ? false : ($data['pop_encryption'] ?? 'ssl'),
                'validate_cert' => false,
                'username'      => $data['pop_username'] ?? '',
                'password'      => $data['pop_password'] ?? '',
                'protocol'      => 'pop3',
            ];
        } else {
            $config = [
                'host'          => $data['imap_host'] ?? '',
                'port'          => (int) ($data['imap_port'] ?? 993),
                'encryption'    => ($data['imap_encryption'] ?? 'ssl') === 'null' ? false : ($data['imap_encryption'] ?? 'ssl'),
                'validate_cert' => false,
                'username'      => $data['imap_username'] ?? '',
                'password'      => $data['imap_password'] ?? '',
                'protocol'      => 'imap',
            ];
        }
        if (empty($config['host']) || empty($config['username'])) {
            return ['status' => 'error', 'stage' => 'config', 'message' => 'ホスト / ユーザー名が未入力です。'];
        }
        try {
            $client = \Webklex\IMAP\Facades\Client::make($config);
            $client->connect();
        } catch (\Throwable $e) {
            return ['status' => 'error', 'stage' => 'connect', 'message' => $this->humanizeError($e, $protocol), 'raw' => $e->getMessage()];
        }
        try {
            $folders = $client->getFolders();
            $count = is_countable($folders) ? count($folders) : iterator_count($folders);
        } catch (\Throwable $e) {
            return ['status' => 'error', 'stage' => 'auth', 'message' => $this->humanizeError($e, $protocol), 'raw' => $e->getMessage()];
        }
        if ($count === 0) {
            return ['status' => 'error', 'stage' => 'auth',
                'message' => $protocol === 'pop3'
                    ? 'POP3 に接続しましたが、メールボックスを開けませんでした。ユーザー名/パスワードを再確認してください。'
                    : 'IMAP に接続しましたが、フォルダを取得できませんでした。ユーザー名/パスワードを再確認してください。'];
        }
        return ['status' => 'ok', 'message' => strtoupper($protocol) . " 接続成功 (フォルダ {$count} 件)"];
    }

    /**
     * SMTP サーバへの接続 + EHLO + STARTTLS + AUTH まで実際に試す (メール送信はしない).
     */
    protected function testSmtp(array $data): array
    {
        $host = $data['smtp_host'] ?? '';
        $port = (int) ($data['smtp_port'] ?? 587);
        $enc  = $data['smtp_encryption'] ?? 'tls';
        $user = $data['smtp_username'] ?? '';
        $pass = $data['smtp_password'] ?? '';
        if ($host === '' || $user === '') {
            return ['status' => 'error', 'stage' => 'config', 'message' => 'SMTPホスト / ユーザー名が未入力です。'];
        }
        try {
            $tls = $enc === 'ssl';
            $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport($host, $port, $tls);
            $transport->setUsername($user);
            $transport->setPassword($pass);
            $transport->start();
            $transport->stop();
        } catch (\Throwable $e) {
            return ['status' => 'error', 'stage' => 'connect', 'message' => $this->humanizeError($e, 'smtp'), 'raw' => $e->getMessage()];
        }
        return ['status' => 'ok', 'message' => 'SMTP 認証成功'];
    }

    protected function humanizeError(\Throwable $e, string $proto): string
    {
        $msg = strtolower($e->getMessage());
        $cls = class_basename(get_class($e));
        if (str_contains($cls, 'AuthFailed') || str_contains($msg, 'authentication failed') ||
            str_contains($msg, 'login failed') || str_contains($msg, 'invalid credentials') ||
            str_contains($msg, 'wrong password') || str_contains($msg, '5.7.0') || str_contains($msg, '5.7.8')) {
            return "認証失敗 ({$proto})。ユーザー名/パスワードを再確認してください。";
        }
        if (str_contains($msg, 'could not resolve') || str_contains($msg, 'getaddrinfo') || str_contains($msg, 'name or service not known')) {
            return "ホスト名が解決できません ({$proto})。ホストを再確認してください。";
        }
        if (str_contains($msg, 'connection refused') || str_contains($msg, 'timed out') || str_contains($msg, 'no route to host')) {
            return "サーバに接続できません ({$proto})。ホスト/ポート/ネットワークを再確認してください。";
        }
        if (str_contains($msg, 'ssl') || str_contains($msg, 'tls') || str_contains($msg, 'certificate')) {
            return "SSL/TLS のハンドシェイクに失敗しました ({$proto})。暗号化方式(SSL/TLS/なし)とポートの組合せを再確認してください。";
        }
        return "{$proto} で予期しないエラー: " . $e->getMessage();
    }

    protected function authorizeOwnership(Request $request, MailAccount $account): void
    {
        if ($account->user_id !== $request->user()->id) {
            abort(403, 'このメールアカウントへのアクセス権がありません。');
        }
    }

    protected function validated(Request $request, ?MailAccount $existing = null): array
    {
        return $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'email_address'  => ['required', 'email'],
            'is_active'      => ['nullable', 'boolean'],

            'inbox_protocol' => ['required', 'in:imap,pop3,disabled'],
            'imap_host'      => ['nullable', 'string', 'max:255'],
            'imap_port'      => ['nullable', 'integer'],
            'imap_encryption' => ['nullable', 'in:ssl,tls,null'],
            'imap_username'  => ['nullable', 'string', 'max:255'],
            'imap_password'  => ['nullable', 'string'],
            'imap_folder'    => ['nullable', 'string', 'max:255'],
            'pop_host'       => ['nullable', 'string', 'max:255'],
            'pop_port'       => ['nullable', 'integer'],
            'pop_encryption' => ['nullable', 'in:ssl,null'],
            'pop_username'   => ['nullable', 'string', 'max:255'],
            'pop_password'   => ['nullable', 'string'],

            'smtp_enabled'   => ['nullable', 'boolean'],
            'smtp_host'      => ['nullable', 'string', 'max:255'],
            'smtp_port'      => ['nullable', 'integer'],
            'smtp_encryption' => ['nullable', 'in:tls,ssl,null'],
            'smtp_username'  => ['nullable', 'string', 'max:255'],
            'smtp_password'  => ['nullable', 'string'],
            'smtp_from_name' => ['nullable', 'string', 'max:100'],
        ], [
            'name.required'           => 'アカウント名を入力してください。',
            'email_address.required'  => 'メールアドレスを入力してください。',
            'email_address.email'     => 'メールアドレスの形式が正しくありません。',
            'inbox_protocol.in'       => '受信プロトコルが無効です。',
        ]);
    }
}
