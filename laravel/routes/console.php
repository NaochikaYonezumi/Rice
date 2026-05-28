<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Modules\MailClient\Console\Commands\FetchEmailsCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * 予約送信 (scheduled send) のディスパッチャ.
 *
 * pending_emails で status='scheduled' かつ scheduled_for <= now() の行を pick して送信.
 * 送信処理は PendingEmailController::executeSend() に集約してあり、
 * 通常の承認フロー (approve) と同じロジックを再利用する.
 *
 * 失敗時は send_attempts++ + last_send_error 記録. 3 回失敗したら status='draft' に戻して
 * ユーザに気付かせる (= 自動リトライをやめて手動介入を促す).
 *
 * 1 回の実行で最大 50 件まで処理 (cron 1 分実行を想定; 大量予約があっても DB / SMTP を詰まらせない).
 *
 * 使い方:
 *   php artisan mail:send-scheduled
 *   php artisan mail:send-scheduled --limit=20
 */
Artisan::command('mail:send-scheduled {--limit=50}', function (\Modules\MailClient\Services\EmailFetcher $fetcher) {
    /** @var \Illuminate\Console\Command $this */
    $limit = max(1, (int) $this->option('limit'));
    $now   = now();
    $rows = \App\Models\PendingEmail::where('status', \App\Models\PendingEmail::STATUS_SCHEDULED)
        ->whereNotNull('scheduled_for')
        ->where('scheduled_for', '<=', $now)
        ->orderBy('scheduled_for')
        ->limit($limit)
        ->get();
    if ($rows->isEmpty()) {
        $this->info('no scheduled emails due. (now=' . $now->format('Y-m-d H:i:s') . ')');
        return 0;
    }
    $this->info('processing ' . $rows->count() . ' scheduled emails...');

    /** @var \App\Http\Controllers\PendingEmailController $controller */
    $controller = app(\App\Http\Controllers\PendingEmailController::class);

    $ok = 0; $fail = 0;
    foreach ($rows as $pending) {
        try {
            $controller->executeSend($pending, $fetcher, $pending->created_by_user_id);
            $this->line('  ✔ #' . $pending->id . ' "' . mb_substr($pending->subject ?? '', 0, 40) . '" -> ' . $pending->to_address);
            $ok++;
        } catch (\Throwable $e) {
            $attempts = (int) ($pending->send_attempts ?? 0) + 1;
            $msg = mb_substr($e->getMessage(), 0, 1000);
            $update = [
                'send_attempts'   => $attempts,
                'last_send_error' => $msg,
            ];
            if ($attempts >= 3) {
                $update['status']        = \App\Models\PendingEmail::STATUS_DRAFT;
                $update['scheduled_for'] = null;
                $this->error('  ✘ #' . $pending->id . ' failed 3 times → 下書きに戻しました: ' . $msg);
            } else {
                $this->error('  ✘ #' . $pending->id . ' attempt ' . $attempts . ' failed: ' . $msg);
            }
            $pending->update($update);
            $fail++;
        }
    }
    $this->info("done. ok={$ok} fail={$fail}");
    return 0;
})->purpose('Send all due scheduled emails (pending_emails where status=scheduled AND scheduled_for <= now()).');

// Laravel Scheduler: 1 分ごとに mail:send-scheduled を起動.
// 本番では cron 側で `* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1` を仕込む.
// withoutOverlapping() で長時間の SMTP 詰まり時に多重起動しないようにロック.
Schedule::command('mail:send-scheduled')
    ->everyMinute()
    ->withoutOverlapping(5);

