<?php

namespace Modules\MailClient\Services;

use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\EmailThread;
use App\Models\MailAccount;
use App\Models\MailSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Webklex\IMAP\Facades\Client;

class EmailFetcher
{
    /**
     * システム既定 (mail_settings) + 全有効ユーザアカウントの順に取得する。
     */
    public function fetchAll(): int
    {
        $total = 0;
        try {
            $total += $this->fetch();
        } catch (\Throwable $e) {
            Log::error('[mail-fetch system] ' . $e->getMessage());
        }

        $accounts = MailAccount::query()
            ->where('is_active', true)
            ->whereIn('inbox_protocol', [MailAccount::PROTOCOL_IMAP, MailAccount::PROTOCOL_POP3])
            ->get();

        foreach ($accounts as $account) {
            try {
                $total += $this->fetchForAccount($account);
            } catch (\Throwable $e) {
                Log::error('[mail-fetch account#' . $account->id . '] ' . $e->getMessage());
            }
        }
        return $total;
    }

    /**
     * 互換: 引数なしならシステム既定 (MailSetting singleton) を取得する。
     */
    public function fetch(): int
    {
        $settings = MailSetting::getSettings();
        $protocol = $settings->inbox_protocol ?? 'imap';
        if ($protocol === 'pop3') {
            $config = $this->buildConfig('pop3', [
                'host' => $settings->pop_host, 'port' => $settings->pop_port,
                'encryption' => $settings->pop_encryption,
                'username' => $settings->pop_username, 'password' => $settings->pop_password,
            ]);
            $folderName = 'INBOX';
        } else {
            $config = $this->buildConfig('imap', [
                'host' => $settings->imap_host, 'port' => $settings->imap_port,
                'encryption' => $settings->imap_encryption,
                'username' => $settings->imap_username, 'password' => $settings->imap_password,
            ]);
            $folderName = $settings->imap_folder ?: 'INBOX';
        }
        return $this->runFetch($config, $folderName, ownerUserId: null, mailAccountId: null);
    }

    /**
     * 個人アカウント (MailAccount) で取得。fetched email/thread に owner と account をタグ付け。
     */
    public function fetchForAccount(MailAccount $account): int
    {
        if (!$account->canReceive()) {
            Log::info('[mail-fetch] account ' . $account->id . ' skipped (incomplete config)');
            return 0;
        }
        if ($account->inbox_protocol === MailAccount::PROTOCOL_POP3) {
            $config = $this->buildConfig('pop3', [
                'host' => $account->pop_host, 'port' => $account->pop_port,
                'encryption' => $account->pop_encryption,
                'username' => $account->pop_username, 'password' => $account->pop_password,
            ]);
            $folderName = 'INBOX';
        } else {
            $config = $this->buildConfig('imap', [
                'host' => $account->imap_host, 'port' => $account->imap_port,
                'encryption' => $account->imap_encryption,
                'username' => $account->imap_username, 'password' => $account->imap_password,
            ]);
            $folderName = $account->imap_folder ?: 'INBOX';
        }
        $imported = $this->runFetch(
            config: $config,
            folderName: $folderName,
            ownerUserId: $account->user_id,
            mailAccountId: $account->id,
        );
        $account->forceFill(['last_fetched_at' => now()])->save();
        return $imported;
    }

    protected function buildConfig(string $protocol, array $cred): array
    {
        return [
            'host' => $cred['host'] ?? null,
            'port' => $cred['port'] ?? null,
            'encryption' => ($cred['encryption'] ?? null) === 'null' ? false : ($cred['encryption'] ?? false),
            'validate_cert' => false,
            'username' => $cred['username'] ?? null,
            'password' => $cred['password'] ?? null,
            'protocol' => $protocol,
        ];
    }

    /**
     * 接続+メッセージ取り込みの共通処理。
     */
    protected function runFetch(array $config, string $folderName, ?int $ownerUserId, ?int $mailAccountId): int
    {
        if (empty($config['host']) || empty($config['username'])) {
            Log::warning('Mail fetch skipped: Host or username not configured.');
            return 0;
        }

        try {
            $client = Client::make($config);
            $client->connect();
        } catch (\Exception $e) {
            Log::error('Mail connection failed: ' . $e->getMessage());
            throw new \RuntimeException('メールサーバーに接続できませんでした: ' . $e->getMessage());
        }

        $folders = $client->getFolders();
        $imported = 0;
        $protocol = $config['protocol'] ?? 'imap';

        foreach ($folders as $folder) {
            if ($protocol === 'imap'
                && strcasecmp($folder->name, $folderName) !== 0
                && strcasecmp($folder->path, $folderName) !== 0) {
                continue;
            }

            $messages = $folder->messages()->all()->limit(50)->get();

            foreach ($messages as $message) {
                $messageId = (string) $message->getMessageId();

                // 重複検出: 同じユーザコンテキスト (owner_user_id) 内で重複を弾く。
                // システム = 全員共有プールなので owner_user_id IS NULL でも重複判定。
                $dupQ = Email::query()->where('message_id', $messageId);
                if ($messageId === '') {
                    // 空 message-id は重複判定不能 → 取り込む (情報損失より重複)
                } else {
                    if ($ownerUserId === null) {
                        $dupQ->whereNull('owner_user_id');
                    } else {
                        $dupQ->where('owner_user_id', $ownerUserId);
                    }
                    if ($dupQ->exists()) {
                        continue;
                    }
                }

                $thread = $this->findOrCreateThread($message, $ownerUserId, $mailAccountId);

                $receivedAt = $message->getDate() ?: now();

                $ccParts = [];
                $ccList = $message->getCc();
                if ($ccList instanceof \Webklex\PHPIMAP\Attribute) {
                    $ccList = $ccList->all();
                }
                if (is_iterable($ccList)) {
                    foreach ($ccList as $ccItem) {
                        if (isset($ccItem->mail)) {
                            $ccParts[] = $ccItem->mail;
                        }
                    }
                }
                $cc = implode(', ', $ccParts);

                $inReplyTo = (string) $message->getInReplyTo();
                $email = Email::create([
                    'thread_id'       => $thread->id,
                    'message_id'      => $messageId ?: null,
                    'in_reply_to'     => $inReplyTo ?: null,
                    'subject'         => $message->getSubject() ?: '(件名なし)',
                    'from_address'    => $message->getFrom()[0]->mail ?? 'unknown@example.com',
                    'from_name'       => $message->getFrom()[0]->personal ?? null,
                    'to_address'      => $message->getTo()[0]->mail ?? '',
                    'cc'              => $cc ?: null,
                    'body_text'       => $message->getTextBody() ?: '',
                    'body_html'       => $message->getHTMLBody() ?: '',
                    'received_at'     => $receivedAt,
                    'owner_user_id'   => $ownerUserId,
                    'mail_account_id' => $mailAccountId,
                ]);

                $this->handleAttachments($message, $email);

                $thread->update(['last_email_at' => $receivedAt]);

                $tags = $thread->tags ?? [];
                $newTags = array_values(array_filter($tags, fn($t) => !in_array($t, ['保留', '完了'])));
                if (count($tags) !== count($newTags)) {
                    $thread->update(['tags' => $newTags]);
                }

                $imported++;
            }
        }

        return $imported;
    }

