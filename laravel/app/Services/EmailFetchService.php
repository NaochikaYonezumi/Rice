<?php

namespace App\Services;

use App\Models\Email;
use App\Models\EmailThread;
use App\Models\MailSetting;
use Illuminate\Support\Facades\Log;

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
    // DSN（接続文字列）ビルダー
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

        // 重複スキップ
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

        [$bodyText, $bodyHtml] = $this->getBody($mailbox, $msgNum);

        $thread = $this->resolveThread($subject, $inReplyTo);

        Email::create([
            'thread_id'    => $thread->id,
            'message_id'   => $messageId ?: null,
            'in_reply_to'  => $inReplyTo ?: null,
            'subject'      => $subject,
            'from_address' => $fromAddress,
            'from_name'    => $fromName,
            'to_address'   => $toAddress,
            'body_text'    => $bodyText,
            'body_html'    => $bodyHtml,
            'received_at'  => $receivedAt,
        ]);

        $thread->update(['last_email_at' => $receivedAt]);
        return true;
    }

    private function getBody($mailbox, int $msgNum): array
    {
        $structure = imap_fetchstructure($mailbox, $msgNum);
        $bodyText  = '';
        $bodyHtml  = '';

        $this->walkParts($mailbox, $msgNum, $structure, '', $bodyText, $bodyHtml);

        return [$bodyText ?: null, $bodyHtml ?: null];
    }

    private function walkParts($mailbox, int $msgNum, object $part, string $section, string &$bodyText, string &$bodyHtml): void
    {
        $type    = $part->type ?? 0;
        $subtype = strtoupper($part->subtype ?? '');

        if ($type === 1) {
            foreach ($part->parts ?? [] as $idx => $child) {
                $childSection = $section ? "{$section}." . ($idx + 1) : (string) ($idx + 1);
                $this->walkParts($mailbox, $msgNum, $child, $childSection, $bodyText, $bodyHtml);
            }
            return;
        }

        if ($type !== 0) {
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

    private function resolveThread(string $subject, string $inReplyTo): EmailThread
    {
        if ($inReplyTo) {
            $parent = Email::where('message_id', $inReplyTo)->first();
            if ($parent?->thread_id) {
                return EmailThread::findOrFail($parent->thread_id);
            }
        }

        $normalized = preg_replace('/^(Re:\s*|Fwd:\s*)+/i', '', $subject);
        $thread = EmailThread::where('subject', 'like', "%{$normalized}%")
            ->orderByDesc('last_email_at')
            ->first();

        return $thread ?? EmailThread::create([
            'subject'       => $normalized,
            'last_email_at' => now(),
        ]);
    }
}