Artisan::command('mail:fetch', function (\Modules\MailClient\Services\EmailFetcher $fetcher) {
    /** @var \Illuminate\Console\Command $this */
    $settings = \App\Models\MailSetting::getSettings();
    $this->info('Starting email fetch...');
    try {
        // 1) system / 共有メール (MailSetting からの IMAP/POP3)
        $count  = $fetcher->fetch();
        $errors = $fetcher->getLastErrors();
        $settings->recordFetchSuccess((int) $count, $errors['errors'] ?? []);

        // 2) 個人メールアカウント (MailAccount). 1 アカウントの失敗で全体を落とさず, ログに残す.
        //    旧実装は fetch() だけを呼んでいて, 個人メールが永遠に取得されない不具合だった.
        $personalAccounts = \App\Models\MailAccount::query()
            ->where('is_active', true)
            ->whereIn('inbox_protocol', [
                \App\Models\MailAccount::PROTOCOL_IMAP,
                \App\Models\MailAccount::PROTOCOL_POP3,
            ])
            ->get();
        $accountImported = 0;
        $accountErrors   = 0;
        foreach ($personalAccounts as $account) {
            try {
                $accountImported += $fetcher->fetchForAccount($account);
            } catch (\Throwable $e) {
                $accountErrors++;
                \Illuminate\Support\Facades\Log::error(
                    '[mail-fetch account#' . $account->id . '] ' . $e->getMessage()
                );
                $this->warn(sprintf(
                    '  account#%d (%s) fetch failed: %s',
                    $account->id, $account->email_address, $e->getMessage()
                ));
            }
        }
        $count += $accountImported;
        $this->info("Email fetch completed. imported={$count} (system+personal={$accountImported} from " . $personalAccounts->count() . " accts), errors={$errors['count']}, account_errors={$accountErrors}");

        // 個別エラーがあった場合は CLI でも要約を表示する
        if (($errors['count'] ?? 0) > 0) {
            $this->warn("---- 個別メールエラー ({$errors['count']} 件) ----");
            foreach (array_slice($errors['errors'] ?? [], 0, 10) as $i => $err) {
                $this->line(sprintf(
                    '  %d. [%s] %s -- %s',
                    $i + 1,
                    $err['from'] ?? '(no from)',
                    mb_substr($err['subject'] ?? '(件名なし)', 0, 50),
                    $err['error'] ?? ''
                ));
            }
            if (count($errors['errors']) > 10) {
                $this->line('  ... 他 ' . (count($errors['errors']) - 10) . ' 件');
            }
        }
        return 0;
    } catch (\Throwable $e) {
        // 認証失敗・接続失敗・SSL 等の全体エラー。EmailFetcher::humanizeConnectError で
        // 日本語化済みのメッセージがそのまま入っている。
        $settings->recordFetchFailure($e->getMessage());
        $this->error('Email fetch FAILED: ' . $e->getMessage());
        $extra = $fetcher->getLastErrors();
        if (!empty($extra['connection_error']) && $extra['connection_error'] !== $e->getMessage()) {
            $this->error('  raw: ' . $extra['connection_error']);
        }
        $this->error('  consecutive_failures: ' . (int) $settings->fresh()->consecutive_failures);
        return 1;  // shell exit code 1 (失敗)
    }
})->purpose('Fetch emails from POP3/IMAP server');

/**
 * 既存の emails テーブルに保存されている本文を再走査し、
 * バイナリ判定で置換 (PGP 署名 / TNEF / RTF などの生バイト) されているものを
 * プレースホルダ または body_html を strip_tags した文字列で置き換える。
 *
 * 使い方:
 *   php artisan mail:repair-bodies              # ドライラン (件数のみ表示)
 *   php artisan mail:repair-bodies --apply      # 実際に書き換える
 *   php artisan mail:repair-bodies --apply --limit=200
 */
