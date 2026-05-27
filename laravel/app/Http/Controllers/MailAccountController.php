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

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;
        MailAccount::create($data);
        return redirect()->route('mail-accounts.index')->with('status', 'メールアカウントを追加しました。');
    }

    public function edit(Request $request, MailAccount $mailAccount): View
    {
        $this->authorizeOwnership($request, $mailAccount);
        return view('mail-accounts.form', ['account' => $mailAccount]);
    }

    public function update(Request $request, MailAccount $mailAccount): RedirectResponse
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
