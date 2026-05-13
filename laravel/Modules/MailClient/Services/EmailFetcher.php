<?php

namespace Modules\MailClient\Services;

use App\Models\Email;
use App\Models\EmailThread;
use App\Models\EmailAttachment;
use App\Models\MailSetting;
use Webklex\IMAP\Facades\Client;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class EmailFetcher
{
    /**
     * Fetch emails from the server.
     */
    public function fetch(): int
    {
        $settings = MailSetting::getSettings();
        $protocol = $settings->inbox_protocol ?? 'imap';

        $config = [];
        if ($protocol === 'pop3') {
            $config = [
                'host'          => $settings->pop_host,
                'port'          => $settings->pop_port,
                'encryption'    => $settings->pop_encryption === 'null' ? false : $settings->pop_encryption,
                'validate_cert' => false,
                'username'      => $settings->pop_username,
                'password'      => $settings->pop_password,
                'protocol'      => 'pop3',
            ];
            $folderName = 'INBOX';
        } else {
            $config = [
                'host'          => $settings->imap_host,
                'port'          => $settings->imap_port,
                'encryption'    => $settings->imap_encryption === 'null' ? false : $settings->imap_encryption,
                'validate_cert' => false,
                'username'      => $settings->imap_username,
                'password'      => $settings->imap_password,
                'protocol'      => 'imap',
            ];
            $folderName = $settings->imap_folder ?: 'INBOX';
        }

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

        foreach ($folders as $folder) {
            if ($protocol === 'imap' && strcasecmp($folder->name, $folderName) !== 0 && strcasecmp($folder->path, $folderName) !== 0) {
                continue;
            }

            $messages = $folder->messages()->all()->limit(50)->get();

            foreach ($messages as $message) {
                // 重複検出 (Message-ID)
                $messageId = (string) $message->getMessageId();
                if ($messageId && Email::where('message_id', $messageId)->exists()) {
                    continue;
                }

                // スレッドの特定または作成
                $thread = $this->findOrCreateThread($message);
                // 新規スレッドかどうかを後で判定するために保持 (Eloquent の wasRecentlyCreated を退避)
                $threadIsNew = (bool) $thread->wasRecentlyCreated;

                $receivedAt = $message->getDate();
                if (!$receivedAt) {
                    $receivedAt = now();
                }

                // cc extraction
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

                // メールの保存
                $inReplyTo = (string) $message->getInReplyTo();
                $email = Email::create([
                    'thread_id'    => $thread->id,
                    'message_id'   => $messageId ?: null,
                    'in_reply_to'  => $inReplyTo ?: null,
                    'subject'      => $message->getSubject() ?: '(件名なし)',
                    'from_address' => $message->getFrom()[0]->mail ?? 'unknown@example.com',
                    'from_name'    => $message->getFrom()[0]->personal ?? null,
                    'to_address'   => $message->getTo()[0]->mail ?? '',
                    'cc'           => $cc ?: null,
                    'body_text'    => $message->getTextBody() ?: '',
                    'body_html'    => $message->getHTMLBody() ?: '',
                    'received_at'  => $receivedAt,
                ]);

                // 添付ファイルの処理
                $this->handleAttachments($message, $email);

                // スレッドの最終更新日時を更新
                $thread->update(['last_email_at' => $receivedAt]);
                
                // 保留・完了タグを削除
                $tags = $thread->tags ?? [];
                $newTags = array_values(array_filter($tags, fn($t) => !in_array($t, ['保留', '完了'])));
                if (count($tags) !== count($newTags)) {
                    $thread->update(['tags' => $newTags]);
                }

                // Phase 6-2: 新規スレッドのみ自動割当 (既存スレッドへの返信は owner_lock で割当を変えない)
                if ($threadIsNew) {
                    try {
                        app(\Modules\Workflow\Services\WorkflowEngine::class)->autoAssign($thread->fresh());
                    } catch (\Throwable $e) {
                        \Log::warning('WorkflowEngine::autoAssign failed', [
                            'thread_id' => $thread->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $imported++;
            }
        }

        return $imported;
    }

    public function resolveThread(string $subject, ?string $inReplyTo, ?string $fromAddress = null): EmailThread
    {
        $thread = null;

        // (1) 件名のチケット番号で最優先マッチ (カラム未作成環境でも落ちないようガード)
        $ticket = EmailThread::extractTicketNumber($subject);
        if ($ticket) {
            try {
                $thread = EmailThread::where('ticket_number', $ticket)->first();
            } catch (\Throwable $e) {
                $thread = null;
            }
        }

        // (2) In-Reply-To のメッセージID から親スレッドを辿る
        if (!$thread && $inReplyTo) {
            $parent = Email::where('message_id', $inReplyTo)->first();
            if ($parent?->thread_id) {
                $thread = EmailThread::find($parent->thread_id);
            }
        }

        // (3) 件名 (Re:/Fwd: 除去 + チケットタグ除去) で部分一致検索
        if (!$thread) {
            $normalized = preg_replace('/^(Re:\s*|Fwd:\s*)+/i', '', $subject);
            $normalized = preg_replace(EmailThread::TICKET_REGEX, '', $normalized);
            $normalized = trim($normalized);
            if ($normalized !== '') {
                $thread = EmailThread::where('subject', 'like', "%{$normalized}%")
                    ->orderByDesc('last_email_at')
                    ->first();
            }

            if (!$thread) {
                $customer = $fromAddress ? \App\Models\Customer::where('email', $fromAddress)->first() : null;
                $thread = EmailThread::create([
                    'subject'       => $normalized !== '' ? $normalized : $subject,
                    'status'        => 'inbox',
                    'last_email_at' => now(),
                    'customer_id'   => $customer?->id,
                ]);
            }
        }

        // チケット番号を未発行なら付与 (存在チェック含む)
        $thread->ensureTicketNumber();

        // 新しいメールを受信した場合は保留・完了タグを削除
        $tags = $thread->tags ?? [];
        $newTags = array_values(array_filter($tags, fn($t) => !in_array($t, ['保留', '完了'])));
        if (count($tags) !== count($newTags)) {
            $thread->update(['tags' => $newTags]);
        }
        
        return $thread;
    }

    protected function findOrCreateThread($message)
    {
        $subject = $message->getSubject() ?: '(件名なし)';

        // (0) 件名のチケット番号で最優先マッチ (カラム未作成環境でも落ちないようガード)
        $ticket = EmailThread::extractTicketNumber($subject);
        if ($ticket) {
            try {
                $byTicket = EmailThread::where('ticket_number', $ticket)->first();
                if ($byTicket) {
                    $byTicket->ensureTicketNumber();
                    return $byTicket;
                }
            } catch (\Throwable $e) {
                // カラム未存在時はスキップ
            }
        }

        // In-Reply-To または References に基づくスレッド検索
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

        if (!empty($references)) {
            $parentEmail = Email::whereIn('message_id', $references)->first();
            if ($parentEmail && $parentEmail->thread) {
                $parentEmail->thread->ensureTicketNumber();
                return $parentEmail->thread;
            }
        }

        $normalized = preg_replace('/^(Re:\s*|Fwd:\s*)+/i', '', $subject);
        $normalized = preg_replace(EmailThread::TICKET_REGEX, '', $normalized);
        $normalized = trim($normalized);
        if ($normalized !== '') {
            $thread = EmailThread::where('subject', 'like', "%{$normalized}%")
                ->orderByDesc('last_email_at')
                ->first();
            if ($thread) {
                $thread->ensureTicketNumber();
                return $thread;
            }
        }

        $fromAddress = $message->getFrom()[0]->mail ?? null;
        $customer = $fromAddress ? \App\Models\Customer::where('email', $fromAddress)->first() : null;

        // 新規スレッド作成 → 自動でチケット番号を付与
        $thread = EmailThread::create([
            'subject'       => $normalized !== '' ? $normalized : $subject,
            'status'        => 'inbox',
            'last_email_at' => now(),
            'customer_id'   => $customer?->id,
        ]);
        $thread->ensureTicketNumber();
        return $thread;
    }

    protected function handleAttachments($message, $email)
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