Artisan::command('mail:repair-bodies {--apply} {--limit=}', function () {
    /** @var \Illuminate\Console\Command $this */
    $apply = (bool) $this->option('apply');
    $limit = $this->option('limit');
    $limit = $limit !== null ? (int) $limit : null;

    // バイナリ判定ヘルパは EmailFetcher のメソッドを流用するために
    // 一旦インスタンス化する (DI で各種依存は要らない)
    $fetcher = app(\Modules\MailClient\Services\EmailFetcher::class);
    // protected メソッドにアクセスするため Closure::bind を使う
    $looksBinary = \Closure::bind(function (string $t) {
        /** @var \Modules\MailClient\Services\EmailFetcher $this */
        return $this->looksLikeBinary($t);
    }, $fetcher, \Modules\MailClient\Services\EmailFetcher::class);
    $clean = \Closure::bind(function (?string $t, int $max = 60000) {
        /** @var \Modules\MailClient\Services\EmailFetcher $this */
        return $this->cleanUtf8($t, $max);
    }, $fetcher, \Modules\MailClient\Services\EmailFetcher::class);

    $query = \App\Models\Email::query()->orderByDesc('id');
    if ($limit) $query->limit($limit);

    $scanned = 0;
    $broken  = 0;
    $fixed   = 0;

    $query->chunkById(200, function ($rows) use (&$scanned, &$broken, &$fixed, $apply, $looksBinary, $clean) {
        foreach ($rows as $email) {
            $scanned++;
            $bodyText = (string) ($email->body_text ?? '');
            $bodyHtml = (string) ($email->body_html ?? '');
            if (!$looksBinary($bodyText)) continue;

            $broken++;
            // HTML 側を strip_tags して救済を試みる
            $candidate = trim(strip_tags($bodyHtml));
            $candidate = $clean($candidate);
            if ($candidate === '' || $looksBinary($candidate)) {
                // どちらもバイナリ → プレースホルダ
                $candidate = "(本文を表示できませんでした)\n\nこのメールはバイナリパートを含み、テキストとして取り込めませんでした。\n\n差出人: {$email->from_address}\n件名:   {$email->subject}";
            }

            if ($apply) {
                $email->body_text = $clean($candidate);
                // バイナリな HTML はクリア
                if ($looksBinary($bodyHtml)) $email->body_html = '';
                $email->save();
                $fixed++;
            }
        }
    });

    if ($apply) {
        $this->info("scanned={$scanned}  broken={$broken}  fixed={$fixed}");
    } else {
        $this->info("scanned={$scanned}  broken={$broken}  (dry-run: --apply で実書き換え)");
    }
    return 0;
})->purpose('Repair already-stored garbled email bodies (binary stored as text)');

/**
 * 孤児スレッド (Email 行が 0 件の EmailThread) を検出し、必要に応じて削除する。
 *
 * 旧 EmailFetcher は「findOrCreateThread() で新スレッドを先に作って、
 * 後段の Email::create / handleAttachments が例外で失敗」した場合に、
 * スレッドだけが DB に残る (Email 行は無し) という残骸を作ることがあった。
 * これがメール一覧で latestEmail = null となり、「不明な送信者」として
 * 表示されてしまう原因の一つ。
 *
 * 新しい取り込み経路は DB::transaction で囲んだのでこれ以上は増えないが、
 * 既存の残骸をクリーンアップするためのコマンド。
 *
 * 使い方:
 *   php artisan mail:clean-orphan-threads             # ドライラン (件数 + 一覧表示)
 *   php artisan mail:clean-orphan-threads --apply     # 実削除
 *   php artisan mail:clean-orphan-threads --apply --limit=100
 *
 * 削除対象から除外:
 *   - is_manual_upload = true (添付アップロード由来。意図的に Email が空の場合もある)
 *   - threadMerges のソース側 (マージで Email が target に移動した残骸はそもそも一覧に出ない)
 */
Artisan::command('mail:clean-orphan-threads {--apply} {--limit=}', function () {
    /** @var \Illuminate\Console\Command $this */
    $apply = (bool) $this->option('apply');
    $limit = $this->option('limit');
    $limit = $limit !== null ? (int) $limit : null;

    $query = \App\Models\EmailThread::doesntHave('emails')
        ->where(function ($q) {
            $q->where('is_manual_upload', false)
              ->orWhereNull('is_manual_upload');
        })
        ->orderByDesc('id');
    if ($limit) $query->limit($limit);

    $orphans = $query->get(['id', 'subject', 'created_at', 'last_email_at', 'ticket_number']);
    $total   = $orphans->count();

    if ($total === 0) {
        $this->info('孤児スレッドは見つかりませんでした。');
        return 0;
    }

    $this->info("孤児スレッド候補: {$total} 件" . ($apply ? ' (削除します)' : ' (dry-run)'));
    // 先頭 20 件だけ詳細表示 (件数が多い場合の出力肥大化を回避)
    foreach ($orphans->take(20) as $i => $t) {
        $this->line(sprintf(
            '  %2d. id=%d ticket=%s created=%s subject=%s',
            $i + 1,
            $t->id,
            $t->ticket_number ?: '-',
            $t->created_at?->format('Y/m/d H:i') ?: '-',
            mb_substr((string) ($t->subject ?? '(件名なし)'), 0, 60)
        ));
    }
    if ($total > 20) {
        $this->line('  ... 他 ' . ($total - 20) . ' 件');
    }

    if (!$apply) {
        $this->warn('dry-run モードです。実削除するには --apply を付けて再実行してください。');
        return 0;
    }

    $deleted = 0;
    foreach ($orphans as $thread) {
        try {
            // delete() は ChatRoomThread ピボット / ThreadComment / ThreadMerge 等の
            // 関連レコードに FK cascade が設定されていればまとめて消える。
            // 設定されていない場合でも EmailThread 自体は消える (子レコードは別途残るが
            // メール一覧には影響しない)。
            $thread->delete();
            $deleted++;
        } catch (\Throwable $e) {
            $this->error("  id={$thread->id} の削除に失敗: " . $e->getMessage());
        }
    }
    $this->info("削除完了: {$deleted} / {$total} 件");
    return 0;
})->purpose('Detect & delete orphan EmailThread rows (threads with zero Email rows)');


