<?php

namespace App\Services;

use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\EmailThread;
use App\Models\MailSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EmailFetchService
{
    public function fetch(): int
    {
        $settings = MailSetting::getSettings();
        $protocol = $settings->inbox_protocol ?? config('mail.inbox.protocol', 'imap');

        return match ($protocol) {
            'pop3'  => $this->fetchWithConnection($this->buildPop3Dsn($settings)),
            default => $this->fetchWithConnection($this->buildImapDsn($settings)),
        };
    }

    // ------------------------------------------------------------------
    // DSN ビルダー
    // ------------------------------------------------------------------

    private function buildImapDsn(MailSetting $settings): string
    {
        $cfg = [
            'host'       => $settings->imap_host,
            'port'       => $settings->imap_port,
            'encryption' => $settings->imap_encryption,
            'username'   => $settings->imap_username,
            'password'   => $settings->imap_password,
            'folder'     => $settings->imap_folder,
        ];
        $this->assertConfig($cfg, ['host', 'username', 'password'], 'IMAP');

        $flags  = $this->encryptionFlags($cfg['encryption']);
        $folder = $cfg['folder'] ?? 'INBOX';

        return "{{$cfg['host']}:{$cfg['port']}/imap{$flags}}{$folder}";
    }

    private function buildPop3Dsn(MailSetting $settings): string
    {
        $cfg = [
            'host'       => $settings->pop_host,
            'port'       => $settings->pop_port,
            'encryption' => $settings->pop_encryption,
            'username'   => $settings->pop_username,
            'password'   => $settings->pop_password,
        ];
        $this->assertConfig($cfg, ['host', 'username', 'password'], 'POP3');

        $flags = $this->encryptionFlags($cfg['encryption']);

        return "{{$cfg['host']}:{$cfg['port']}/pop3{$flags}}INBOX";
    }

    private function encryptionFlags(?string $enc): string
    {
        return match (strtolower((string) $enc)) {
            'ssl'  => '/ssl/novalidate-cert',
            'tls'  => '/tls/novalidate-cert',
            default => '/novalidate-cert',
        };
    }

    private function assertConfig(?array $cfg, array $keys, string $label): void
    {
        foreach ($keys as $key) {
            if (empty($cfg[$key])) {
                throw new \RuntimeException("{$label} config '{$key}' is not set. Check your .env file.");
            }
        }
    }

    // ------------------------------------------------------------------
    // メール取得・保存
    // ------------------------------------------------------------------

    private function fetchWithConnection(string $dsn): int
    {
        $settings = MailSetting::getSettings();
        $protocol = $settings->inbox_protocol ?? config('mail.inbox.protocol', 'imap');
        $username = $protocol === 'pop3' ? $settings->pop_username : $settings->imap_username;
        $password = $protocol === 'pop3' ? $settings->pop_password : $settings->imap_password;

        $mailbox = @imap_open($dsn, $username, $password, 0, 1);

        if (!$mailbox) {
            $error = imap_last_error();
            Log::error("Mail connection failed [{$protocol}] dsn={$dsn} error={$error}");
            throw new \RuntimeException("接続失敗: {$error}");
        }

        $count    = imap_num_msg($mailbox);
        $imported = 0;

        for ($i = 1; $i <= $count; $i++) {
            try {
                if ($this->importMessage($mailbox, $i)) {
                    $imported++;
                }
            } catch (\Throwable $e) {
                Log::error("Failed to import message #{$i}: " . $e->getMessage());
            }
        }

        imap_close($mailbox);
        return $imported;
    }

    private function importMessage($mailbox, int $msgNum): bool
    {
        $header    = imap_headerinfo($mailbox, $msgNum);
        $messageId = trim($header->message_id ?? '');
        $inReplyTo = trim($header->in_reply_to ?? $header->references ?? '');

        if ($messageId && Email::where('message_id', $messageId)->exists()) {
            return false;
        }

        $subject     = $header->subject ? $this->decodeHeader($header->subject) : '(件名なし)';
        $fromAddress = ($header->from[0]->mailbox ?? 'unknown') . '@' . ($header->from[0]->host ?? 'unknown');
        $fromName    = isset($header->from[0]->personal) ? imap_utf8($header->from[0]->personal) : null;
        $toAddress   = isset($header->to[0])
            ? ($header->to[0]->mailbox ?? '') . '@' . ($header->to[0]->host ?? '')
            : '';
        $receivedAt  = date('Y-m-d H:i:s', $header->udate ?? time());

        // CC を抽出
        $cc = '';
        if (isset($header->cc) && is_array($header->cc)) {
            $ccParts = array_map(
                fn($c) => ($c->mailbox ?? '') . '@' . ($c->host ?? ''),
                $header->cc
            );
            $cc = implode(', ', array_filter($ccParts, fn($a) => $a !== '@'));
        }

        [$bodyText, $bodyHtml] = $this->getBody($mailbox, $msgNum);
        $thread = $this->resolveThread($subject, $inReplyTo, $fromAddress);

        $email = Email::create([
            'thread_id'    => $thread->id,
            'message_id'   => $messageId ?: null,
            'in_reply_to'  => $inReplyTo ?: null,
            'subject'      => $subject,
            'from_address' => $fromAddress,
            'from_name'    => $fromName,
            'to_address'   => $toAddress,
            'cc'           => $cc ?: null,
            'body_text'    => $bodyText,
            'body_html'    => $bodyHtml,
            'received_at'  => $receivedAt,
        ]);

        $thread->update(['last_email_at' => $receivedAt]);

        // 添付ファイルを保存
        $this->saveAttachments($mailbox, $msgNum, $email);

        return true;
    }

    // ------------------------------------------------------------------
    // 本文抽出
    // ------------------------------------------------------------------

    private function getBody($mailbox, int $msgNum): array
    {
        $structure = imap_fetchstructure($mailbox, $msgNum);
        $bodyText  = '';
        $bodyHtml  = '';

        $this->walkBodyParts($mailbox, $msgNum, $structure, '', $bodyText, $bodyHtml);

        return [$bodyText ?: null, $bodyHtml ?: null];
    }

    private function walkBodyParts($mailbox, int $msgNum, object $part, string $section, string &$bodyText, string &$bodyHtml): void
    {
        $type    = $part->type ?? 0;
        $subtype = strtoupper($part->subtype ?? '');

        if ($type === 1) {
            foreach ($part->parts ?? [] as $idx => $child) {
                $childSection = $section ? "{$section}." . ($idx + 1) : (string) ($idx + 1);
                $this->walkBodyParts($mailbox, $msgNum, $child, $childSection, $bodyText, $bodyHtml);
            }
            return;
        }

        if ($type !== 0) {
            return;
        }

        // 添付として扱われる TEXT パートはスキップ
        if ($this->isAttachment($part)) {
            return;
        }

        $sec = $section ?: '1';
        $raw = imap_fetchbody($mailbox, $msgNum, $sec);
        $raw = $this->decodeBody($raw, $part->encoding ?? 0);
        $raw = $this->toUtf8($raw, $part->parameters ?? []);

        if ($subtype === 'HTML') {
            $raw = preg_replace(
                '/(<meta[^>]+charset\s*=\s*)["\']?[^"\';\s>]+/i',
                '$1"UTF-8"',
                $raw
            );
            $bodyHtml .= $raw;
        } elseif ($bodyText === '') {
            $bodyText = $raw;
        }
    }

    // ------------------------------------------------------------------
    // 添付ファイル抽出・保存
    // ------------------------------------------------------------------

    private function saveAttachments($mailbox, int $msgNum, Email $email): void
    {
        $structure = imap_fetchstructure($mailbox, $msgNum);
        $this->walkAttachmentParts($mailbox, $msgNum, $structure, '', $email);
    }

    private function walkAttachmentParts($mailbox, int $msgNum, object $part, string $section, Email $email): void
    {
        if (($part->type ?? 0) === 1) {
            foreach ($part->parts ?? [] as $idx => $child) {
                $childSection = $section ? "{$section}." . ($idx + 1) : (string) ($idx + 1);
                $this->walkAttachmentParts($mailbox, $msgNum, $child, $childSection, $email);
            }
            return;
        }

        if (!$this->isAttachment($part)) {
            return;
        }

        $filename = $this->getAttachmentFilename($part) ?? 'attachment';
        $sec      = $section ?: '1';
        $raw      = imap_fetchbody($mailbox, $msgNum, $sec);
        $raw      = $this->decodeBody($raw, $part->encoding ?? 0);

        if (empty($raw)) {
            return;
        }

        // ストレージに保存
        $safeName = preg_replace('/[^A-Za-z0-9._\-]/u', '_', $filename);
        $diskPath = "attachments/{$email->id}/{$safeName}";
        Storage::disk('local')->put($diskPath, $raw);

        // MIME タイプ判定
        $typeMap  = [0 => 'text', 1 => 'multipart', 2 => 'message', 3 => 'application', 4 => 'audio', 5 => 'image', 6 => 'video'];
        $mainType = $typeMap[$part->type ?? 3] ?? 'application';
        $mimeType = $mainType . '/' . strtolower($part->subtype ?? 'octet-stream');

        EmailAttachment::create([
            'email_id'  => $email->id,
            'filename'  => $filename,
            'mime_type' => $mimeType,
            'size'      => strlen($raw),
            'disk_path' => $diskPath,
        ]);
    }

    private function isAttachment(object $part): bool
    {
        if (isset($part->ifdisposition) && $part->ifdisposition &&
            strtolower($part->disposition ?? '') === 'attachment') {
            return true;
        }
        foreach ($part->dparameters ?? [] as $param) {
            if (strtolower($param->attribute ?? '') === 'filename') {
                return true;
            }
        }
        foreach ($part->parameters ?? [] as $param) {
            if (strtolower($param->attribute ?? '') === 'name') {
                return true;
            }
        }
        return false;
    }

    private function getAttachmentFilename(object $part): ?string
    {
        foreach ($part->dparameters ?? [] as $param) {
            if (strtolower($param->attribute ?? '') === 'filename') {
                return $this->decodeHeader($param->value);
            }
        }
        foreach ($part->parameters ?? [] as $param) {
            if (strtolower($param->attribute ?? '') === 'name') {
                return $this->decodeHeader($param->value);
            }
        }
        return null;
    }

    // ------------------------------------------------------------------
    // ユーティリティ
    // ------------------------------------------------------------------

    private function decodeBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            3       => base64_decode($body),
            4       => quoted_printable_decode($body),
            default => $body,
        };
    }

    private function toUtf8(string $text, mixed $params): string
    {
        $charset = 'UTF-8';
        foreach ((array) $params as $param) {
            if (strtolower($param->attribute ?? '') === 'charset') {
                $charset = strtoupper($param->value);
                break;
            }
        }
        if ($charset !== 'UTF-8') {
            $converted = @mb_convert_encoding($text, 'UTF-8', $charset);
            return ($converted !== false && $converted !== '') ? $converted : $text;
        }
        return $text;
    }

    private function decodeHeader(string $header): string
    {
        $parts = imap_mime_header_decode($header);
        if (!$parts) {
            return imap_utf8($header);
        }
        $result = '';
        foreach ($parts as $part) {
            $charset = strtoupper($part->charset ?? 'UTF-8');
            $text    = $part->text ?? '';
            if ($charset === 'DEFAULT' || $charset === 'UTF-8') {
                $result .= $text;
            } else {
                $converted = @mb_convert_encoding($text, 'UTF-8', $charset);
                $result .= ($converted !== false && $converted !== '') ? $converted : $text;
            }
        }
        return $result;
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
            $thread     = EmailThread::where('subject', 'like', "%{$normalized}%")
                ->orderByDesc('last_email_at')
                ->first();

            if (!$thread) {
                // スレッドを新規作成する場合、顧客がいれば自動で紐付け
                $customer = $fromAddress ? \App\Models\Customer::where('email', $fromAddress)->first() : null;
                $thread = EmailThread::create([
                    'subject'       => $normalized,
                    'last_email_at' => now(),
                    'customer_id'   => $customer?->id,
                ]);
            }
        }

        // 新しいメールを受信した場合は保留・完了タグを削除
        $tags = $thread->tags ?? [];
        $newTags = array_values(array_filter($tags, fn($t) => !in_array($t, ['保留', '完了'])));
        if (count($tags) !== count($newTags)) {
            $thread->update(['tags' => $newTags]);
        }
        
        return $thread;
    }
}