    public function resolveThread(string $subject, ?string $inReplyTo, ?string $fromAddress = null): EmailThread
    {
        $thread = null;

        if ($inReplyTo) {
            $parent = Email::where('message_id', $inReplyTo)->first();
            if ($parent?->thread_id) {
                $thread = EmailThread::find($parent->thread_id);
            }
        }

        if (!$thread) {
            $normalized = preg_replace('/^(Re:\s*|Fwd:\s*)+/i', '', $subject);
            $thread = EmailThread::where('subject', 'like', "%{$normalized}%")
                ->orderByDesc('last_email_at')
                ->first();

            if (!$thread) {
                $customer = $fromAddress ? \App\Models\Customer::where('email', $fromAddress)->first() : null;
                $thread = EmailThread::create([
                    'subject'       => $normalized,
                    'status'        => 'inbox',
                    'last_email_at' => now(),
                    'customer_id'   => $customer?->id,
                ]);
            }
        }

        $tags = $thread->tags ?? [];
        $newTags = array_values(array_filter($tags, fn($t) => !in_array($t, ['保留', '完了'])));
        if (count($tags) !== count($newTags)) {
            $thread->update(['tags' => $newTags]);
        }
        return $thread;
    }

    protected function findOrCreateThread($message, ?int $ownerUserId, ?int $mailAccountId): EmailThread
    {
        $referencesAttr = $message->getReferences();
        $references = [];
        if ($referencesAttr instanceof \Webklex\PHPIMAP\Attribute) {
            $references = $referencesAttr->all();
        } elseif (is_array($referencesAttr)) {
            $references = $referencesAttr;
        } elseif (is_string($referencesAttr)) {
            $references = explode(' ', $referencesAttr);
        }

        $inReplyTo = (string) $message->getInReplyTo();
        if ($inReplyTo && !in_array($inReplyTo, $references)) {
            $references[] = $inReplyTo;
        }
        $references = array_filter($references);

        // 自分の owner スコープ内でスレッド検索
        $threadQuery = EmailThread::query();
        if ($ownerUserId === null) {
            $threadQuery->whereNull('owner_user_id');
        } else {
            $threadQuery->where('owner_user_id', $ownerUserId);
        }

        if (!empty($references)) {
            $parentEmail = Email::whereIn('message_id', $references)
                ->when($ownerUserId === null,
                    fn($q) => $q->whereNull('owner_user_id'),
                    fn($q) => $q->where('owner_user_id', $ownerUserId))
                ->first();
            if ($parentEmail && $parentEmail->thread) {
                return $parentEmail->thread;
            }
        }

        $subject = $message->getSubject() ?: '(件名なし)';
        $normalized = preg_replace('/^(Re:\s*|Fwd:\s*)+/i', '', $subject);
        $thread = $threadQuery->where('subject', 'like', "%{$normalized}%")
            ->orderByDesc('last_email_at')
            ->first();

        if ($thread) {
            return $thread;
        }

        $fromAddress = $message->getFrom()[0]->mail ?? null;
        $customer = $fromAddress ? \App\Models\Customer::where('email', $fromAddress)->first() : null;

        return EmailThread::create([
            'subject'         => $normalized,
            'status'          => 'inbox',
            'last_email_at'   => now(),
            'customer_id'     => $customer?->id,
            'owner_user_id'   => $ownerUserId,
            'mail_account_id' => $mailAccountId,
        ]);
    }

    protected function handleAttachments($message, $email): void
    {
        $attachments = $message->getAttachments();
        foreach ($attachments as $attachment) {
            $filename = $attachment->getName() ?: 'attachment';
            $safeName = preg_replace('/[^A-Za-z0-9._\-]/u', '_', $filename);
            $path = "attachments/{$email->id}/{$safeName}";
            Storage::disk('local')->put($path, $attachment->getContent());
            EmailAttachment::create([
                'email_id'  => $email->id,
                'filename'  => $filename,
                'disk_path' => $path,
                'size'      => $attachment->size ?? 0,
                'mime_type' => $attachment->getMimeType() ?: 'application/octet-stream',
            ]);
        }
    }
}