/**
 * mail:purge-trash
 *
 * ゴミ箱 (status='trash' / emails.trashed_at IS NOT NULL) に
 * EmailThread::TRASH_RETENTION_DAYS 日以上滞留しているレコードを cascade ハード DELETE する.
 *
 * 通常は Scheduler から日次 (03:10) で起動.
 * --dry-run で実削除せず対象件数だけ表示.
 * --days=N で保持日数を上書き可能 (デバッグ用).
 *
 * 対象:
 *   - スレッド: email_threads.status='trash' AND email_threads.trashed_at < NOW() - INTERVAL N DAY
 *     → $thread->delete() で cascade (Email / EmailAttachment / chat_room_thread / ThreadMemo /
 *       ThreadComment / ThreadMerge 等の FK CASCADE)
 *   - 個別メール: emails.trashed_at < NOW() - INTERVAL N DAY AND 親スレッドは status='trash' でない
 *     → $email->delete() で添付も cascade. メール削除後にスレッドが空になったら親スレッドも削除.
 *
 * 順序: 個別メール → スレッド の順に処理する.
 *   スレッド削除が先だと cascade で個別メールも一緒に消え、削除イベントが集計に混ざるため.
 */
Artisan::command('mail:purge-trash {--dry-run} {--days=}', function () {
    /** @var \Illuminate\Console\Command $this */
    $dry  = (bool) $this->option('dry-run');
    $days = $this->option('days');
    // 既定値は管理者設定 (mail_settings.trash_retention_days) を読む.
    // mail_settings 行が無い / カラム未存在の環境では 30 日にフォールバック.
    $days = $days !== null ? max(0, (int) $days) : \App\Models\EmailThread::trashRetentionDays();
    $cutoff = now()->subDays($days);

    $this->info(sprintf(
        'mail:purge-trash 起動 (保持期間=%d 日, cutoff=%s%s)',
        $days,
        $cutoff->format('Y/m/d H:i'),
        $dry ? ', dry-run' : ''
    ));

    // (1) 個別メール (スレッドはまだ生きている = 親スレッド status != 'trash')
    $individualEmails = \App\Models\Email::whereNotNull('trashed_at')
        ->where('trashed_at', '<', $cutoff)
        ->with('thread')
        ->limit(2000) // 1 回の実行で爆発しないよう上限
        ->get();
    // 親スレッドが trash 状態のメールは (2) 側の cascade に任せるのでここでは除外する.
    $individualEmails = $individualEmails->filter(function ($e) {
        return !$e->thread || $e->thread->status !== \App\Models\EmailThread::STATUS_TRASH;
    })->values();

    $this->info('個別メール削除候補: ' . $individualEmails->count() . ' 件');
    $purgedEmails = 0;
    $orphanedThreadsFromEmail = 0;
    if (!$dry) {
        foreach ($individualEmails as $e) {
            try {
                $tid = $e->thread_id;
                $e->delete();
                $purgedEmails++;
                // 親スレッドの生存メール (trashed_at IS NULL かつ status != trash) が 0 件なら親も削除.
                if ($tid) {
                    $alive = \App\Models\Email::where('thread_id', $tid)->whereNull('trashed_at')->count();
                    if ($alive === 0) {
                        $thread = \App\Models\EmailThread::find($tid);
                        if ($thread) {
                            $thread->delete();
                            $orphanedThreadsFromEmail++;
                        }
                    }
                }
            } catch (\Throwable $err) {
                $this->error("  email_id={$e->id} の削除に失敗: " . $err->getMessage());
            }
        }
    }

    // (2) ゴミ箱スレッド (status='trash' AND trashed_at < cutoff)
    $trashedThreads = \App\Models\EmailThread::where('status', \App\Models\EmailThread::STATUS_TRASH)
        ->whereNotNull('trashed_at')
        ->where('trashed_at', '<', $cutoff)
        ->limit(2000)
        ->get();
    $this->info('スレッド削除候補: ' . $trashedThreads->count() . ' 件');
    $purgedThreads = 0;
    if (!$dry) {
        foreach ($trashedThreads as $t) {
            try {
                $t->delete();
                $purgedThreads++;
            } catch (\Throwable $err) {
                $this->error("  thread_id={$t->id} の削除に失敗: " . $err->getMessage());
            }
        }
    }

    if ($dry) {
        $this->warn('dry-run モード: 実削除はしていません. --dry-run を外して再実行してください.');
    } else {
        $this->info(sprintf(
            '完了: 個別メール %d 件 (連動スレッド削除 %d 件), ゴミ箱スレッド %d 件',
            $purgedEmails,
            $orphanedThreadsFromEmail,
            $purgedThreads
        ));
    }
    return 0;
})->purpose('Permanently delete trash items (threads / emails) older than the trash retention setting');

