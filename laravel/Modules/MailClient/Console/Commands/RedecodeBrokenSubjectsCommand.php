<?php

namespace Modules\MailClient\Console\Commands;

use App\Models\Email;
use App\Models\EmailThread;
use App\Models\MailAccount;
use App\Models\MailSetting;
use Illuminate\Console\Command;
use Modules\MailClient\Services\EmailFetcher;
use Webklex\IMAP\Facades\Client;

/**
 * 過去に取り込んだメールの件名が MIME デコード失敗で `???...` 化けしているもの
 * (= 5 文字以上の連続 `?` を含む subject) について,
 *   1) もとの IMAP アカウントに再接続
 *   2) message_id でメッセージを検索
 *   3) 生の Subject ヘッダを取り直す
 *   4) 改善版 decodeMimeHeaderRobust() で再デコード
 *   5) emails.subject および (件名がスレッド名と同じなら) email_threads.subject を更新
 * する一発リカバリコマンド.
 *
 * 使い方:
 *   php artisan mail:redecode-broken-subjects                # 全件 (?{5,}) 自動検出
 *   php artisan mail:redecode-broken-subjects --email-id=143  # 特定 1 件だけ
 *   php artisan mail:redecode-broken-subjects --dry-run       # 更新せずプレビューだけ
 */
class RedecodeBrokenSubjectsCommand extends Command
{
    protected $signature = 'mail:redecode-broken-subjects
                            {--email-id= : 特定の emails.id 1 件だけ処理}
                            {--dry-run : 更新を実行せずに新旧 subject を表示するだけ}';

    protected $description = '化けた subject を IMAP から取り直して再デコードする';

    public function handle(EmailFetcher $fetcher): int
    {
        $query = Email::query();
        if ($emailId = $this->option('email-id')) {
            $query->where('id', (int) $emailId);
        } else {
            // ? が 5 個以上連続するものを「化け」とみなす
            $query->where('subject', 'REGEXP', '\\?{5,}');
        }
        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->info('対象メッセージなし.');
            return self::SUCCESS;
        }

        $this->info("対象 {$rows->count()} 件を処理します.");

        // mail_account_id ごとに IMAP クライアントをキャッシュ.
        // null (= 共有メール) は MailSetting を使う.
        $clients = [];
        $okCount   = 0;
        $missCount = 0;
        $skipCount = 0;
        $dryRun = (bool) $this->option('dry-run');

        foreach ($rows as $em) {
            $key = $em->mail_account_id ?? 'shared';
            if (!isset($clients[$key])) {
                try {
                    $clients[$key] = $this->makeClient($em->mail_account_id);
                } catch (\Throwable $e) {
                    $this->error("[email id={$em->id}] IMAP 接続失敗: " . $e->getMessage());
                    $clients[$key] = false;
                }
            }
            $client = $clients[$key];
            if (!$client) { $skipCount++; continue; }

            $messageId = (string) $em->message_id;
            if ($messageId === '') {
                $this->warn("[email id={$em->id}] message_id 無し → スキップ");
                $skipCount++;
                continue;
            }

            $msg = $this->findMessageByMessageId($client, $messageId);
            if (!$msg) {
                $this->warn("[email id={$em->id}] IMAP サーバ上に見つからず → スキップ");
                $missCount++;
                continue;
            }

            // 生 Subject ヘッダを取り出す.
            // Webklex の getHeader()->raw に全ヘッダブロックがある.
            $rawHeader = '';
            try {
                $rawHeader = (string) $msg->getHeader()->raw;
            } catch (\Throwable $e) {
                // fallback: getRawHeader()
                try { $rawHeader = (string) $msg->getRawHeader(); } catch (\Throwable $_) {}
            }
            $rawSubject = $this->extractRawSubjectFromHeaderBlock($rawHeader);
            if ($rawSubject === '') {
                $this->warn("[email id={$em->id}] Subject 行を生ヘッダから抽出できず → スキップ");
                $skipCount++;
                continue;
            }

            $decoded = trim($fetcher->decodeMimeHeaderRobust($rawSubject));
            if ($decoded === '' || preg_match('/\\?{5,}/', $decoded)) {
                $this->warn("[email id={$em->id}] 再デコード結果も化けている. raw='" . $this->truncate($rawSubject, 80) . "' decoded='{$decoded}'");
                $skipCount++;
                continue;
            }

            // UTF-8 で 255 文字に丸める (DB カラム制限).
            if (function_exists('mb_strimwidth')) {
                $decoded = mb_strimwidth($decoded, 0, 255, '', 'UTF-8');
            }

            $this->line("[email id={$em->id}]");
            $this->line("   old subject = {$em->subject}");
            $this->line("   new subject = {$decoded}");

            if (!$dryRun) {
                $oldSubject = $em->subject;
                $em->subject = $decoded;
                $em->save();

                // スレッド側も subject が一致 (= このメールがスレッド名のオリジン) なら一緒に更新.
                if ($em->thread_id) {
                    $thread = EmailThread::find($em->thread_id);
                    if ($thread && $thread->subject === $oldSubject) {
                        $thread->subject = $decoded;
                        $thread->save();
                        $this->line("   thread {$thread->id} subject も更新.");
                    }
                }
            }
            $okCount++;
        }

