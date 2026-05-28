<?php

namespace Modules\MailClient\Services;

use App\Models\Email;
use App\Models\EmailThread;
use App\Models\EmailAttachment;
use App\Models\MailBlockRule;
use App\Models\MailSetting;
use Webklex\IMAP\Facades\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class EmailFetcher
{
    /**
     * 直近の fetch で発生した「個別メール 1 通単位のエラー」一覧。
     * 接続不能などの全体エラーは ここではなく例外で投げる。
     */
    protected array $perMailErrors = [];

    /**
     * 接続段階でのエラーメッセージ (致命的)。例外時に呼び出し側へ伝える参考用。
     */
    protected ?string $connectionError = null;

    /**
     * 直近の fetch で「既存メールを backfill (再抽出して上書き更新) した件数」。
     * 過去取り込み時に from_address が壊れていた (MAILER-DAEMON など) ような
     * 不完全データを、同期時に再パースして救済するための仕組み。
     */
    protected int $backfilledCount = 0;

    /**
     * 直近の取り込みで発生したエラー情報を返す。
     *
     * 返り値:
     *  [
     *    'count'        => int,    // エラー件数
     *    'errors'       => array,  // [{message_id, subject, from, error}, ...]
     *    'connection_error' => ?string,
     *    'backfilled'   => int,    // 既存メールの再パース更新件数
     *  ]
     */
    public function getLastErrors(): array
    {
        return [
            'count'            => count($this->perMailErrors),
            'errors'           => $this->perMailErrors,
            'connection_error' => $this->connectionError,
            'backfilled'       => $this->backfilledCount,
        ];
    }

    /**
     * 既存 Email 行が「再抽出 (backfill) すべき不完全データか」を判定する。
     *
     * 判定対象:
     *  - from_address が空 / "@" を含まない / 'unknown@example.com' プレースホルダ
     *  - subject が空または '(件名なし)' のままで、後でちゃんとした件名が拾える可能性がある
     *  - body_text が空 or バイナリプレースホルダで救済の余地がある
     *
     * 「もう一段救えそうな状態」なら true。完全な状態なら false。
     */
    protected function emailNeedsBackfill(Email $existing): bool
    {
        $addr = trim((string) ($existing->from_address ?? ''));
        if ($addr === '' || !str_contains($addr, '@') || !filter_var($addr, FILTER_VALIDATE_EMAIL)) {
            return true;
        }
        if (strcasecmp($addr, 'unknown@example.com') === 0) {
            return true;
        }
        $subj = trim((string) ($existing->subject ?? ''));
        if ($subj === '' || $subj === '(件名なし)') {
            return true;
        }
        // 上流 MTA で日本語バイトが ? に置換された件名 (= "Re: ???????????") は
        // backfill 経路で thread.subject から復元を試みたいので、ここで救済対象に含める.
        if ($this->subjectLooksLossy($subj)) {
            return true;
        }
        $body = (string) ($existing->body_text ?? '');
        if ($body === '' || str_starts_with($body, '(本文を表示できませんでした)')) {
            return true;
        }
        return false;
    }

    /**
     * 既存 Email に対し、再抽出した値で「壊れていたフィールドだけ」を差分更新する。
     * 健全な既存値は温存する。変更が発生したら true を返す。
     *
     * @param array{from_address: string, from_name: ?string, subject: string, body_text: string, body_html: string} $fresh
     */
    protected function backfillExistingEmail(Email $existing, array $fresh): bool
    {
        $updates = [];

        // from_address: 既存が壊れていて、再抽出が有効なメールアドレスならアドレスごと差し替え
        $existingAddr = trim((string) ($existing->from_address ?? ''));
        $existingIsBroken = ($existingAddr === ''
            || !str_contains($existingAddr, '@')
            || !filter_var($existingAddr, FILTER_VALIDATE_EMAIL)
            || strcasecmp($existingAddr, 'unknown@example.com') === 0);
        $freshAddr = trim((string) ($fresh['from_address'] ?? ''));
        $freshIsValid = $freshAddr !== ''
            && str_contains($freshAddr, '@')
            && filter_var($freshAddr, FILTER_VALIDATE_EMAIL)
            && strcasecmp($freshAddr, 'unknown@example.com') !== 0;
        if ($existingIsBroken && $freshIsValid) {
            $updates['from_address'] = $this->cleanUtf8($freshAddr, 255);
        }

        // from_name: 既存が空 (または既存 from_address と同値の冗長コピー) で、
        // 再抽出が表示名を持っていれば補完
        $existingName = trim((string) ($existing->from_name ?? ''));
        $freshName    = $fresh['from_name'] !== null ? trim((string) $fresh['from_name']) : '';
        if ($freshName !== '' && ($existingName === '' || $existingName === $existingAddr)) {
            $updates['from_name'] = $this->cleanUtf8($freshName, 255);
        }

        // subject: 既存が空 / '(件名なし)' / lossy (? 置換) で、再抽出が実体を持っていれば差し替え.
        // lossy 判定を含めることで、過去の fetch で `Re: ?????????????` のまま保存された
        // 件名も次回 fetch のタイミングで thread.subject 経由で自動修復される.
        $existingSubj = trim((string) ($existing->subject ?? ''));
        $freshSubj    = trim((string) ($fresh['subject'] ?? ''));
        $existingSubjBroken = ($existingSubj === ''
            || $existingSubj === '(件名なし)'
            || $this->subjectLooksLossy($existingSubj));
        $freshSubjOk = ($freshSubj !== ''
            && $freshSubj !== '(件名なし)'
            && !$this->subjectLooksLossy($freshSubj));
        if ($existingSubjBroken && $freshSubjOk) {
            $updates['subject'] = $freshSubj; // fresh は既に cleanUtf8 + 255 切り済み
        }

        // body_text: 既存が空 or バイナリプレースホルダで、再抽出が実体テキストなら差し替え
        $existingBody = (string) ($existing->body_text ?? '');
        $freshBody    = (string) ($fresh['body_text'] ?? '');
        if (($existingBody === '' || str_starts_with($existingBody, '(本文を表示できませんでした)'))
            && $freshBody !== ''
            && !str_starts_with($freshBody, '(本文を表示できませんでした)')) {
            $updates['body_text'] = $freshBody;
        }

        // body_html: 既存が空で、再抽出に HTML があるなら追加
        $existingHtml = (string) ($existing->body_html ?? '');
        $freshHtml    = (string) ($fresh['body_html'] ?? '');
        if ($existingHtml === '' && $freshHtml !== '') {
            $updates['body_html'] = $freshHtml;
        }

        if (empty($updates)) return false;
        $existing->update($updates);
        return true;
    }

    /**
     * Fetch emails from the server.
     */
    public function fetch(): int
    {
        // 毎回リセット
        $this->perMailErrors   = [];
        $this->connectionError = null;
        $this->backfilledCount = 0;

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
            // imap_folder が空または "*" / "ALL" の場合は「全フォルダ取得」モード。
            // それ以外は指定フォルダ 1 つに絞る (デフォルト INBOX)。
            $rawFolder = trim((string) ($settings->imap_folder ?? ''));
            $folderName = ($rawFolder === '' || strtoupper($rawFolder) === 'ALL' || $rawFolder === '*')
                ? '__ALL__'  // 内部マーカ。後段で全フォルダループになる
                : $rawFolder;
        }

        if (empty($config['host']) || empty($config['username'])) {
            Log::warning('Mail fetch skipped: Host or username not configured.');
            return 0;
        }

        // ===== 接続フェーズ =====
        // webklex/php-imap は POP3 の場合 connect() で認証エラーを投げないことがある
        // (-ERR を握り潰し、後続の getFolders() で空配列を返すだけになる)。
        // そのため connect 直後に「認証が必要な操作」(getFolders) まで呼んで
        // 結果と例外の両方をチェックする。
        try {
            $client = Client::make($config);
            $client->connect();
        } catch (\Throwable $e) {
            $this->connectionError = $e->getMessage();
            Log::error('Mail connection failed (connect): ' . $e->getMessage());
            throw new \RuntimeException($this->humanizeConnectError($e, $protocol));
        }

        try {
            $folders = $client->getFolders();
        } catch (\Throwable $e) {
            // POP3/IMAP 双方で認証失敗時はここで例外。webklex の Auth/Connection/Imap 系を
            // 区別せず取り扱う。
            $this->connectionError = $e->getMessage();
            Log::error('Mail auth/folder list failed: ' . $e->getMessage());
            throw new \RuntimeException($this->humanizeConnectError($e, $protocol));
        }

        // 認証失敗を握り潰されたケースの検知:
        //  - IMAP: 通常 INBOX を含む 1+ 個のフォルダが返るのが正常。0 なら認証/接続疑い
        //  - POP3: フォルダ概念は無いが webklex は INBOX をエミュレートして 1 個返す。0 なら同じ
        $folderCount = is_countable($folders) ? count($folders) : iterator_count($folders);
        if ($folderCount === 0) {
            $msg = $protocol === 'pop3'
                ? 'POP3 サーバに接続しましたが、メールボックスを開けませんでした。ユーザー名 / パスワード / ホストを再確認してください。'
                : 'IMAP サーバに接続しましたが、フォルダ一覧を取得できませんでした。ユーザー名 / パスワードを再確認してください。';
            $this->connectionError = $msg;
            Log::error('Mail fetch: 0 folders returned, suspect auth failure.', [
                'protocol' => $protocol,
                'host'     => $config['host'] ?? '',
                'username' => $config['username'] ?? '',
            ]);
            throw new \RuntimeException($msg);
        }

        $imported = 0;
        // POP3 でも保険として「INBOX を見つけたが messages() で例外」を検知するため
        // ループ内で auth エラーを catch + 再スロー
        $authErrorInLoop = null;

        // ===== 認証プローブ =====
        // webklex は POP3/一部 IMAP サーバ実装で `LOGIN/PASS -ERR/NO` を
        // 握り潰し、`connect() + getFolders()` の段階では例外を投げないことがある。
        // その結果、認証失敗のまま messages() が空配列を返して「fetch 成功 / 0 件」となり、
        // ユーザは「設定が正しい」と誤認する。
        //
        // これを確実に検知するため、メッセージ取得「前」に target folder に対して
        // STATUS / EXAMINE 相当の auth-required 操作を明示的に発火させる。
        //
        // 全フォルダ取得モードの場合は、INBOX (あれば) または最初のフォルダで probe する。
        $isAllFolders = ($folderName === '__ALL__');
        $probeTarget  = null;
        $foldersList  = [];
        foreach ($folders as $f) { $foldersList[] = $f; }

        if ($isAllFolders) {
            // 全フォルダ取得モード: probe は INBOX 優先、無ければ最初のフォルダ
            foreach ($foldersList as $f) {
                if (strcasecmp($f->name ?? '', 'INBOX') === 0 || strcasecmp($f->path ?? '', 'INBOX') === 0) {
                    $probeTarget = $f;
                    break;
                }
            }
            if ($probeTarget === null && !empty($foldersList)) $probeTarget = $foldersList[0];
        } else {
            // 指定フォルダ 1 つ (= POP3 は常にここ。INBOX 等を受け取る)
            foreach ($foldersList as $f) {
                if ($protocol === 'imap'
                    && strcasecmp($f->name ?? '', $folderName) !== 0
                    && strcasecmp($f->path ?? '', $folderName) !== 0) {
                    continue;
                }
                $probeTarget = $f;
                break;
            }
            if ($probeTarget === null) {
                $msg = "指定フォルダ '{$folderName}' が見つかりません ({$protocol})。フォルダ名 / プロトコルを再確認してください。設定で取得フォルダを空欄にすると全フォルダ取得モードになります。";
                $this->connectionError = $msg;
                Log::error('EmailFetcher: target folder not found in folder list', [
                    'protocol'   => $protocol,
                    'folderName' => $folderName,
                    'available'  => array_map(fn($f) => $f->name ?? $f->path ?? '?', $foldersList),
                ]);
                throw new \RuntimeException($msg);
            }
        }
        try {
            if (method_exists($probeTarget, 'examine')) {
                $probeTarget->examine();
            } else {
                $probeTarget->messages()->all()->count();
            }
            Log::info('EmailFetcher: auth probe OK', [
                'protocol' => $protocol,
                'folder'   => $probeTarget->name ?? $probeTarget->path ?? '?',
                'mode'     => $isAllFolders ? 'all_folders' : 'single_folder',
            ]);
        } catch (\Throwable $authProbe) {
            $this->connectionError = $authProbe->getMessage();
            Log::error('EmailFetcher: auth probe failed (likely wrong password)', [
                'protocol' => $protocol,
                'error'    => $authProbe->getMessage(),
            ]);
            throw new \RuntimeException($this->humanizeConnectError($authProbe, $protocol));
        }

        // 1 フォルダあたりの最大取得件数。環境変数で上書き可。デフォルト 500
        $fetchLimit = (int) (env('MAIL_FETCH_LIMIT_PER_FOLDER', 500));
        if ($fetchLimit < 1)   $fetchLimit = 50;
        if ($fetchLimit > 5000) $fetchLimit = 5000;

        // 全フォルダモードで除外するフォルダ名 (Sent / Drafts / Trash / Junk 等)
        // webmail 客户ント の Sent や Trash は二重取り込みになるので避ける。
        // ユーザが「Sent も取り込みたい」場合は明示的にフォルダ名を指定してもらう。
        $excludeIfAll = ['Sent', 'Sent Messages', 'Sent Items', '送信済み', '送信済みメール',
                         'Drafts', '下書き',
                         'Trash', 'Deleted Items', 'ゴミ箱', 'Junk', '迷惑メール', 'Spam',
                         'Outbox', 'Archive'];

        foreach ($foldersList as $folder) {
            // 1) 単一フォルダモード: 指定フォルダだけ通す
            if (!$isAllFolders && $protocol === 'imap'
                && strcasecmp($folder->name ?? '', $folderName) !== 0
                && strcasecmp($folder->path ?? '', $folderName) !== 0) {
                continue;
            }
            // 2) 全フォルダモード: Sent/Trash/Drafts/Junk 系を除外
            if ($isAllFolders) {
                $fname = $folder->name ?? $folder->path ?? '';
                $skip = false;
                foreach ($excludeIfAll as $ex) {
                    if (strcasecmp($fname, $ex) === 0 || stripos($fname, $ex) !== false) { $skip = true; break; }
                }
                if ($skip) {
                    Log::info('EmailFetcher: skip folder in all-folders mode', ['folder' => $fname]);
                    continue;
                }
            }

            try {
                // 新着 (UID 降順) で取得。webklex の MessageQuery は ->setFetchOrder('desc')
                // で UID 降順になり「最新メールから順に取得」できる。
                // method_exists で safety にチェック (古い webklex バージョン考慮)
                $query = $folder->messages()->all();
                if (method_exists($query, 'setFetchOrder')) {
                    $query = $query->setFetchOrder('desc');
                }
                $messages = $query->limit($fetchLimit)->get();
                Log::info('EmailFetcher: messages fetched', [
                    'folder' => $folder->name ?? $folder->path ?? '?',
                    'count'  => is_countable($messages) ? count($messages) : 0,
                    'limit'  => $fetchLimit,
                ]);
            } catch (\Throwable $msgErr) {
                // 認証 / 通信エラーは握り潰さず全体エラーとして上に伝える
                $authErrorInLoop = $msgErr;
                break;
            }

            foreach ($messages as $message) {
                // 1 通単位で try/catch。1 通の取り込み失敗が他のメールを巻き込まないようにする。
                try {
                // 重複検出 (Message-ID)
                // 重要: 後段の Insert では cleanUtf8($messageId, 255) で正規化した値を保存している。
                // ここで raw 値のまま exists() を撃つと、NUL/C0 制御文字を含む ID や 255 文字超過の ID で
                // 「DB には正規化後の値が既に存在するのに exists() が false を返す」ミスマッチが起き、
                // その直後に unique 制約違反 (SQLSTATE 23000) を踏んでログがノイズで埋まる原因になっていた。
                // 重複検査も「DB に入っているのと同じ正規化後の値」で行う。
                $rawMessageId = (string) $message->getMessageId();
                $messageId    = $this->cleanUtf8($rawMessageId, 255);

                // 既存 Email を 1 度だけ取得 (重複判定 + backfill 用)。
                // - 健全な既存メール → 通常の dedup スキップ
                // - 壊れている既存メール (from_address に "@" が無い等) → 下のロジックでフィールドを差分更新
                //   "MAILER-DAEMON" のような不正アドレスが前回保存されていたケースを、再同期で救済する。
                $existing = ($messageId !== '') ? Email::where('message_id', $messageId)->first() : null;
                if ($existing && !$this->emailNeedsBackfill($existing)) {
                    continue;
                }

                // スレッドの解決は「新規メール経路」に入ってから DB トランザクション内で行う。
                // 理由: 旧実装では findOrCreateThread() で新スレッドを先に作ってから
                //  Email::create / handleAttachments が後段で例外を投げると、
                //  スレッドだけが孤児 (Email 行ゼロ) で DB に残り、メール一覧で
                //  「不明な送信者」として表示される原因になっていた。
                //  ここでは「データ抽出」のみ先に済ませ、書き込みはまとめてトランザクション内へ。

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

                // 送信元情報を堅牢に抽出する。
                // - $message->getFrom() は webklex/php-imap の Address[] を返す。
                //   ヘッダが "From: Name <>" のように空ブラケットだと ->mail は空文字列になり、
                //   ?? は null だけしか拾わないので empty string で素通りしてしまっていた。
                // - ヘッダ自体が無いケースも考慮し、Return-Path / Sender ヘッダから補完する。
                $fromArr   = $message->getFrom();
                $fromObj   = (is_array($fromArr) && !empty($fromArr)) ? $fromArr[0] : null;
                $fromMail  = $fromObj && !empty(trim($fromObj->mail ?? '')) ? trim($fromObj->mail) : '';
                $fromName  = $fromObj && !empty(trim($fromObj->personal ?? '')) ? trim($fromObj->personal) : null;

                // 「不正な From アドレス」検出ヘルパ。
                // - "@" を含まない値 (例: "MAILER-DAEMON") は実体としてはメールアドレスではなく
                //   表示名扱いにすべき。
                // - 含んでいても filter_var で valid と判定できないものも同様。
                // webklex のパース結果が "MAILER-DAEMON" のような表示名だけになるケース (バウンス
                // メールで From: MAILER-DAEMON だけ書かれているなど) で、これまで Return-Path /
                // Sender / raw header のフォールバックが走らず "MAILER-DAEMON" が
                // from_address カラムに入ってしまっていた。
                $isInvalidAddr = function (string $v): bool {
                    if ($v === '') return true;
                    if (!str_contains($v, '@')) return true;
                    return !filter_var($v, FILTER_VALIDATE_EMAIL);
                };
                if ($isInvalidAddr($fromMail)) {
                    // 表示名情報として温存 (上書きは「from_name がまだ未設定」のときだけ)
                    if (!$fromName && $fromMail !== '') {
                        $fromName = $fromMail;
                    }
                    $fromMail = '';
                }

                // From アドレスが空 (= 表示名しか無い不正ヘッダを含む) なら Return-Path / Sender を順に試す
                // (各 try/catch 内で出た例外は debug ログだけ残し、本流は続行)
                if ($fromMail === '') {
                    try {
                        $rp = (string) $message->getReturnPath();
                        if ($rp !== '' && preg_match('/<([^>]+)>/', $rp, $m) && filter_var($m[1], FILTER_VALIDATE_EMAIL)) {
                            $fromMail = $m[1];
                        } elseif ($rp !== '' && filter_var(trim($rp, '<> '), FILTER_VALIDATE_EMAIL)) {
                            $fromMail = trim($rp, '<> ');
                        }
                    } catch (\Throwable $e) {
                        Log::debug('EmailFetcher: getReturnPath failed: ' . $e->getMessage());
                    }
                }
                if ($fromMail === '') {
                    try {
                        $senderArr = $message->getSender();
                        $senderMail = (is_array($senderArr) && !empty($senderArr)) ? trim((string) ($senderArr[0]->mail ?? '')) : '';
                        if ($senderMail !== '' && filter_var($senderMail, FILTER_VALIDATE_EMAIL)) {
                            $fromMail = $senderMail;
                            if (!$fromName && !empty(trim($senderArr[0]->personal ?? ''))) {
                                $fromName = trim($senderArr[0]->personal);
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::debug('EmailFetcher: getSender failed: ' . $e->getMessage());
                    }
                }
                // それでも空なら raw From ヘッダから抽出を試みる
                if ($fromMail === '') {
                    try {
                        $rawFrom = (string) $message->getHeader()->get('from');
                        // 1) "<addr@domain>" 形式: 山括弧内が valid email ならそれを採用
                        if ($rawFrom !== '' && preg_match('/<([^>]+)>/', $rawFrom, $m) && filter_var($m[1], FILTER_VALIDATE_EMAIL)) {
                            $fromMail = $m[1];
                        }
                        // 2) 山括弧無しでもヘッダ全体が valid email ならそれを採用
                        elseif ($rawFrom !== '' && filter_var(trim($rawFrom), FILTER_VALIDATE_EMAIL)) {
                            $fromMail = trim($rawFrom);
                        }
                        // 3) "MAILER-DAEMON@host.example.com (Mail Delivery System)" のように
                        //    パーレン手前まで切ったら valid email になるケース
                        elseif ($rawFrom !== '') {
                            $bare = trim(preg_replace('/\s*\(.*\)\s*$/u', '', $rawFrom) ?? $rawFrom);
                            if (filter_var($bare, FILTER_VALIDATE_EMAIL)) {
                                $fromMail = $bare;
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::debug('EmailFetcher: raw From header failed: ' . $e->getMessage());
                    }
                }
                if ($fromMail === '') {
                    $fromMail = 'unknown@example.com';
                    \Log::warning('EmailFetcher: From ヘッダから差出人を抽出できませんでした', [
                        'subject'    => $message->getSubject() ?: '(件名なし)',
                        'message_id' => $messageId,
                    ]);
                }

                // To 抽出: Cc と同じ思想で「Attribute / 配列 / 単独」のどれが来てもループする.
                // 旧実装は is_array() チェックだけで、Webklex\PHPIMAP\Attribute オブジェクトが
                // 返るケースを取りこぼし、To が常に空 (= UI 上 "—" 表記) になる不具合があった.
                $toParts = [];
                $toRaw = $message->getTo();
                if ($toRaw instanceof \Webklex\PHPIMAP\Attribute) {
                    $toRaw = $toRaw->all();
                }
                if (is_iterable($toRaw)) {
                    foreach ($toRaw as $toItem) {
                        if (is_object($toItem) && isset($toItem->mail) && trim((string) $toItem->mail) !== '') {
                            $toParts[] = trim((string) $toItem->mail);
                        }
                    }
                }
                // フォールバック: getTo() が空でも、生ヘッダから "To:" 行を直接拾えるなら使う.
                if (empty($toParts)) {
                    try {
                        $rawHeaderTo = (string) ($message->getHeader()->get('to') ?? '');
                        if ($rawHeaderTo !== '') {
                            if (preg_match_all('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/u', $rawHeaderTo, $m)) {
                                foreach ($m[0] as $addr) {
                                    $toParts[] = $addr;
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::debug('EmailFetcher: raw To header parse failed: ' . $e->getMessage());
                    }
                }
                // 重複除去 (大文字小文字無視) + カンマ結合
                $seen = [];
                $toFinal = [];
                foreach ($toParts as $p) {
                    $k = mb_strtolower($p);
                    if (isset($seen[$k])) continue;
                    $seen[$k] = true;
                    $toFinal[] = $p;
                }
                $toMail = implode(', ', $toFinal);

                // メールの保存
                // body_text / body_html / subject / from_name / cc は受信側のエンコード次第で
                // 不正な UTF-8 バイト列 (PGP暗号化バウンス、ISO-2022-JP 混在、添付バイナリ等) を含む。
                // MySQL の utf8mb4 カラムに入れる前に必ず UTF-8 として valid な文字列に整える。
                $inReplyTo = (string) $message->getInReplyTo();
                // 本文を抽出 → サニタイズ → 「テキストっぽくない」場合は救済処理
                // 1) text/plain を最優先
                // 2) plain がバイナリっぽい場合は HTML から strip_tags
                // 3) どちらもダメなら件名/差出人だけ残した警告文に差し替え
                $rawText = $this->cleanUtf8($message->getTextBody() ?: '');
                $rawHtml = $this->cleanUtf8($message->getHTMLBody() ?: '');

                $finalText = $rawText;
                if ($this->looksLikeBinary($finalText)) {
                    // plain がバイナリっぽい → HTML を strip_tags して再採用
                    $htmlStripped = $rawHtml !== '' ? trim(strip_tags($rawHtml)) : '';
                    if ($htmlStripped !== '' && !$this->looksLikeBinary($htmlStripped)) {
                        $finalText = $htmlStripped;
                    } else {
                        // 両方ともダメ: プレースホルダで置換 (元バイナリは捨てる)
                        $finalText = $this->binaryPlaceholder($message, $fromMail);
                        Log::info('EmailFetcher: 本文がバイナリ判定のためプレースホルダに置換', [
                            'message_id' => $messageId ?: null,
                            'subject'    => $message->getSubject() ?: null,
                        ]);
                    }
                }

                // バイナリ判定済みの HTML/Text。HTML はバイナリならクリア
                $cleanedHtml = $this->looksLikeBinary($rawHtml) ? '' : $rawHtml;
                // ===== 件名 (Subject) のデコード =====
                // Webklex の getSubject() は MIME ヘッダを内部でデコード済み. しかし以下のケースで失敗する:
                //   1. charset 名が "shift-jis" "x-sjis" など PHP の mbstring が直接認識しない別名.
                //   2. =?A?B?...?= の encoded-word が複数連結された長い件名で、間に半角空白が
                //      入っていたり、デコーダが連結を取りこぼして `?` 大量挿入になる.
                //   3. mb_decode_mimeheader の substitute_character がデフォルト 0x3F (`?`) のままで、
                //      解釈不能バイトが全て `?` に置換される ("Re: ????????????" 形のような mojibake).
                // 対策: 複数のデコード経路の結果をスコアリングして最良を採用する.
                //   (a) Webklex getSubject()
                //   (b) raw header + mb_decode_mimeheader (FFFD substitute で破損検知可能に)
                //   (c) raw header + 自前の encoded-word パーサ (charset 別名を正規化 + 複数 charset 試行)
                //   (d) raw header + imap_utf8 (拡張があれば)
                $libSubject  = (string) ($message->getSubject() ?: '');
                $rawSubject  = '';
                try { $rawSubject = (string) ($message->getHeader()->get('subject') ?? ''); } catch (\Throwable) {}
                // Webklex の getSubject()/getHeader()->get('subject') は内部で「先にデコード」してから返してくるため、
                // そこで生バイトが ? に置換されているとリトライしても回復不可能.
                // 唯一の最終手段は raw_header 文字列 (受信時の全 RFC822 ヘッダ生バイト) から
                // Subject: 行を取り出し、本物のバイトを我々の堅牢デコーダに通すこと.
                $trueRawSubject = '';
                try {
                    $rawAll = (string) ($message->getHeader()->raw ?? '');
                    if ($rawAll !== '') {
                        // RFC822 の continuation (CRLF SP / TAB) を 1 行に折り畳んでから Subject: を抽出.
                        $unfolded = preg_replace('/\r?\n[ \t]+/', ' ', $rawAll);
                        if (preg_match('/^Subject:[ \t]*(.*)$/im', $unfolded, $m)) {
                            $trueRawSubject = trim($m[1]);
                        }
                    }
                } catch (\Throwable) {}
                $bestSubject      = $libSubject;
                $bestSubjectScore = $libSubject !== '' ? $this->scoreJapaneseQuality($libSubject) : PHP_INT_MIN;

                // 候補を集めて最良を選ぶ.
                // 「raw_header から取り直したオリジナルバイト」を最優先で扱う (Webklex のデコード前の状態).
                $candidates = array_filter([$trueRawSubject, $rawSubject]);
                foreach ($candidates as $src) {
                    // (b) mb_decode_mimeheader + FFFD substitute. 失敗は U+FFFD で表現させてスコア低下.
                    try {
                        $prevEnc = mb_internal_encoding();
                        $prevSubst = mb_substitute_character();
                        mb_internal_encoding('UTF-8');
                        mb_substitute_character(0xFFFD);
                        try {
                            $cand = @mb_decode_mimeheader($src);
                        } finally {
                            mb_substitute_character($prevSubst);
                            mb_internal_encoding($prevEnc);
                        }
                        if (is_string($cand) && $cand !== '') {
                            $cand = $this->cleanUtf8($cand, 255);
                            $sc = $this->scoreJapaneseQuality($cand);
                            if ($sc > $bestSubjectScore) { $bestSubjectScore = $sc; $bestSubject = $cand; }
                        }
                    } catch (\Throwable $e) {
                        Log::debug('EmailFetcher: mb_decode_mimeheader failed: ' . $e->getMessage());
                    }
                    // (c) 自前デコーダ (charset 正規化 + 失敗時に他 charset で再試行)
                    try {
                        $cand = $this->decodeMimeHeaderRobust($src);
                        if ($cand !== '') {
                            $cand = $this->cleanUtf8($cand, 255);
                            $sc = $this->scoreJapaneseQuality($cand);
                            if ($sc > $bestSubjectScore) { $bestSubjectScore = $sc; $bestSubject = $cand; }
                        }
                    } catch (\Throwable $e) {
                        Log::debug('EmailFetcher: decodeMimeHeaderRobust failed: ' . $e->getMessage());
                    }
                    // (d) imap_utf8 (IMAP 拡張が有効ならフォールバックとして使う)
                    if (function_exists('imap_utf8')) {
                        try {
                            $cand = @imap_utf8($src);
                            if (is_string($cand) && $cand !== '') {
                                $cand = $this->cleanUtf8($cand, 255);
                                $sc = $this->scoreJapaneseQuality($cand);
                                if ($sc > $bestSubjectScore) { $bestSubjectScore = $sc; $bestSubject = $cand; }
                            }
                        } catch (\Throwable) {}
                    }
                }
                $subjectClean = $this->cleanUtf8($bestSubject !== '' ? $bestSubject : '(件名なし)', 255);

                // ===== 既存メールの backfill 経路 =====
                // ループ冒頭で「既存だが壊れている」と判定されたメールはここで部分更新する。
                // 新規 Insert (Email::create) は走らせず、添付やスレッド状態も触らない
                //  (既に取り込み済みのため二重登録になる)。
                // 単一行 UPDATE なのでトランザクションは不要。
                if ($existing) {
                    $cleanedFromMail = $this->cleanUtf8($fromMail, 255);
                    // 件名救済: 再抽出した $subjectClean も lossy なら、既存メールが所属する
                    // スレッドの subject (= スレッド先頭メールの decode 済み件名) から復元を試みる.
                    // これにより、過去の取り込み時に ? 置換のまま保存された subject も
                    // 次回 fetch で自動的に修復される.
                    $existingThread = $existing->thread;
                    $subjectForBackfill = $this->inheritSubjectFromThread($subjectClean, $existingThread);
                    $changed = $this->backfillExistingEmail($existing, [
                        'from_address' => $cleanedFromMail,
                        'from_name'    => $fromName !== null ? $this->cleanUtf8($fromName, 255) : null,
                        'subject'      => $subjectForBackfill,
                        'body_text'    => $finalText,
                        'body_html'    => $cleanedHtml,
                    ]);
                    if ($changed) {
                        $this->backfilledCount++;
                        Log::info('EmailFetcher: backfilled existing email (壊れた既存データを再パースで救済)', [
                            'email_id'   => $existing->id,
                            'message_id' => $messageId ?: null,
                            'subject'    => $existing->subject,
                        ]);
                    }
                    continue;
                }

                // ===== 新規メールの保存経路 (DB トランザクション内) =====
                // 「スレッド作成 → Email 作成 → 添付保存 → スレッド更新」を1つのアトミック単位にする。
                // 途中で例外が出れば BeginTransaction 以降のすべてがロールバックされるので、
                // 「Email が無いスレッドだけが DB に残る (= 後から『不明な送信者』表示の原因になる)」
                // 状態は構造的に発生しなくなる。
                // VARCHAR(255) カラムは 255 文字で切る。 longtext は 60,000 文字 (TEXT 上限) で切る
                // message_id はループ冒頭で既に cleanUtf8($rawMessageId, 255) 済み。重複検査と
                // 同じ値を保存することで「検査は素通り → Insert で 23000」のミスマッチを防ぐ。
                DB::transaction(function () use (
                    $message, $messageId, $receivedAt, $cc,
                    $fromMail, $fromName, $toMail, $inReplyTo,
                    $finalText, $cleanedHtml, $subjectClean
                ) {
                    // スレッド解決 (新規生成 or 既存 hit)
                    $thread = $this->findOrCreateThread($message);

                    // 件名の救済: 受信した raw バイトが上流 MTA で `?` に置換されていた場合、
                    // いかなる decoder でもバイトレベルから復元はできない. ただしチケット番号や
                    // In-Reply-To 経由で既存スレッドにヒットしていれば、スレッド側の subject (= 最初の
                    // メールの decode 済み件名) を継承することで一覧 / 詳細ヘッダの ??? 表示を抑止できる.
                    // ※ 以降の spam 判定や Email::create はこの $subjectClean を参照するので、ここで上書きする.
                    $subjectClean = $this->inheritSubjectFromThread($subjectClean, $thread);

                    // 迷惑メール判定 (ルール一致なら thread.status を spam にする).
                    // 宛先系ルール (recipient_address / recipient_domain / recipient_contains) のため
                    // To / Cc / Bcc も渡す. Bcc はメールヘッダから取得できないことが多いが空文字を渡すだけで OK.
                    if ($this->isSpamByRules($fromMail, $subjectClean, $finalText, $cleanedHtml, $toMail, $cc, '')) {
                        if (($thread->status ?? '') !== EmailThread::STATUS_SPAM) {
                            // status='spam' に切り替える時は spammed_at = now() を同時セット.
                            // mail:purge-spam がこの値を起点に保持期間を判定する.
                            $thread->update([
                                'status'     => EmailThread::STATUS_SPAM,
                                'spammed_at' => now(),
                            ]);
                        }
                    }

                    $email = Email::create([
                        'thread_id'    => $thread->id,
                        'message_id'   => $messageId !== '' ? $messageId : null,
                        'in_reply_to'  => $this->cleanUtf8($inReplyTo, 255) ?: null,
                        'subject'      => $subjectClean,
                        'from_address' => $this->cleanUtf8($fromMail, 255),
                        'from_name'    => $fromName !== null ? $this->cleanUtf8($fromName, 255) : null,
                        'to_address'   => $this->cleanUtf8($toMail, 255),
                        'cc'           => $cc !== '' ? $this->cleanUtf8($cc, 255) : null,
                        'body_text'    => $finalText,
                        'body_html'    => $cleanedHtml,
                        'received_at'  => $receivedAt,
                    ]);

                    // 添付ファイル保存 (例外が出ればトランザクション全体がロールバック)
                    $this->handleAttachments($message, $email);

                    // スレッドの最終更新日時を更新
                    $thread->update(['last_email_at' => $receivedAt]);

                    // 保留・完了タグを削除 (新着到着でこれらのタグは陳腐化するため)
                    $tags = $thread->tags ?? [];
                    $newTags = array_values(array_filter($tags, fn($t) => !in_array($t, ['保留', '完了'])));
                    if (count($tags) !== count($newTags)) {
                        $thread->update(['tags' => $newTags]);
                    }

                    // ★ ステータスの自動変更は行わない (新仕様).
                    //
                    //   旧仕様: 「新着が届いたら completed/hold/no_action を inbox に戻す」.
                    //          意図は「対応漏れを防ぐためバッジに出す」だったが、
                    //          ML やシステム通知のように毎日新着が来るスレッドだと
                    //          「完了 → 翌日 inbox に戻る → 完了 → ...」の無限ループになり
                    //          ユーザが手動で完了し続ける羽目になっていた.
                    //
                    //   新仕様: ユーザが付けたステータスは sticky にする. 新着メールは普通に
                    //          スレッドに追加されるだけで、status は触らない.
                    //          バッジに復活させたい場合はユーザが手動で inbox / hold に戻す.
                    //          (新着到着は last_email_at の更新と email 行追加で表現される.
                    //           未読件数で気付けるので、バッジ自体を切り替える必要は無い.)
                });

                $imported++;
                } catch (\Throwable $perMailError) {
                    // 1 通の失敗で全体を止めない。詳細はログ + 蓄積 (UI 表示用) して次のメールに進む。

                    // (a) Message-ID 重複は「既に取り込み済み」と同義 → サイレントスキップ。
                    //     exists() のチェックを通り抜けてしまう経路:
                    //       - 並行 fetch で別プロセスが先に Insert した (race)
                    //       - in_reply_to など他 unique 制約 (将来追加された場合) の衝突
                    //     どちらも UI に出すべきエラーではないので perMailErrors には積まない。
                    $rawErr = $perMailError->getMessage();
                    $isDuplicateMessageId = (
                        $perMailError instanceof \Illuminate\Database\QueryException
                        && ((string) $perMailError->getCode()) === '23000'
                        && stripos($rawErr, 'Duplicate entry') !== false
                        && stripos($rawErr, 'emails_message_id_unique') !== false
                    );
                    if ($isDuplicateMessageId) {
                        Log::debug('EmailFetcher: duplicate message_id at insert (race or pre-existing), silently skipped', [
                            'message_id' => $messageId ?? null,
                        ]);
                        continue;
                    }

                    // (b) その他のエラー: メタ情報を集めて UI / ログへ通知してから次へ進む。
                    // メタ情報の getter で更に例外が出てもログに残す (二重サイレントを避ける)。
                    $subject = null;
                    try { $subject = $message->getSubject(); }
                    catch (\Throwable $e) { Log::debug('EmailFetcher: per-mail getSubject failed: ' . $e->getMessage()); }
                    $fromAddr = null;
                    try {
                        $arr = $message->getFrom();
                        if (is_array($arr) && !empty($arr) && isset($arr[0]->mail)) $fromAddr = (string) $arr[0]->mail;
                    } catch (\Throwable $e) { Log::debug('EmailFetcher: per-mail getFrom failed: ' . $e->getMessage()); }

                    $this->perMailErrors[] = [
                        'message_id' => $messageId ?? null,
                        'subject'    => $subject,
                        'from'       => $fromAddr,
                        'error'      => $this->humanizePerMailError($perMailError),
                    ];

                    Log::warning('EmailFetcher: 1 通の取り込みに失敗したのでスキップしました', [
                        'message_id' => $messageId ?? null,
                        'subject'    => $subject,
                        'from'       => $fromAddr,
                        'error'      => $perMailError->getMessage(),
                    ]);
                    continue;
                }
            }
        }

        if ($authErrorInLoop) {
            // ループ中に認証/通信エラーが出ていたなら、ここで全体エラーとして投げる
            $this->connectionError = $authErrorInLoop->getMessage();
            Log::error('Mail fetch: error during message fetch loop', [
                'error' => $authErrorInLoop->getMessage(),
            ]);
            throw new \RuntimeException($this->humanizeConnectError($authErrorInLoop, $protocol));
        }

        return $imported;
    }

    /**
     * webklex/php-imap 由来の例外メッセージを人間向けに翻訳する。
     */
    protected function humanizeConnectError(\Throwable $e, string $protocol): string
    {
        $msg = $e->getMessage();
        $cls = class_basename(get_class($e));
        $low = strtolower($msg);

        // 認証失敗 (webklex の AuthFailedException / Imap NO/BAD 等)
        if (
            str_contains($cls, 'AuthFailed') ||
            str_contains($low, 'authentication failed') ||
            str_contains($low, 'login failed') ||
            str_contains($low, 'bad user') ||
            str_contains($low, 'invalid credentials') ||
            str_contains($low, 'logon failure') ||
            str_contains($low, '-err') ||                 // POP3 -ERR
            str_contains($low, 'incorrect password') ||
            str_contains($low, 'wrong password') ||
            preg_match('/imap.*(?:no|bad).*login/i', $msg)
        ) {
            return 'メールサーバーの認証に失敗しました。ユーザー名 / パスワードを再確認してください。'
                . " ({$protocol}: {$msg})";
        }

        // 接続不能 / DNS / タイムアウト
        if (
            str_contains($low, 'could not resolve') ||
            str_contains($low, 'getaddrinfo') ||
            str_contains($low, 'connection refused') ||
            str_contains($low, 'connection reset') ||
            str_contains($low, 'no route to host') ||
            str_contains($low, 'timed out') ||
            str_contains($low, 'operation timed out') ||
            str_contains($low, 'network is unreachable')
        ) {
            return 'メールサーバーに接続できません。ホスト名 / ポート / ネットワーク到達性を再確認してください。'
                . " ({$protocol}: {$msg})";
        }

        // SSL/TLS エラー
        if (
            str_contains($low, 'ssl') ||
            str_contains($low, 'tls') ||
            str_contains($low, 'certificate') ||
            str_contains($low, 'starttls')
        ) {
            return 'SSL/TLS のハンドシェイクに失敗しました。暗号化 (SSL/TLS/none) とポート (993/995/143/110) の組合せが正しいか確認してください。'
                . " ({$protocol}: {$msg})";
        }

        // それ以外
        return 'メールサーバーに接続できませんでした: ' . $msg;
    }

    /**
     * 任意の入力を MySQL utf8mb4 カラムに安全に入れられる文字列に整える。
     *
     * 処理:
     * 1. null / 空 はそのまま空文字へ
     * 2. iso-2022-jp / shift_jis / euc-jp など日本語エンコーディングの可能性を mb_detect_encoding で検出 → utf-8 に変換
     * 3. それでも valid UTF-8 で無いバイト列が残る場合は不正バイトを除去
     * 4. NUL バイトと制御文字 (タブ/改行は残す) を除去
     * 5. 指定の最大文字数で切り詰め (TEXT / VARCHAR(255) いずれにも適用可能)
     *
     * @param int $maxLen 最大文字数 (デフォルト 60000 = TEXT 用)
     */
    protected function cleanUtf8(?string $value, int $maxLen = 60000): string
    {
        if ($value === null || $value === '') return '';

        // (1) ISO-2022-JP の早期判定. 件名や本文が `ESC $ B ... ESC ( B` のシーケンスを
        //     持っていれば確実に ISO-2022-JP. mb_detect_encoding は ASCII を先に拾って
        //     しまうケースがあり (ISO-2022-JP は ASCII 互換), その場合 (4) の制御文字
        //     除去で ESC (0x1B) が消えて `$B!Z%5!<%S%9 ...` のような mojibake が残っていた.
        //     ここで明示的に ISO-2022-JP として UTF-8 へ変換する.
        if (strpos($value, "\x1B") !== false
            && preg_match('/\x1B[$(][@B]/', $value)) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'ISO-2022-JP');
            if ($converted !== false && $converted !== null && $converted !== '') {
                $value = $converted;
            }
        }

        // (2) エンコーディング推測 + 変換 (上で ISO-2022-JP として変換できなかった場合のフォールバック)
        //
        // 既知の罠: mb_detect_encoding は同じバイト列が複数のエンコーディングで valid な場合、
        //   候補リストの先頭から「strict 通過した最初のもの」を返す.
        //   例えば「基幹システム」の UTF-8 バイト (E59FBAE5B9B9...) は Shift_JIS としても valid で
        //   "蝓ｺ蟷ｹ繧ｷ繧ｹ繝�繝�" と解釈できてしまう.
        //   候補リスト先頭が UTF-8 なので普通は UTF-8 が勝つが、bytes が UTF-8 として 1 文字でも
        //   invalid だと SJIS や EUC-JP に流れて mojibake (e.g. "蝓‖臻麺") が発生する.
        //
        // 対策: 候補エンコーディングそれぞれで UTF-8 に変換し、変換後の文字列の「日本語らしさ
        //   スコア」を比較して最も自然な結果を採用する. スコアは以下の合計:
        //     +2 : 常用ひらがな / カタカナ (U+3040-30FF)
        //     +2 : 基本 CJK 漢字のうち JIS X 0208 範囲 (U+4E00-9FFF の中でも頻出域)
        //     +1 : ASCII 印字可能
        //     -3 : "rare" CJK / 互換漢字 (U+2E80-2FDF, U+3400-4DBF, U+F900-FAFF など) ← mojibake の特徴
        //     -2 : 私用領域 / 制御文字 / U+FFFD
        if (mb_check_encoding($value, 'UTF-8')) {
            // 既に valid UTF-8 ならまず元の値をそのまま採用する (最も信頼できる).
            // ただし mojibake スコアが極端に悪い場合は他候補も試す.
            $bestScore = $this->scoreJapaneseQuality($value);
            $best      = $value;
            // valid UTF-8 でも mojibake っぽい (スコアが負) の場合だけ別解釈を試す.
            if ($bestScore < 0) {
                $tries = $this->tryReinterpretBytes($value);
                foreach ($tries as [$candidate, $label]) {
                    $sc = $this->scoreJapaneseQuality($candidate);
                    // 大幅に改善する場合のみ採用 (誤検出によるさらなる悪化を避ける)
                    if ($sc > $bestScore + 5) {
                        $bestScore = $sc;
                        $best = $candidate;
                    }
                }
            }
            $value = $best;
        } else {
            // 非 UTF-8 バイト列: 候補ごとに変換してスコア比較し最良を採用.
            // 重要: mb_convert_encoding は変換できないバイトを mb_substitute_character の値
            //   (デフォルト 0x3F = '?') で置換するため、誤エンコーディング指定では `?` だらけの
            //   高スコア偽結果が出る. これを検出するため一時的に substitute を U+FFFD に変更し、
            //   結果から U+FFFD カウントを減点する.
            $prevSubst = mb_substitute_character();
            mb_substitute_character(0xFFFD);
            try {
                $best = $value;
                $bestScore = PHP_INT_MIN;
                $candidates = ['UTF-8', 'ISO-2022-JP', 'ISO-2022-JP-MS', 'SJIS-win', 'CP932', 'SJIS', 'EUC-JP', 'eucJP-win'];
                foreach ($candidates as $enc) {
                    $converted = @mb_convert_encoding($value, 'UTF-8', $enc);
                    if ($converted === false || $converted === null) continue;
                    if (!@mb_check_encoding($converted, 'UTF-8')) continue;
                    $sc = $this->scoreJapaneseQuality($converted);
                    if ($sc > $bestScore) {
                        $bestScore = $sc;
                        $best = $converted;
                    }
                }
                $value = $best;
            } finally {
                mb_substitute_character($prevSubst);
            }
        }

        // (3) 残った不正バイトを除去
        if (!mb_check_encoding($value, 'UTF-8')) {
            $prev = mb_substitute_character();
            mb_substitute_character('none');
            $cleaned = @mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            mb_substitute_character($prev);
            $value = $cleaned !== false && $cleaned !== null ? $cleaned : '';
        }

        // (4) NUL + C0 制御文字 (\t \n \r 以外) を削除
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;

        // (5) 最大文字数で切り詰め
        if (mb_strlen($value, 'UTF-8') > $maxLen) {
            $value = mb_substr($value, 0, $maxLen, 'UTF-8');
        }

        return $value;
    }

    /**
     * RFC 2047 の encoded-word を自前でパースして UTF-8 化する堅牢デコーダ.
     *
     * 標準の mb_decode_mimeheader が落ちる代表ケース:
     *   - charset 名が "shift-jis" "x-sjis" 等のエイリアスで mbstring が認識せず substitute='?' になる
     *   - 連続する encoded-word (例 =?A?B?...?= =?A?B?...?=) を結合する時に内部状態を取りこぼす
     *   - encoded-word 内に余計な空白が入った非適合実装
     *
     * このデコーダは以下を行う:
     *   1. =?charset?encoding?text?= をすべて抽出 (連続する場合は隙間の空白を除去して結合)
     *   2. charset 名を正規化 (shift-jis → SHIFT_JIS, x-sjis → CP932 など)
     *   3. encoding が B なら base64_decode, Q なら quoted_printable_decode
     *   4. mb_convert_encoding($bytes, 'UTF-8', $normalizedCharset) で UTF-8 化
     *      失敗したら CP932 / SJIS / EUC-JP / ISO-2022-JP の順で再試行し、スコア最良を採用
     *   5. encoded-word でない部分はそのまま (ASCII 想定だが UTF-8 でも素通し)
     */
    protected function decodeMimeHeaderRobust(string $raw): string
    {
        if ($raw === '') return '';
        // RFC 2047: 隣接する encoded-word の間の空白は無視する.
        // ステップ 1: encoded-word を空白を介して連続させているケースを先に潰す.
        $raw = preg_replace('/\?=[\r\n\s]+=\?/u', '?==?', $raw) ?? $raw;

        // ステップ 2: 各 encoded-word をパースして UTF-8 へ.
        $out = preg_replace_callback(
            '/=\?([A-Za-z0-9_\-\.:]+)\?([BbQq])\?([^?]*)\?=/',
            function (array $m): string {
                $charset = $this->normalizeMimeCharset($m[1]);
                $enc     = strtoupper($m[2]);
                $payload = $m[3];
                $decoded = ($enc === 'B')
                    ? (base64_decode($payload, false) ?: '')
                    : quoted_printable_decode(strtr($payload, ['_' => ' '])); // Q encoding は _ = space

                if ($decoded === '') return '';
                return $this->bestUtf8Decode($decoded, $charset);
            },
            $raw
        );
        return is_string($out) ? $out : $raw;
    }

    /**
     * MIME ヘッダの charset 名を mbstring が認識する正規名に変換.
     */
    protected function normalizeMimeCharset(string $cs): string
    {
        $cs = strtolower(trim($cs));
        // 代表的なエイリアス → mbstring 正規名
        $alias = [
            'shift-jis'      => 'SJIS-win',
            'shift_jis'      => 'SJIS-win',
            'shiftjis'       => 'SJIS-win',
            'sjis'           => 'SJIS-win',
            'x-sjis'         => 'CP932',
            'x-sjis-jp'      => 'CP932',
            'windows-31j'    => 'CP932',
            'windows-932'    => 'CP932',
            'ms_kanji'       => 'CP932',
            'ms-kanji'       => 'CP932',
            'cp932'          => 'CP932',
            'csshiftjis'     => 'SJIS-win',
            'csshift_jis'    => 'SJIS-win',
            'iso-2022-jp'    => 'ISO-2022-JP',
            'iso2022-jp'     => 'ISO-2022-JP',
            'iso-2022-jp-2'  => 'ISO-2022-JP-MS',
            'iso-2022-jp-ms' => 'ISO-2022-JP-MS',
            'csiso2022jp'    => 'ISO-2022-JP',
            'euc-jp'         => 'eucJP-win',
            'euc_jp'         => 'eucJP-win',
            'eucjp'          => 'eucJP-win',
            'x-euc-jp'       => 'eucJP-win',
            'cseucpkdfmtjapanese' => 'eucJP-win',
            'utf-8'          => 'UTF-8',
            'utf8'           => 'UTF-8',
            'us-ascii'       => 'ASCII',
            'ascii'          => 'ASCII',
        ];
        return $alias[$cs] ?? strtoupper($cs);
    }

    /**
     * バイト列を UTF-8 へ変換. 指定 charset で失敗 (= 代替文字 0xFFFD だらけ) なら
     * 別の日本語 charset で再試行し、scoreJapaneseQuality が最も高い結果を返す.
     */
    protected function bestUtf8Decode(string $bytes, string $primaryCharset): string
    {
        $prevSubst = mb_substitute_character();
        mb_substitute_character(0xFFFD);
        try {
            $candidates = array_unique([
                $primaryCharset,
                'CP932', 'SJIS-win', 'SJIS', 'eucJP-win', 'EUC-JP',
                'ISO-2022-JP-MS', 'ISO-2022-JP', 'UTF-8',
            ]);
            $best = '';
            $bestScore = PHP_INT_MIN;
            foreach ($candidates as $cs) {
                $converted = @mb_convert_encoding($bytes, 'UTF-8', $cs);
                if ($converted === false || $converted === '' || $converted === null) continue;
                if (!@mb_check_encoding($converted, 'UTF-8')) continue;
                $sc = $this->scoreJapaneseQuality($converted);
                // JIS シフトイン/アウト残骸 ($B, (B, $@) は文字化け強指標. 大量ペナルティ.
                //   $B → 漢字シフトイン   $@ → 旧 JIS シフトイン   (B → ASCII 復帰
                // ASCII 文字数で稼ぐ「実は壊れた JIS」結果を弾く.
                $jisLeak = preg_match_all('/\$[B@]|\([BJ]/', $converted, $junk);
                if ($jisLeak > 0) $sc -= $jisLeak * 8;
                // ESC 残骸 (バイナリ的にも危険)
                if (strpos($converted, "\x1B") !== false) $sc -= 20;
                // U+FFFD (substitute character) の数で減点
                $fffd = mb_substr_count($converted, "\u{FFFD}", 'UTF-8');
                if ($fffd > 0) $sc -= $fffd * 3;
                if ($sc > $bestScore) { $bestScore = $sc; $best = $converted; }
            }
            return $best !== '' ? $best : '';
        } finally {
            mb_substitute_character($prevSubst);
        }
    }

    /**
     * 「日本語テキストとしての自然さ」を 0 基準でスコアリングする.
     * cleanUtf8() のエンコーディング選択ヒューリスティックに使う.
     *
     * スコアリング基準:
     *   +2 : ひらがな (U+3040-309F) / カタカナ (U+30A0-30FF)
     *   +1 : 常用漢字域 (U+4E00-9FFF), CJK 記号 (U+3000-303F), 全角英数 (U+FF00-FFEF)
     *   +1 : ASCII 印字可能
     *   -3 : "rare" 漢字域 (U+3400-4DBF CJK Ext A, U+F900-FAFF 互換, U+20000+ Ext B+) ← mojibake の特徴
     *   -3 : U+FFFD (置換文字), 私用領域 (U+E000-F8FF)
     *   -1 : 制御文字 (タブ/改行以外)
     *
     * @return int 高いほど「読みやすい日本語」
     */
    protected function scoreJapaneseQuality(string $text): int
    {
        if ($text === '') return 0;
        $len = mb_strlen($text, 'UTF-8');
        if ($len === 0) return 0;
        // 4000 文字超は先頭サンプルでスコア (パフォーマンス対策)
        $sample = $len > 4000 ? mb_substr($text, 0, 4000, 'UTF-8') : $text;
        $sampleLen = mb_strlen($sample, 'UTF-8');
        $score = 0;
        for ($i = 0; $i < $sampleLen; $i++) {
            $ch = mb_substr($sample, $i, 1, 'UTF-8');
            $code = mb_ord($ch, 'UTF-8');
            if ($code === false) { $score -= 3; continue; }
            // ASCII 印字
            if ($code >= 0x20 && $code <= 0x7E) { $score += 1; continue; }
            // タブ / 改行は中立
            if ($code === 0x09 || $code === 0x0A || $code === 0x0D) { continue; }
            // 他の制御文字
            if ($code < 0x20 || $code === 0x7F) { $score -= 1; continue; }
            // U+FFFD 置換文字
            if ($code === 0xFFFD) { $score -= 3; continue; }
            // ひらがな / カタカナ
            if (($code >= 0x3040 && $code <= 0x309F) || ($code >= 0x30A0 && $code <= 0x30FF)) {
                $score += 2; continue;
            }
            // CJK 記号と全角句読点
            if ($code >= 0x3000 && $code <= 0x303F) { $score += 1; continue; }
            // 全角英数 / 半角カナ
            if ($code >= 0xFF00 && $code <= 0xFFEF) { $score += 1; continue; }
            // 常用 CJK (U+4E00-9FFF) — ただし mojibake の頻出文字 (蝓 U+8753, 臻 U+81FB, 麺 U+9EBA など)
            //   を含むので、希少漢字判定を内部に持たせる.
            if ($code >= 0x4E00 && $code <= 0x9FFF) {
                $score += $this->isRareKanji($code) ? -3 : 1;
                continue;
            }
            // CJK Ext A — JIS X 0208 にない. mojibake 強指標.
            if ($code >= 0x3400 && $code <= 0x4DBF) { $score -= 3; continue; }
            // CJK 互換漢字 — 同上
            if ($code >= 0xF900 && $code <= 0xFAFF) { $score -= 3; continue; }
            // CJK Ext B-G (SMP)
            if ($code >= 0x20000 && $code <= 0x3FFFF) { $score -= 3; continue; }
            // 私用領域
            if (($code >= 0xE000 && $code <= 0xF8FF) || ($code >= 0xF0000 && $code <= 0x10FFFF)) {
                $score -= 3; continue;
            }
            // Latin 拡張, ラテン系 → ノーマル
            if ($code >= 0x00A0 && $code <= 0x024F) { $score += 0; continue; }
            // 絵文字 / 記号類 → 中立
            // それ以外: 軽くマイナス (西洋以外のスクリプトが業務メールに多く出るのは不自然)
            $score -= 0;
        }
        return $score;
    }

    /**
     * 「受信時に件名が ? や U+FFFD で置換されて欠落している」状態の検出.
     *
     * scoreJapaneseQuality だけだと、上流 MTA で日本語バイトが `?` に置換された結果
     *   "Re: ?????????????????????"
     * のような件名でも「ASCII 印字可能=+1」が大量に積まれて健全件名と区別がつかない.
     * その結果、新着メールの subject が ? 置換のまま DB に保存されてしまう.
     *
     * 本メソッドは「Re:/Fwd: と [#TICKET-N] を剥がした本文」を見て、
     *   - 空文字
     *   - U+FFFD を含む
     *   - 半分以上が '?' で占められている
     *   - 3 個以上連続した '?' を含む
     * のいずれかに当てはまれば true を返す. true なら呼び出し側で
     * inheritSubjectFromThread() による復元を試みる.
     */
    protected function subjectLooksLossy(string $subject): bool
    {
        $s = trim($subject);
        if ($s === '' || $s === '(件名なし)') return true;

        // Re:/Fwd:/Fw: と [#TICKET-NNN] / [#RICE-NNN] を剥がした「件名本文」を判定対象にする.
        // 全角コロン ":" にも対応 ("(?::|：)").
        $body = preg_replace('/^(\s*(?:re|fwd|fw)\s*(?::|：)\s*)+/iu', '', $s) ?? $s;
        $body = preg_replace(EmailThread::TICKET_REGEX, '', $body) ?? $body;
        $body = trim($body);
        if ($body === '') return true;

        // U+FFFD (デコード失敗の置換文字) を 1 個でも含めば壊れ確定.
        if (mb_strpos($body, "\u{FFFD}") !== false) return true;

        // '?' が本文の半分以上を占めれば置換アーティファクトの可能性大.
        //   "Re: ??????????" → 本文 "??????????" = 100% ? → lossy.
        //   "Re: 何??"        → 本文 "何??" = 67% ? → lossy 扱い (短い件名は誤検出されうるが、
        //                        thread.subject に健全なものがあれば置き換えても問題は小さい).
        $qmark = mb_substr_count($body, '?', 'UTF-8');
        $len   = mb_strlen($body, 'UTF-8');
        if ($len > 0 && $qmark * 2 >= $len) return true;

        // 3 個以上の連続した '?' は MTA / クライアントの置換シーケンスとしてほぼ確実.
        //   "Re: 山田です???よろしく" のような単発混入は許容する.
        if (preg_match('/\?{3,}/u', $body)) return true;

        return false;
    }

    /**
     * email.subject が ? 置換などで lossy になっているとき、スレッド側の健全な subject から
     * Re:/Fwd: プレフィックスを保ったまま復元する.
     *
     * 復元条件:
     *   - $current が subjectLooksLossy() で lossy 判定
     *   - $thread が非 null かつ thread.subject が非 lossy
     * いずれかを満たさない場合は $current をそのまま返す (= 副作用なし).
     *
     * 例:
     *   $current = "Re: [#RICE-123456] ?????????????????????"
     *   $thread->subject = "お問い合わせの件"
     *   戻り値 = "Re: [#RICE-123456] お問い合わせの件"
     */
    protected function inheritSubjectFromThread(string $current, ?EmailThread $thread): string
    {
        if (!$this->subjectLooksLossy($current)) return $current;
        if (!$thread) return $current;
        $threadSubj = trim((string) ($thread->subject ?? ''));
        if ($threadSubj === '' || $this->subjectLooksLossy($threadSubj)) return $current;

        // 元の件名から Re:/Fwd: プレフィックスと [#TICKET-NNN] マーカを保存して付け直す.
        // 「Re: + チケット + 本文」の構造はそのままに、本文部分だけ thread.subject に差し替えるイメージ.
        $rePrefix = '';
        if (preg_match('/^((?:\s*(?:re|fwd|fw)\s*(?::|：)\s*)+)/iu', $current, $m)) {
            $rePrefix = $m[1];
        }
        $ticketTag = '';
        if (preg_match(EmailThread::TICKET_REGEX, $current, $m)) {
            $ticketTag = $m[0] . ' ';
        }
        $rebuilt = $rePrefix . $ticketTag . $threadSubj;
        // VARCHAR(255) を超えないよう切り詰め.
        if (mb_strlen($rebuilt, 'UTF-8') > 255) {
            $rebuilt = mb_substr($rebuilt, 0, 255, 'UTF-8');
        }
        Log::info('EmailFetcher: subject inherited from thread (受信時に件名バイトが ? に置換されていたため復元)', [
            'thread_id'      => $thread->id,
            'original_lossy' => $current,
            'restored'       => $rebuilt,
        ]);
        return $rebuilt;
    }

    /**
     * 「希少漢字」(JIS X 0208 にない or 業務メールにほぼ出ない) かどうかの簡易判定.
     * mojibake "蝓‖臻麺" の検出に使う. 完全網羅ではなく、頻出 mojibake 字種を弾く軽量チェック.
     */
    protected function isRareKanji(int $code): bool
    {
        // JIS X 0208 「第一水準」「第二水準」に含まれない範囲をざっくり弾く.
        // 業務メールに登場する可能性が極めて低い文字種を「rare」とみなす.
        // 代表的な mojibake 字: 蝓 0x8753, 臻 0x81FB, 麺 0x9EBA, 繧 0x7E67, 繝 0x7E7D など.
        // ヒューリスティックなので過剰判定上等 — 候補比較に使うだけで、削除はしない.
        // SJIS 由来 UTF-8 mojibake で頻出する Unicode codepoint を列挙.
        static $rare = null;
        if ($rare === null) {
            // よく出る mojibake 文字 (蝟蝓蟷蟹蟆繧繝繁繝逅邂遙莉莅蕗蕪薨薹蘿蛻蜍髀ｴ... etc).
            // ここでは「業務メールでまず使われない文字」のうち、SJIS→UTF-8 mojibake で
            // 頻出するレンジを概ねカバーする.
            $rare = [];
            foreach ([
                [0x8000, 0x88FF], // 蕪 蛻 蜍 繧 蝟 蝓 蟇 など多数
                [0x9C00, 0x9FFF], // 鬣 鯱 鱸 鴻 鵺 麿 など
                [0x9E00, 0x9EFF], // 麺 周辺
            ] as [$lo, $hi]) {
                for ($i = $lo; $i <= $hi; $i++) $rare[$i] = true;
            }
            // 例外: 業務メールで使う頻出漢字を明示的に除外して+判定にする.
            foreach ([
                0x8003, // 考
                0x8005, // 者
                0x80B2, // 育
                0x8272, // 色
                0x82B1, // 花
                0x82E5, // 若
                0x8336, // 茶
                0x88AB, // 被
                0x88C1, // 裁
                0x8868, // 表
                0x8907, // 複
                0x898B, // 見
                0x8AAD, // 読
                0x8AC7, // 談
                0x8ACB, // 請
                0x8AD6, // 論
                0x8B70, // 議
                0x8CA0, // 負
                0x8CC0, // 賀
                0x8CDB, // 賛
                0x8D77, // 起
                0x8DEF, // 路
                0x8FBC, // 込
                0x901A, // 通
                0x9023, // 連
                0x9054, // 達
                0x9069, // 適
                0x90E8, // 部
            ] as $u) {
                unset($rare[$u]);
            }
        }
        return isset($rare[$code]);
    }

    /**
     * 既に valid UTF-8 だが mojibake っぽい (スコア < 0) 文字列について、
     * 「いったん他のエンコーディングのバイト列とみなして UTF-8 再デコード」を試す.
     *
     * 例: UTF-8 として valid な "蝓ｺ蟷ｹ" は実体が SJIS バイト列の UTF-8 化なので、
     *     UTF-8 -> SJIS bytes -> CP932 デコード を試すと「基幹」に戻る.
     *
     * @return list<array{0:string,1:string}> [candidate_text, label] のリスト
     */
    protected function tryReinterpretBytes(string $value): array
    {
        $out = [];
        // 文字を CP932/SJIS バイトに「逆エンコード」して UTF-8 として再解釈する 2 段階回復.
        foreach (['CP932', 'SJIS-win', 'SJIS', 'EUC-JP', 'eucJP-win'] as $enc) {
            $back = @mb_convert_encoding($value, $enc, 'UTF-8');
            if ($back === false || $back === '' || $back === null) continue;
            // バイト列を UTF-8 として読み直し
            if (@mb_check_encoding($back, 'UTF-8')) {
                $out[] = [$back, "via-{$enc}-to-utf8"];
            }
        }
        return $out;
    }

    /**
     * 与えられた文字列が「テキストではない」(= バイナリ / PGP 署名 / TNEF / RTF 等)
     * かどうかをヒューリスティック判定する。
     *
     * 判定ロジック:
     * - 「業務メールに普通に出現するスクリプト」(ASCII / Latin / 日本語 / 全角 / 句読点 / 記号)
     *   の文字数を数える
     * - それ以外のスクリプト (Arabic / Syriac / Devanagari / ... CJK拡張 B/C など)
     *   が 30% を超えると binary 判定 → 添付/署名の raw バイトを誤って text body として
     *   返している可能性が高い
     * - 文字数が極端に少ない or サンプリング不能なら false
     */
    protected function looksLikeBinary(string $text): bool
    {
        $len = mb_strlen($text, 'UTF-8');
        if ($len < 60) return false;
        $sample = $len > 4000 ? mb_substr($text, 0, 4000, 'UTF-8') : $text;
        $sampleLen = mb_strlen($sample, 'UTF-8');
        $weird = 0;
        for ($i = 0; $i < $sampleLen; $i++) {
            $ch = mb_substr($sample, $i, 1, 'UTF-8');
            $code = mb_ord($ch, 'UTF-8');
            if ($code === false) { $weird++; continue; }
            // 業務メールで通常使われる Unicode 範囲を許容
            $ok = (
                $code <= 0x7F ||
                ($code >= 0xA0   && $code <= 0x024F) ||  // Latin-1 ext / Latin Extended-A,B
                ($code >= 0x2000 && $code <= 0x206F) ||  // General Punctuation
                ($code >= 0x2070 && $code <= 0x209F) ||  // Super/Subscripts
                ($code >= 0x20A0 && $code <= 0x20CF) ||  // Currency
                ($code >= 0x2100 && $code <= 0x214F) ||  // Letterlike
                ($code >= 0x2150 && $code <= 0x218F) ||  // Number Forms
                ($code >= 0x2190 && $code <= 0x21FF) ||  // Arrows
                ($code >= 0x2200 && $code <= 0x22FF) ||  // Mathematical
                ($code >= 0x2300 && $code <= 0x23FF) ||  // Misc Technical
                ($code >= 0x2460 && $code <= 0x24FF) ||  // Enclosed Alphanum
                ($code >= 0x2500 && $code <= 0x259F) ||  // Box Drawing
                ($code >= 0x25A0 && $code <= 0x25FF) ||  // Geometric
                ($code >= 0x2600 && $code <= 0x27BF) ||  // Misc Symbols / Dingbats
                ($code >= 0x2700 && $code <= 0x27BF) ||  // Dingbats
                ($code >= 0x3000 && $code <= 0x303F) ||  // CJK Symbols & Punct
                ($code >= 0x3040 && $code <= 0x309F) ||  // Hiragana
                ($code >= 0x30A0 && $code <= 0x30FF) ||  // Katakana
                ($code >= 0x3200 && $code <= 0x33FF) ||  // Enclosed CJK
                ($code >= 0x3400 && $code <= 0x4DBF) ||  // CJK Ext A
                ($code >= 0x4E00 && $code <= 0x9FFF) ||  // CJK Unified
                ($code >= 0xF900 && $code <= 0xFAFF) ||  // CJK Compat
                ($code >= 0xFE30 && $code <= 0xFE4F) ||  // CJK Compat Forms
                ($code >= 0xFF00 && $code <= 0xFFEF) ||  // Halfwidth/Fullwidth
                ($code >= 0x1F300 && $code <= 0x1F9FF)   // 絵文字
            );
            if (!$ok) $weird++;
        }
        return ($weird / max($sampleLen, 1)) > 0.30;
    }

    /**
     * 個別メールエラーを人間向けに翻訳する。
     */
    protected function humanizePerMailError(\Throwable $e): string
    {
        $msg = $e->getMessage();

        // DB 接続不能
        if ($e instanceof \PDOException || str_contains($msg, 'SQLSTATE')) {
            if (str_contains($msg, 'Connection refused') || str_contains($msg, 'getaddrinfo')) {
                return 'データベースサーバーに接続できません: ' . $msg;
            }
            if (str_contains($msg, 'Incorrect string value')) {
                return 'DB に保存できない文字 (バイナリ等) が含まれていました: ' . $msg;
            }
            if (str_contains($msg, 'Data too long')) {
                return 'DB カラムの上限を超える文字列が含まれていました: ' . $msg;
            }
            if (str_contains($msg, "Duplicate entry")) {
                return '同一メールが既に保存済みです (重複): ' . $msg;
            }
            return 'DB エラー: ' . $msg;
        }
        // タイムアウト/接続系
        if (str_contains($msg, 'timed out') || str_contains($msg, 'Connection refused')) {
            return '通信エラー: ' . $msg;
        }
        // ストレージ書き込み失敗
        if (str_contains($msg, 'Failed to write') || str_contains($msg, 'No space left')) {
            return '添付ファイルの保存に失敗: ' . $msg;
        }
        return $msg;
    }

    /**
     * MailBlockRule にマッチするかを判定。1 つでもマッチすればルール側の
     * match_count / last_matched_at を更新して true を返す。
     *
     * 失敗 (DB なし等) しても true は返さない (= 取り込みは続行)。
     */
    protected function isSpamByRules(
        string $fromAddress,
        string $subject,
        string $bodyText,
        string $bodyHtml,
        string $toAddress = '',
        string $cc = '',
        string $bcc = ''
    ): bool {
        try {
            $rules = MailBlockRule::where('enabled', true)->get();
        } catch (\Throwable $e) {
            // テーブル未作成等の問題はログに残す。スパム判定はスキップ (= 取り込みは続行)。
            Log::warning('EmailFetcher: failed to load MailBlockRule list: ' . $e->getMessage());
            return false;
        }
        $matched = null;
        foreach ($rules as $rule) {
            try {
                if ($rule->matches($fromAddress, $subject, $bodyText, $bodyHtml, $toAddress, $cc, $bcc)) {
                    $matched = $rule;
                    break;
                }
            } catch (\Throwable $e) {
                // 個別ルールの異常 (regex 不正 等) はログに残す
                Log::warning('EmailFetcher: spam rule match failed', [
                    'rule_id' => $rule->id ?? null,
                    'pattern' => $rule->pattern ?? null,
                    'error'   => $e->getMessage(),
                ]);
            }
        }
        if (!$matched) return false;

        // ルール側のカウンタを更新 (失敗してもメール保存は続行、ただしログには出す)
        try {
            $matched->forceFill([
                'match_count'     => (int) $matched->match_count + 1,
                'last_matched_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('EmailFetcher: spam rule counter update failed: ' . $e->getMessage());
        }
        return true;
    }

    /**
     * 本文をバイナリ判定で置き換える際のプレースホルダ。
     */
    protected function binaryPlaceholder($message, string $fromMail): string
    {
        $subject = '';
        try { $subject = (string) ($message->getSubject() ?: ''); }
        catch (\Throwable $e) { Log::debug('EmailFetcher.binaryPlaceholder: getSubject failed: ' . $e->getMessage()); }
        $lines   = [
            '(本文を表示できませんでした)',
            '',
            'このメールはバイナリパート (電子署名 / 暗号化本文 / RTF/TNEF など) を含み、',
            'プレーンテキストとして取り込めなかったため、本文表示を省略しました。',
            '元のメールクライアントで内容をご確認ください。',
            '',
            "差出人: {$fromMail}",
            "件名:   {$subject}",
        ];
        return $this->cleanUtf8(implode("\n", $lines));
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
                // 顧客名 / 件名 / 送信履歴 のいずれかと一致する共有ルームへ自動振り分け
                try {
                    \App\Services\ChatRoomAutoBundler::bundleThread($thread, $fromAddress);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('auto-bundle (subject path) failed', [
                        'thread_id' => $thread->id, 'error' => $e->getMessage(),
                    ]);
                }
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

    /**
     * 件名一致判定用のスレッド・キーを返す.
     * 件名から以下を除去した残骸を小文字化・空白詰めして返す:
     *   - 先頭の Re: / Re :  / RE: / Fw: / Fwd: の連鎖 (大文字小文字無視)
     *   - [TICKET-XXXX] チケット番号タグ
     *   - [ml-xxx 123] のような ML 配信番号タグ (角括弧 + 半角空白 + 数字)
     *   - 連続する全角/半角空白の正規化
     * 同じスレッドと判定されるべきメールは、この戻り値が一致する.
     *
     * ※ 「件名のみで一致」判定の中枢なので、ここを過度にゆるくすると別案件まで混ざる.
     */
    public static function normalizeSubjectForThreading(string $subject): string
    {
        $s = $subject;
        // Re:/Fwd:/Fw: の連鎖を頭から除去 (大文字小文字 + 全角 ":" にも対応).
        // ※ "[::]" だと PCRE が POSIX 名前付きクラスの開始と誤認するため "(?::|：)" で書く.
        $s = preg_replace('/^(\s*(?:re|fwd|fw)\s*(?::|：)\s*)+/iu', '', $s) ?? $s;
        // [TICKET-XXXX] を除去
        $s = preg_replace(EmailThread::TICKET_REGEX, '', $s) ?? $s;
        // ML 配信番号 [foo-bar 12345]   (Mailman / Sympa / Google Groups 形式)
        $s = preg_replace('/\[\s*[^\[\]\s]+\s+\d+\s*\]/u', '', $s) ?? $s;
        // 余分な空白を 1 個に圧縮 + 前後トリム
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        $s = trim($s);
        return mb_strtolower($s);
    }

    protected function findOrCreateThread($message)
    {
        $subject = $message->getSubject() ?: '(件名なし)';

        // ★ 件名一致のためのキーを先に作る (Re: / Fwd: / [TICKET-..] / [ml-foo 123] 等を除去).
        //   ユーザ要望「件名が一致しているもののみ同じスレッドに入れる」に応えるため、
        //   In-Reply-To や 部分一致でのスレッド統合は、件名キーが一致する場合に限定する.
        $normalizedKey = self::normalizeSubjectForThreading($subject);

        // ★ さらにユーザ要望「件名が同じでもメールアドレスが異なるなら別スレッド」に応えるため、
        //   新着メールの from_address を取り出し、候補スレッドに「同じ from の過去メールがあるか」を必須にする.
        //   (典型的な reply chain では同じ顧客の from が複数回現れるので、これで識別できる.
        //    顧客 A の "お問い合わせ" と 顧客 B の "お問い合わせ" は別スレッドになる)
        $newFromAddress = $message->getFrom()[0]->mail ?? null;
        $newFromLower   = $newFromAddress ? mb_strtolower(trim($newFromAddress)) : '';

        // 候補スレッドに「new email の from と同じ from を持つ過去メール」があるかを判定するヘルパ.
        // ※ 「該当スレッドの from を全部 SELECT して比較」より、 EXISTS 1 件確認の方が速いので
        //    Email::where(thread_id=X, lower(from_address)=Y)->exists() で確認する.
        $threadContainsFrom = function (int $threadId) use ($newFromLower): bool {
            if ($newFromLower === '') return true; // from が無い場合 (希) は subject だけで判定 (= 旧挙動)
            return Email::where('thread_id', $threadId)
                ->whereRaw('LOWER(from_address) = ?', [$newFromLower])
                ->exists();
        };

        // (0) 件名のチケット番号で最優先マッチ (カラム未作成環境でも落ちないようガード)
        //     チケット番号は明示的な識別子なので件名一致に依らない (システム自身が付与する番号).
        //     ★ チケット番号一致でも from が違えば別スレッドにする (顧客取り違え事故防止).
        $ticket = EmailThread::extractTicketNumber($subject);
        if ($ticket) {
            try {
                $byTicket = EmailThread::where('ticket_number', $ticket)->first();
                if ($byTicket && $threadContainsFrom((int) $byTicket->id)) {
                    $byTicket->ensureTicketNumber();
                    return $byTicket;
                }
            } catch (\Throwable $e) {
                // カラム未存在時はスキップ
            }
        }

        // (1) In-Reply-To / References に基づくスレッド検索 (RFC 標準のメールスレッド).
        //     ★ ただし要望に従い「件名が一致するときだけ」+「from が一致するときだけ」採用する.
        //        ヘッダ的には繋がっていても、件名が分岐 / 送信者が違う場合は別スレッドとして扱う.
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
                $parentThread = $parentEmail->thread;
                $parentKey    = self::normalizeSubjectForThreading((string) $parentThread->subject);
                // 件名キーが一致 + そのスレッドに同じ from の過去メールがある場合に限定.
                if ($normalizedKey !== '' && $parentKey !== '' && $normalizedKey === $parentKey
                    && $threadContainsFrom((int) $parentThread->id)) {
                    $parentThread->ensureTicketNumber();
                    return $parentThread;
                }
                // 件名が分岐 or from が違えば、このメールは新しい独立スレッドにする (下に流す).
            }
        }

        // (2) 件名の完全一致 (正規化後) + from 一致 でスレッド検索.
        //     旧実装の "like %normalized%" は曖昧マッチが過剰だったため廃止.
        //     正規化キー (= Re:/Fwd:/チケット番号/ML プレフィックス除去後の本体) が一致する直近のスレッドだけ走査し、
        //     さらに「new email の from と同じ from を持つ過去メールがある」スレッドだけマッチする.
        if ($normalizedKey !== '') {
            $candidates = EmailThread::orderByDesc('last_email_at')->limit(50)->get(['id','subject','last_email_at']);
            foreach ($candidates as $cand) {
                $candKey = self::normalizeSubjectForThreading((string) $cand->subject);
                if ($candKey !== '' && $candKey === $normalizedKey
                    && $threadContainsFrom((int) $cand->id)) {
                    $hit = EmailThread::find($cand->id);
                    if ($hit) {
                        $hit->ensureTicketNumber();
                        return $hit;
                    }
                }
            }
        }

        // 上で $newFromAddress を既に取得済. ここでは customer 検索用にエイリアスを置く.
        $fromAddress = $newFromAddress;
        $customer = $fromAddress ? \App\Models\Customer::where('email', $fromAddress)->first() : null;

        // 新規スレッド作成 → 自動でチケット番号を付与.
        // subject カラムには正規化前の Re: / Fwd: プレフィックスを残さない方が一覧で読みやすいので
        // normalized 版を採用 (空ならフォールバックで元の subject).
        $cleanSubjectForStore = preg_replace('/^(Re:\s*|Fwd:\s*|Fw:\s*)+/iu', '', $subject) ?? $subject;
        $cleanSubjectForStore = trim($cleanSubjectForStore);
        if ($cleanSubjectForStore === '') $cleanSubjectForStore = $subject;
        $thread = EmailThread::create([
            'subject'       => $cleanSubjectForStore,
            'status'        => 'inbox',
            'last_email_at' => now(),
            'customer_id'   => $customer?->id,
        ]);
        $thread->ensureTicketNumber();
        // 顧客名 / 件名 / 送信履歴 のいずれかと一致する共有ルームへ自動振り分け
        try {
            \App\Services\ChatRoomAutoBundler::bundleThread($thread, $fromAddress);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('auto-bundle (header path) failed', [
                'thread_id' => $thread->id, 'error' => $e->getMessage(),
            ]);
        }
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

    // ===== 個人メールアカウント対応 (追加機能) =====

    /**
     * システム既定 (mail_settings) + 全ユーザの有効アカウントを順に取得する。
     * 既存 fetch() の挙動には手を入れず、個人アカウント分を「追加で」取得する。
     */
    public function fetchAll(): int
    {
        $total = 0;
        try {
            $total += $this->fetch();
        } catch (\Throwable $e) {
            Log::error('[mail-fetch system] ' . $e->getMessage());
        }
        $accounts = \App\Models\MailAccount::query()
            ->where('is_active', true)
            ->whereIn('inbox_protocol', [\App\Models\MailAccount::PROTOCOL_IMAP, \App\Models\MailAccount::PROTOCOL_POP3])
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
     * 個人アカウント (MailAccount) で取得。owner_user_id / mail_account_id でタグ付け。
     * 既存 fetch() とは独立したシンプル版。50件取得 / message-id 重複弾き / スレッド連結のみ。
     */
    public function fetchForAccount(\App\Models\MailAccount $account): int
    {
        if (!$account->canReceive()) {
            Log::info('[mail-fetch] account ' . $account->id . ' skipped (incomplete config)');
            return 0;
        }

        // OAuth (XOAUTH2) アカウントは access_token を refresh してから利用する
        $oauthToken = null;
        if ($account->isOAuth()) {
            $svc = app(\App\Services\MicrosoftMailOAuth::class);
            $oauthToken = $svc->ensureValidAccessToken($account);
            if (!$oauthToken) {
                Log::warning('[mail-fetch] account ' . $account->id . ' OAuth access_token を取得できません');
                return 0;
            }
        }

        if ($account->inbox_protocol === \App\Models\MailAccount::PROTOCOL_POP3) {
            $config = [
                'host' => $account->pop_host, 'port' => $account->pop_port,
                'encryption' => $account->pop_encryption === 'null' ? false : $account->pop_encryption,
                'validate_cert' => false,
                'username' => $account->pop_username,
                'password' => $oauthToken ?: $account->pop_password,
                'protocol' => 'pop3',
                'authentication' => $oauthToken ? 'oauth' : null,
            ];
            $folderName = 'INBOX';
        } else {
            $config = [
                'host' => $account->imap_host, 'port' => $account->imap_port,
                'encryption' => $account->imap_encryption === 'null' ? false : $account->imap_encryption,
                'validate_cert' => false,
                'username' => $account->imap_username,
                'password' => $oauthToken ?: $account->imap_password,
                'protocol' => 'imap',
                'authentication' => $oauthToken ? 'oauth' : null,
            ];
            $folderName = $account->imap_folder ?: 'INBOX';
        }

        try {
            $client = \Webklex\IMAP\Facades\Client::make($config);
            $client->connect();
        } catch (\Throwable $e) {
            throw new \RuntimeException('メールサーバーに接続できませんでした: ' . $e->getMessage());
        }

        $protocol = $config['protocol'];
        $folders = $client->getFolders();
        $imported = 0;

        // imap_folder='*' (ワイルドカード) や空文字なら全フォルダ走査.
        // 旧実装は完全一致のみで, ユーザが '*' を入れていると loop が全 continue して
        // 「取得 0 件 / エラー 0 件」という無音失敗になっていた.
        $fetchAllFolders = ($folderName === '*' || $folderName === '');

        foreach ($folders as $folder) {
            if ($protocol === 'imap'
                && !$fetchAllFolders
                && strcasecmp($folder->name, $folderName) !== 0
                && strcasecmp($folder->path, $folderName) !== 0) {
                continue;
            }
            $messages = $folder->messages()->all()->limit(50)->get();
            foreach ($messages as $message) {
                $messageId = (string) $message->getMessageId();
                if ($messageId !== '' && Email::query()
                    ->where('message_id', $messageId)
                    ->where('owner_user_id', $account->user_id)
                    ->exists()) {
                    continue;
                }

                // スレッド: 個人スコープ内で In-Reply-To 連結 or 件名一致
                $thread = $this->findOrCreateThreadForOwner($message, $account->user_id, $account->id);

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

                // 件名: Webklex の getSubject() は基本 MIME デコード済みだが,
                // 連結 encoded-word を間に空白なしで連ねた変則的な件名 (xserver のメンテ通知等) では
                // 生のまま返り, VARCHAR(255) の subject カラムに入らず SQL エラーになる.
                // ロバストデコーダ + cleanUtf8(255) で必ず 255 文字以内に切り詰める.
                $rawSubject = (string) ($message->getSubject() ?: '');
                $decoded    = $this->decodeMimeHeaderRobust($rawSubject);
                if ($decoded === '') $decoded = $rawSubject;
                $subjectClean = $this->cleanUtf8($decoded !== '' ? $decoded : '(件名なし)', 255);

                $email = Email::create([
                    'thread_id'       => $thread->id,
                    'message_id'      => $messageId ?: null,
                    'in_reply_to'     => $inReplyTo ?: null,
                    'subject'         => $subjectClean,
                    'from_address'    => $message->getFrom()[0]->mail ?? 'unknown@example.com',
                    'from_name'       => $message->getFrom()[0]->personal ?? null,
                    'to_address'      => $message->getTo()[0]->mail ?? '',
                    'cc'              => $cc ?: null,
                    'body_text'       => $message->getTextBody() ?: '',
                    'body_html'       => $message->getHTMLBody() ?: '',
                    'received_at'     => $receivedAt,
                    'owner_user_id'   => $account->user_id,
                    'mail_account_id' => $account->id,
                ]);
                $this->handleAttachments($message, $email);
                $thread->update(['last_email_at' => $receivedAt]);
                $imported++;
            }
        }

        $account->forceFill(['last_fetched_at' => now()])->save();
        return $imported;
    }

    protected function findOrCreateThreadForOwner($message, int $ownerUserId, int $accountId): EmailThread
    {
        $inReplyTo = (string) $message->getInReplyTo();
        $references = [];
        $refAttr = $message->getReferences();
        if ($refAttr instanceof \Webklex\PHPIMAP\Attribute) {
            $references = $refAttr->all();
        } elseif (is_array($refAttr)) {
            $references = $refAttr;
        } elseif (is_string($refAttr)) {
            $references = explode(' ', $refAttr);
        }
        if ($inReplyTo && !in_array($inReplyTo, $references)) {
            $references[] = $inReplyTo;
        }
        $references = array_filter($references);

        if (!empty($references)) {
            $parentEmail = Email::whereIn('message_id', $references)
                ->where('owner_user_id', $ownerUserId)
                ->first();
            if ($parentEmail && $parentEmail->thread) {
                return $parentEmail->thread;
            }
        }

        // 件名は MIME デコード + 255 文字以内に揃える (email_threads.subject は VARCHAR(1000) だが,
        // 過剰に長い件名は LIKE クエリも遅くするため emails.subject と同じ 255 で統一する).
        $rawSubject = (string) ($message->getSubject() ?: '');
        $decoded    = $this->decodeMimeHeaderRobust($rawSubject);
        if ($decoded === '') $decoded = $rawSubject;
        $subject = $this->cleanUtf8($decoded !== '' ? $decoded : '(件名なし)', 255);
        $normalized = preg_replace('/^(Re:\s*|Fwd:\s*)+/i', '', $subject) ?? $subject;
        $thread = EmailThread::query()
            ->where('owner_user_id', $ownerUserId)
            ->where('subject', 'like', "%{$normalized}%")
            ->orderByDesc('last_email_at')
            ->first();
        if ($thread) return $thread;

        return EmailThread::create([
            'subject'         => $normalized,
            'status'          => 'inbox',
            'last_email_at'   => now(),
            'owner_user_id'   => $ownerUserId,
            'mail_account_id' => $accountId,
        ]);
    }
}