// Scheduler: 毎日 03:10 にゴミ箱を自動 purge. 業務時間帯を避けるため深夜帯.
Schedule::command('mail:purge-trash')
    ->dailyAt('03:10')
    ->withoutOverlapping(60);


/**
 * mail:purge-spam
 *
 * 迷惑メール (status='spam') を spammed_at 起点で保持期間 (mail_settings.spam_retention_days, 既定 30 日)
 * 経過後に cascade ハード DELETE する.
 *
 * 通常は Scheduler から日次 (03:20) で起動. mail:purge-trash と同じ思想.
 *   --dry-run         : 実削除せず対象件数だけ表示
 *   --days=N          : 保持日数を上書き (デバッグ用. 既定は MailSetting::spamRetentionDays())
 *
 * 対象: email_threads.status='spam' AND email_threads.spammed_at < NOW() - INTERVAL N DAY
 *   → $thread->delete() で cascade (Email / EmailAttachment / chat_room_thread / メモ / コメント / マージ).
 *
 * 注意:
 *   - 迷惑メール解除 (unmark) で status が inbox に戻った行は spammed_at がクリアされるため対象外.
 *   - status='spam' のままだが spammed_at が NULL の行は対象外 (古いマイグレーション前データ等の
 *     セーフティ. 必要なら手動で spammed_at を埋めて再実行).
 */
Artisan::command('mail:purge-spam {--dry-run} {--days=}', function () {
    /** @var \Illuminate\Console\Command $this */
    $dry  = (bool) $this->option('dry-run');
    $days = $this->option('days');
    $days = $days !== null ? max(0, (int) $days) : \App\Models\EmailThread::spamRetentionDays();
    $cutoff = now()->subDays($days);

    $this->info(sprintf(
        'mail:purge-spam 起動 (保持期間=%d 日, cutoff=%s%s)',
        $days,
        $cutoff->format('Y/m/d H:i'),
        $dry ? ', dry-run' : ''
    ));

    $threads = \App\Models\EmailThread::where('status', \App\Models\EmailThread::STATUS_SPAM)
        ->whereNotNull('spammed_at')
        ->where('spammed_at', '<', $cutoff)
        ->limit(2000)
        ->get();
    $this->info('迷惑メール削除候補: ' . $threads->count() . ' 件');

    $purged = 0;
    if (!$dry) {
        foreach ($threads as $t) {
            try {
                $t->delete();
                $purged++;
            } catch (\Throwable $err) {
                $this->error("  thread_id={$t->id} の削除に失敗: " . $err->getMessage());
            }
        }
    }

    if ($dry) {
        $this->warn('dry-run モード: 実削除はしていません. --dry-run を外して再実行してください.');
    } else {
        $this->info("完了: {$purged} 件削除しました.");
    }
    return 0;
})->purpose('Permanently delete spam threads older than the spam retention setting');

// Scheduler: 03:20 に迷惑メールも自動 purge. trash と 10 分ずらして同時実行を回避.
Schedule::command('mail:purge-spam')
    ->dailyAt('03:20')
    ->withoutOverlapping(60);


