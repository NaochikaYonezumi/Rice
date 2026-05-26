<?php

namespace App\Http\Controllers;

use App\Models\AiSetting;
use App\Models\MailSetting;
use App\Models\SsoSetting;
use App\Services\RagApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function mail()
    {
        $settings = MailSetting::getSettings();
        return view('settings.mail', compact('settings'));
    }

    public function updateMail(Request $request)
    {
        $validated = $request->validate([
            'smtp_host'         => 'nullable|string|max:255',
            'smtp_port'         => 'required|integer',
            'smtp_encryption'   => 'required|in:tls,ssl,null',
            'smtp_username'     => 'nullable|string|max:255',
            'smtp_password'     => 'nullable|string|max:255',
            'smtp_from_address' => 'nullable|email|max:255',
            'smtp_from_name'    => 'nullable|string|max:255',
            'inbox_protocol'    => 'required|in:imap,pop3',
            'imap_host'         => 'nullable|string|max:255',
            'imap_port'         => 'required|integer',
            'imap_encryption'   => 'required|in:ssl,tls,null',
            'imap_username'     => 'nullable|string|max:255',
            'imap_password'     => 'nullable|string|max:255',
            'imap_folder'       => 'nullable|string|max:255',
            'pop_host'          => 'nullable|string|max:255',
            'pop_port'          => 'required|integer',
            'pop_encryption'    => 'required|in:ssl,tls,null',
            'pop_username'      => 'nullable|string|max:255',
            'pop_password'      => 'nullable|string|max:255',
            // 送信ポリシー (管理者のみ変更可. 非管理者からの送信は無視する).
            'send_policy'       => 'nullable|in:flexible,approval_required',
            // ゴミ箱 / 迷惑メール の保持期間 (= 自動完全削除までの日数).
            // 管理者のみ変更可. 範囲は 1〜3650 (= 約 10 年). 0 以下は受け付けない (= 即時削除回避).
            'trash_retention_days' => 'nullable|integer|min:' . MailSetting::MIN_RETENTION_DAYS . '|max:' . MailSetting::MAX_RETENTION_DAYS,
            'spam_retention_days'  => 'nullable|integer|min:' . MailSetting::MIN_RETENTION_DAYS . '|max:' . MailSetting::MAX_RETENTION_DAYS,
        ]);

        $settings = MailSetting::getSettings();

        // 非管理者が送って来た管理者専用フィールドはサーバ側でも防御除去 (UI 側 disable のバックアップ).
        $isAdmin = (bool) (auth()->user()?->isAdmin());
        if (!$isAdmin) {
            unset($validated['send_policy']);
            unset($validated['trash_retention_days']);
            unset($validated['spam_retention_days']);
        }

        $settings->update($validated);

        return redirect()->route('settings.mail')->with('success', '設定を保存しました');
    }

    /**
     * 受信メールサーバへの「接続テスト」。
     * - 入力フォームの値そのまま (DB 保存前) でテストできる
     * - webklex 経由で connect + getFolders + INBOX のメッセージ件数取得を試す
     * - 認証 / 接続 / SSL のエラーを症状別に日本語で返す
     */
    public function testMailConnection(Request $request): JsonResponse
    {
        $data = $request->validate([
            'inbox_protocol'  => 'required|in:imap,pop3',
            'imap_host'       => 'nullable|string|max:255',
            'imap_port'       => 'nullable|integer',
            'imap_encryption' => 'nullable|in:ssl,tls,null',
            'imap_username'   => 'nullable|string|max:255',
            'imap_password'   => 'nullable|string|max:255',
            'imap_folder'     => 'nullable|string|max:255',
            'pop_host'        => 'nullable|string|max:255',
            'pop_port'        => 'nullable|integer',
            'pop_encryption'  => 'nullable|in:ssl,tls,null',
            'pop_username'    => 'nullable|string|max:255',
            'pop_password'    => 'nullable|string|max:255',
        ]);

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
            return response()->json([
                'status' => 'error',
                'stage'  => 'config',
                'message' => 'ホスト / ユーザー名が未入力です。',
            ], 422);
        }

        try {
            $client = \Webklex\IMAP\Facades\Client::make($config);
            $client->connect();
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'stage'   => 'connect',
                'message' => self::humanizeMailError($e, $protocol),
                'raw'     => $e->getMessage(),
            ], 200);
        }

        try {
            $folders = $client->getFolders();
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'stage'   => 'auth',
                'message' => self::humanizeMailError($e, $protocol),
                'raw'     => $e->getMessage(),
            ], 200);
        }

        $folderCount = is_countable($folders) ? count($folders) : iterator_count($folders);
        if ($folderCount === 0) {
            return response()->json([
                'status'  => 'error',
                'stage'   => 'auth',
                'message' => $protocol === 'pop3'
                    ? 'POP3 サーバに接続しましたが、メールボックスを開けませんでした。ユーザー名/パスワードが間違っている可能性があります。'
                    : 'IMAP サーバに接続しましたが、フォルダ一覧を取得できませんでした。ユーザー名/パスワードが間違っている可能性があります。',
            ], 200);
        }

        // INBOX メッセージ件数を取得 (失敗しても warning だけにする)
        $folderName = $data['imap_folder'] ?? 'INBOX';
        if (!$folderName) $folderName = 'INBOX';
        $messageCount = null;
        try {
            foreach ($folders as $folder) {
                if ($protocol === 'imap' && strcasecmp($folder->name, $folderName) !== 0 && strcasecmp($folder->path, $folderName) !== 0) {
                    continue;
                }
                $messageCount = $folder->messages()->all()->count();
                break;
            }
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'stage'   => 'list_messages',
                'message' => self::humanizeMailError($e, $protocol),
                'raw'     => $e->getMessage(),
            ], 200);
        }

        return response()->json([
            'status'        => 'ok',
            'protocol'      => $protocol,
            'folder_count'  => $folderCount,
            'message_count' => $messageCount,
            'message'       => "接続成功。フォルダ {$folderCount} 件、INBOX のメール数 " . ($messageCount ?? '?') . " 件。",
        ]);
    }

    protected static function humanizeMailError(\Throwable $e, string $protocol): string
    {
        $msg = $e->getMessage();
        $cls = class_basename(get_class($e));
        $low = strtolower($msg);
        if (str_contains($cls, 'AuthFailed') || str_contains($low, 'authentication failed') ||
            str_contains($low, 'login failed') || str_contains($low, '-err') ||
            str_contains($low, 'invalid credentials') || str_contains($low, 'wrong password')) {
            return "認証に失敗しました ({$protocol})。ユーザー名 / パスワードを再確認してください。";
        }
        if (str_contains($low, 'could not resolve') || str_contains($low, 'getaddrinfo')) {
            return "ホスト名が解決できません ({$protocol})。ホストを再確認してください。";
        }
        if (str_contains($low, 'connection refused') || str_contains($low, 'timed out')) {
            return "サーバに接続できません ({$protocol})。ホスト / ポート / ネットワークを再確認してください。";
        }
        if (str_contains($low, 'ssl') || str_contains($low, 'tls') || str_contains($low, 'certificate')) {
            return "SSL/TLS のハンドシェイクに失敗しました ({$protocol})。暗号化方式 (SSL/TLS/なし) とポートの組合せを再確認してください。";
        }
        return "{$protocol} で予期しないエラーが発生しました: " . $e->getMessage();
    }

    public function ai(RagApiService $ragApi)
    {
        $settings = AiSetting::getSettings();
        $models = [];
        try {
            $models = $ragApi->getModels();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('SettingsController.ai: failed to fetch models from rag-api: ' . $e->getMessage());
        }

        return view('settings.ai', compact('settings', 'models'));
    }

    public function getDefaultPrompt(): JsonResponse
    {
        $settings = AiSetting::getSettings();
        return response()->json(['prompt' => $settings->default_reply_prompt ?? '']);
    }

    public function saveDefaultPrompt(Request $request): JsonResponse
    {
        $settings = AiSetting::getSettings();
        $settings->update(['default_reply_prompt' => $request->input('prompt', '')]);
        return response()->json(['status' => 'ok']);
    }

    public function updateAi(Request $request)
    {
        $validated = $request->validate([
            'anthropic_api_key'    => 'nullable|string|max:2048',
            'gemini_api_key'       => 'nullable|string|max:2048',
            'default_provider'     => 'required|in:ollama,claude,gemini',
            'default_model'        => 'nullable|string|max:128',
            'default_reply_prompt' => 'nullable|string|max:5000',
            'agent_name'           => 'nullable|string|max:255',
            'agent_signature'      => 'nullable|string|max:5000',
        ]);

        $settings = AiSetting::getSettings();

        if (empty($validated['anthropic_api_key'])) {
            unset($validated['anthropic_api_key']);
        }
        if (empty($validated['gemini_api_key'])) {
            unset($validated['gemini_api_key']);
        }

        try {
            $settings->update($validated);
        } catch (\Throwable $e) {
            return redirect()->route('settings.ai')
                ->withInput()
                ->with('error', 'APIキーの保存に失敗しました: ' . $e->getMessage());
        }

        return redirect()->route('settings.ai')->with('success', '設定を保存しました');
    }

    public function sso()
    {
        $settings = SsoSetting::getSettings();
        return view('settings.sso', compact('settings'));
    }

    public function updateSso(Request $request)
    {
        $validated = $request->validate([
            'is_enabled'           => 'nullable|boolean',
            'google_client_id'     => 'nullable|string|max:255',
            'google_client_secret' => 'nullable|string|max:255',
            'google_redirect_uri'  => 'nullable|url|max:500',
            'require_invitation'   => 'nullable|boolean',
        ]);

        $settings = SsoSetting::getSettings();
        
        $settings->update([
            'is_enabled'           => $request->has('is_enabled'),
            'google_client_id'     => $validated['google_client_id'],
            'google_client_secret' => $validated['google_client_secret'],
            'google_redirect_uri'  => $validated['google_redirect_uri'],
            'require_invitation'   => $request->has('require_invitation'),
        ]);

        return redirect()->route('settings.sso')->with('success', 'SSO設定を保存しました');
    }
}