        $this->info("done: 更新={$okCount} 見つからず={$missCount} スキップ={$skipCount}" . ($dryRun ? ' (dry-run)' : ''));
        return self::SUCCESS;
    }

    /**
     * mail_account_id から IMAP クライアントを生成.
     * null なら MailSetting (共有メール) を使う.
     */
    protected function makeClient(?int $mailAccountId)
    {
        if ($mailAccountId === null) {
            $s = MailSetting::first();
            if (!$s) throw new \RuntimeException('mail_settings レコードが存在しない');
            $config = [
                'host'       => $s->imap_host,
                'port'       => (int) $s->imap_port,
                'encryption' => $s->imap_encryption === 'null' ? false : $s->imap_encryption,
                'validate_cert' => false,
                'username'   => $s->imap_username,
                'password'   => $s->imap_password,
                'protocol'   => 'imap',
            ];
        } else {
            $a = MailAccount::find($mailAccountId);
            if (!$a) throw new \RuntimeException("mail_account id={$mailAccountId} が見つからない");
            if (strtolower((string) $a->inbox_protocol) !== 'imap') {
                throw new \RuntimeException("mail_account id={$mailAccountId} は IMAP ではない (" . $a->inbox_protocol . ')');
            }
            $config = [
                'host'       => $a->imap_host,
                'port'       => (int) $a->imap_port,
                'encryption' => $a->imap_encryption === 'null' ? false : $a->imap_encryption,
                'validate_cert' => false,
                'username'   => $a->imap_username,
                'password'   => $a->imap_password,
                'protocol'   => 'imap',
            ];
        }
        $client = Client::make($config);
        $client->connect();
        return $client;
    }

    /**
     * 全フォルダから message_id 一致のメッセージを探す.
     */
    protected function findMessageByMessageId($client, string $messageId)
    {
        // Message-ID は `<...>` で囲まれていることが多いが、DB には剥がして入れてるパターン
        // と入れてるパターンが混在しうるので、両方試す.
        $candidates = [$messageId];
        if (!str_starts_with($messageId, '<')) $candidates[] = '<' . $messageId . '>';
        if (str_starts_with($messageId, '<'))  $candidates[] = trim($messageId, '<>');

        foreach ($client->getFolders() as $folder) {
            foreach ($candidates as $mid) {
                try {
                    $msgs = $folder->query()->header('Message-ID', $mid)->setFetchBody(false)->get();
                } catch (\Throwable $e) {
                    continue;
                }
                if ($msgs && $msgs->count() > 0) {
                    return $msgs->first();
                }
            }
        }
        return null;
    }

    /**
     * 全ヘッダブロックから Subject 行 (folded 含む) を取り出す.
     */
    protected function extractRawSubjectFromHeaderBlock(string $rawHeader): string
    {
        if ($rawHeader === '') return '';
        // 行末を統一
        $h = str_replace("\r\n", "\n", $rawHeader);
        // 'Subject:' から始まり、次のヘッダ (=行頭が空白以外) まで取る.
        if (preg_match('/^Subject:[ \t]*((?:.*\n(?:[ \t]+.*\n)*))/mi', $h . "\n", $m)) {
            // unfold (folded line の継続行は前行に連結)
            $val = preg_replace('/\n[ \t]+/', ' ', $m[1]);
            return trim((string) $val);
        }
        return '';
    }

    protected function truncate(string $s, int $len): string
    {
        if (strlen($s) <= $len) return $s;
        return substr($s, 0, $len) . '…';
    }
}