/**
 * rooms:auto-bundle
 *
 * 各ルームに登録された ChatRoomRoutingRule (= ユーザが明示的に作ったフィルタ) を
 * 既存メールへ遡及適用するバックフィルコマンド。
 *
 * - 顧客名一致 / 件名包含 / 送信履歴 のヒューリスティック自動振り分けは廃止 (要望:
 *   「ユーザによるフィルタがあって初めて振り分け」)
 * - 個人ルームは対象外
 * - 冪等: 既に紐付け済みのスレッドは syncWithoutDetaching で重複しない
 * - ルールを 1 件も持たないルームはスキップ
 *
 * オプション:
 *   --dry-run   実際の更新はせず、件数だけ表示
 *
 * 使い方:
 *   docker compose exec laravel php artisan rooms:auto-bundle
 *   docker compose exec laravel php artisan rooms:auto-bundle --dry-run
 */
Artisan::command('rooms:auto-bundle {--dry-run : 件数だけ表示し更新しない}', function () {
    /** @var \Illuminate\Console\Command $this */
    $dryRun = (bool) $this->option('dry-run');

    // 仕様変更: ルームの ChatRoomRoutingRule (= ユーザが明示登録したルール) だけを既存メールに
    // 遡及適用する。顧客名/件名/送信履歴のヒューリスティックは廃止。
    $rooms = \App\Models\ChatRoom::where('is_private', false)
        ->whereHas('routingRules', fn($q) => $q->where('enabled', true))
        ->orderBy('id')
        ->get(['id', 'name']);

    if ($rooms->isEmpty()) {
        $this->info('振り分けルールが登録されたルームがありません。ルーム管理画面でルールを登録してください。');
        return 0;
    }

    $matchedRooms = 0;
    $totalNew     = 0;
    foreach ($rooms as $r) {
        $rules = $r->routingRules()->where('enabled', true)->get();
        if ($rules->isEmpty()) continue;

        if ($dryRun) {
            // ルール毎に「マッチするけど未 bundled」のスレッド数を試算
            $bundledIds = \DB::table('chat_room_thread')->where('chat_room_id', $r->id)
                ->pluck('email_thread_id')->all();
            $matchedAll = collect();
            foreach ($rules as $rule) {
                $eq = \App\Models\Email::query()->whereNotNull('thread_id');
                switch ($rule->type) {
                    case \App\Models\ChatRoomRoutingRule::TYPE_FROM_ADDRESS:
                        $eq->whereRaw('LOWER(from_address) = ?', [mb_strtolower($rule->pattern)]); break;
                    case \App\Models\ChatRoomRoutingRule::TYPE_FROM_DOMAIN:
                        $eq->whereRaw('LOWER(from_address) LIKE ?', ['%@' . mb_strtolower($rule->pattern)]); break;
                    case \App\Models\ChatRoomRoutingRule::TYPE_SUBJECT_CONTAINS:
                        $eq->where('subject', 'like', '%' . $rule->pattern . '%'); break;
                    case \App\Models\ChatRoomRoutingRule::TYPE_TO_CONTAINS:
                        $eq->where('to_address', 'like', '%' . $rule->pattern . '%'); break;
                }
                $matchedAll = $matchedAll->merge($eq->distinct()->pluck('thread_id'));
            }
            $newOnly = $matchedAll->unique()->diff(collect($bundledIds));
            if ($newOnly->isEmpty()) continue;
            $matchedRooms++;
            $totalNew += $newOnly->count();
            $this->line(sprintf('  room=#%d "%s" → 新規取り込み候補 %d 件 (ルール %d 件適用)',
                $r->id, $r->name, $newOnly->count(), $rules->count()));
        } else {
            $added = \App\Services\ChatRoomAutoBundler::bundleByRoom($r);
            if ($added > 0) {
                $matchedRooms++;
                $totalNew += $added;
                $this->line(sprintf('  bundled: room=#%d "%s" ← %d 件 (ルール %d 件適用)',
                    $r->id, $r->name, $added, $rules->count()));
            }
        }
    }
    $verb = $dryRun ? '取り込み候補' : '取り込み';
    $this->info("完了: {$matchedRooms} ルーム / {$verb} {$totalNew} 件");
    if ($dryRun) $this->warn('実行するには --dry-run を外して再実行してください。');
    return 0;
})->purpose('Apply each room\'s user-defined routing rules to existing emails (only rooms with enabled rules are processed).');


/**
 * rooms:fix-merged-bundles
 *
 * マージで source 扱いになった (= email 一覧から非表示) スレッドに残っていた
 * chat_room_thread の紐付けを、target スレッドに転写する修復コマンド。
 *
 * 背景: 旧 ThreadMergeController::merge() は chat_room_thread ピボットを
 * 触っていなかったため、ルームに紐付いていたスレッドが merge されると
 *   - email 一覧では target だけが見える
 *   - chat_room_thread には source への参照だけが残る
 * という不整合が発生し、ルームフィルタで絞り込んだ時に
 * 「ルームには紐付いているはずなのに inbox タブに 1 件も出てこない」
 * という現象が起きていた。
 *
 * このコマンドは ThreadMerge 全件について
 *   source の chat_room_thread → target に syncWithoutDetaching で複製する。
 * source 側のピボットは残しておく (unmerge 時の安全側)。
 *
 * 使い方:
 *   docker compose exec laravel php artisan rooms:fix-merged-bundles
 *   docker compose exec laravel php artisan rooms:fix-merged-bundles --dry-run
 */
Artisan::command('rooms:fix-merged-bundles {--dry-run : 影響件数だけ表示し更新しない}', function () {
    /** @var \Illuminate\Console\Command $this */
    $dryRun = (bool) $this->option('dry-run');

    $merges = \App\Models\ThreadMerge::all(['id', 'target_thread_id', 'source_thread_id_original']);
    $this->info("ThreadMerge total: {$merges->count()}");

    $totalCopied = 0;
    $touched = 0;
    $statusFixed = 0;

    $active = ['inbox', 'hold', 'pending'];
    $done   = ['completed', 'no_action', 'spam'];

    foreach ($merges as $m) {
        // ----- (1) chat_room_thread の修復 -----
        $sourceRoomIds = \DB::table('chat_room_thread')
            ->where('email_thread_id', $m->source_thread_id_original)
            ->pluck('chat_room_id')->all();

        if (!empty($sourceRoomIds)) {
            $targetRoomIds = \DB::table('chat_room_thread')
                ->where('email_thread_id', $m->target_thread_id)
                ->pluck('chat_room_id')->all();
            $missing = array_diff($sourceRoomIds, $targetRoomIds);
            if (!empty($missing)) {
                $this->line(sprintf('  merge#%d: source=%d → target=%d  追加するルーム ID: %s',
                    $m->id, $m->source_thread_id_original, $m->target_thread_id,
                    json_encode(array_values($missing))));
                $totalCopied += count($missing);
                $touched++;
                if (!$dryRun) {
                    $target = \App\Models\EmailThread::find($m->target_thread_id);
                    if ($target) {
                        $target->chatRooms()->syncWithoutDetaching($missing);
                        \App\Models\ChatRoom::whereIn('id', $missing)->update(['updated_at' => now()]);
                    }
                }
            }
        }

        // ----- (2) status の同期: target → source -----
        // ターゲット側が「完了」など終了系で、ソースがまだ inbox 等のまま残っているなら
        // ソースもターゲットに合わせる (= 全部完了として扱う).
        // 要望: 「マージ後、完了した場合、マージされたものはすべて完了とする」
        $target = \App\Models\EmailThread::find($m->target_thread_id);
        $source = \App\Models\EmailThread::find($m->source_thread_id_original);
        if ($source && $target && $source->status !== $target->status) {
            $this->line(sprintf('  merge#%d: status 同期  source=%d (%s → %s)  (target=%d %s に合わせる)',
                $m->id, $source->id, $source->status, $target->status,
                $target->id, $target->status));
            $statusFixed++;
            if (!$dryRun) {
                $source->update(['status' => $target->status]);
            }
        }
    }

    if ($dryRun) {
        $this->warn("dry-run: ピボット {$totalCopied} 件追加 / status 同期 {$statusFixed} 件 (--dry-run を外して実行)");
    } else {
        $this->info("完了: ピボット {$totalCopied} 件追加 / status 同期 {$statusFixed} 件");
    }
    return 0;
})->purpose('Fix chat_room_thread pivots stranded on merged-source threads, and sync merge-source status with the target.');

