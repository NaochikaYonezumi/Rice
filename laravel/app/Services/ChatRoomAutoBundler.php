<?php

namespace App\Services;

use App\Models\ChatRoom;
use App\Models\Customer;
use App\Models\EmailThread;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * ルームへのスレッド自動振り分けサービス。
 *
 * 方針 (要望: 「ユーザによるフィルタがあって初めて振り分けする」):
 *  - ユーザが ChatRoomRoutingRule で明示的に設定したルール (D) のみで判定する.
 *  - 顧客名一致 (A) / 件名包含 (B) / 送信履歴 (C) の **ヒューリスティック自動判定は無効化**.
 *    ヘルパは残してあるが bundleThread からは呼ばない (将来の手動 backfill / UI 用)。
 *  - 個人ルーム (is_private = true) は対象外。
 *  - syncWithoutDetaching を使うので既存の紐付けは壊れない。
 *
 * エントリポイント:
 *  - bundleThread()       : 新着メール 1 通分のスレッドを振り分け (EmailFetcher から)
 *  - bundleByRoom()       : ルームの全ルールを既存メールに遡及適用 (ルーム作成 / 編集時 / バックフィル)
 *  - applyRoutingRuleBackfill() : 単一ルールを既存メールに適用 (ルール追加直後)
 */
class ChatRoomAutoBundler
{
    /**
     * 名前を比較用に正規化 (trim + lowercase + 全角空白吸収)。
     */
    public static function normalize(?string $name): string
    {
        if ($name === null) return '';
        // \u{3000} = 全角スペース
        $s = str_replace("\u{3000}", ' ', (string) $name);
        $s = trim($s);
        return mb_strtolower($s);
    }

    /**
     * 単一スレッドをユーザ設定の振り分けルール (D) でのみ自動振り分け。
     * マッチするルームがあれば bundle (syncWithoutDetaching) し、その ChatRoom を返す。
     *
     * ★ 旧実装にあった顧客名一致 (A) / 件名包含 (B) / 送信履歴 (C) のヒューリスティックは
     *   ユーザ要望 (「ユーザによるフィルタがあって初めて振り分け」) により無効化した。
     *   ルームに ChatRoomRoutingRule が 1 件も無ければ、このスレッドは自動振り分けされない。
     *
     * @param  EmailThread  $thread
     * @param  string|null  $fromAddress   送信元アドレスのヒント (新着メール受信時に EmailFetcher から渡す)。
     *                                     省略時はスレッド最古/最新メールから推定する。
     */
    public static function bundleThread(EmailThread $thread, ?string $fromAddress = null): ?ChatRoom
    {
        if (!$thread) return null;

        // (D) ルーム個別の振り分けルールだけを評価し、マッチしたルールも一緒に取得する.
        $match = self::findRoomByRoutingRulesWithRule($thread, $fromAddress);
        if (!$match) return null;
        [$room, $rule] = $match;

        try {
            // pivot に「どのルールでマッチしたか」も書き残す.
            // 既存ピボットがあっても上書きしないよう sync* 系は使わず、明示的に存在チェック.
            $existing = \Illuminate\Support\Facades\DB::table('chat_room_thread')
                ->where('chat_room_id', $room->id)
                ->where('email_thread_id', $thread->id)
                ->exists();
            if (!$existing) {
                \Illuminate\Support\Facades\DB::table('chat_room_thread')->insert([
                    'chat_room_id'    => $room->id,
                    'email_thread_id' => $thread->id,
                    'matched_rule_type'    => $rule?->type,
                    'matched_rule_pattern' => $rule?->pattern,
                    'matched_at'           => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $room->touch();
        } catch (\Throwable $e) {
            Log::warning('ChatRoomAutoBundler::bundleThread failed', [
                'thread_id' => $thread->id,
                'room_id'   => $room->id,
                'error'     => $e->getMessage(),
            ]);
            return null;
        }
        return $room;
    }

    /**
     * findRoomByRoutingRules の拡張版. ヒットした ChatRoom と ChatRoomRoutingRule の組を返す.
     * (旧 findRoomByRoutingRules は ChatRoom のみ返すため互換のため残してある.)
     *
     * 評価は ChatRoomRoutingRule::matches() に委譲. これにより:
     *   - conditions ツリーがあれば再帰評価 (AND/OR ネスト)
     *   - 無ければレガシー単一条件 (type+pattern)
     * を意識せずに済む.
     */
    public static function findRoomByRoutingRulesWithRule(EmailThread $thread, ?string $fromAddress = null): ?array
    {
        $email = self::resolveEvaluationEmail($thread, $fromAddress);
        if (!$email) return null;

        $rules = \App\Models\ChatRoomRoutingRule::query()
            ->where('enabled', true)
            ->whereHas('chatRoom', fn($q) => $q->where('is_private', false))
            ->with('chatRoom')
            ->get();
        if ($rules->isEmpty()) return null;

        foreach ($rules as $r) {
            if ($r->matches($email)) {
                $r->recordHit();
                return [$r->chatRoom, $r];
            }
        }
        return null;
    }

    /**
     * ルール評価で使う Email インスタンスを返す.
     *
     * - 最新メールがあればそれをそのまま使う.
     * - 無い場合 (= 新スレッド作成直後など): $fromAddress と thread.subject から
     *   in-memory な Email インスタンスを組み立てる. matches() は from_address /
     *   subject / to_address / cc / bcc しか見ないので、最小限の属性で動く.
     */
    protected static function resolveEvaluationEmail(EmailThread $thread, ?string $fromAddress): ?\App\Models\Email
    {
        try {
            $latest = $thread->latestEmail()->first();
            if ($latest) return $latest;
        } catch (\Throwable) {}
        // フォールバック: 合成 Email (DB 未保存).
        $e = new \App\Models\Email();
        $e->from_address = $fromAddress ?? '';
        $e->to_address   = '';
        $e->cc           = null;
        $e->bcc          = null;
        $e->subject      = (string) $thread->subject;
        return $e;
    }

    /**
     * 振り分けルール (D) で最初にマッチした共有ルームを返す。
     *
     * 評価対象は thread の latestEmail (新着メールから呼ばれた直後はまだ insert 前なので
     * 呼び出し側で $fromAddress + $thread->subject を渡しておくのが推奨)。
     *
     * 同一ルームで複数ルールがヒットしても 1 回だけマッチさせる。
     */
    public static function findRoomByRoutingRules(EmailThread $thread, ?string $fromAddress = null): ?ChatRoom
    {
        // findRoomByRoutingRulesWithRule に委譲. ChatRoom のみが必要な旧 API 経路.
        $hit = self::findRoomByRoutingRulesWithRule($thread, $fromAddress);
        return $hit[0] ?? null;
    }

    /**
     * 振り分けルール 1 件を既存メール (の所属スレッド) に遡及適用してまとめて取り込む。
     * 戻り値: 新たに bundle されたスレッド件数。
     *
     * TO_CONTAINS は to_address だけでなく cc / bcc も対象にする (= 「そのアドレスが
     * このメールに含まれているか」という直感的な意味合いに合わせる)。
     */
    public static function applyRoutingRuleBackfill(ChatRoom $room, \App\Models\ChatRoomRoutingRule $rule): int
    {
        if (!$rule->enabled) return 0;

        // ネスト条件 (AND/OR) のルールは粗 LIKE フィルタ + PHP 側 matches() で精密判定する.
        // 旧経路 (単一条件) は従来の type 別 SQL を使うので、ここで分岐.
        if (is_array($rule->conditions) && !empty($rule->conditions)) {
            return self::applyNestedRuleBackfill($room, $rule);
        }

        $pattern = (string) $rule->pattern;
        if ($pattern === '') return 0;

        $emailQuery = \App\Models\Email::query()->whereNotNull('thread_id');
        switch ($rule->type) {
            case \App\Models\ChatRoomRoutingRule::TYPE_FROM_ADDRESS:
                $emailQuery->whereRaw('LOWER(from_address) = ?', [mb_strtolower($pattern)]);
                break;
            case \App\Models\ChatRoomRoutingRule::TYPE_FROM_DOMAIN:
                $emailQuery->whereRaw('LOWER(from_address) LIKE ?', ['%@' . mb_strtolower($pattern)]);
                break;
            case \App\Models\ChatRoomRoutingRule::TYPE_SUBJECT_CONTAINS:
                $emailQuery->where('subject', 'like', '%' . $pattern . '%');
                break;
            case \App\Models\ChatRoomRoutingRule::TYPE_TO_CONTAINS:
                $emailQuery->where(function ($q) use ($pattern) {
                    $q->where('to_address', 'like', '%' . $pattern . '%')
                      ->orWhere('cc',  'like', '%' . $pattern . '%')
                      ->orWhere('bcc', 'like', '%' . $pattern . '%');
                });
                break;
            // ★ any_address / any_domain: From / To / Cc / Bcc を横断検索 (ユーザ要望対応).
            //    DB レイヤでは SQL の LIKE で粗くフィルタしてから、Eloquent モデル側の
            //    matches() で精密な完全一致を再チェックして false-positive を捨てる.
            //    粗フィルタにしておくのは、To/Cc 文字列内に "x@y.com, x2@y.com" のように
            //    複数アドレスが入っていて単純 LIKE では誤マッチが起こりうるため.
            case \App\Models\ChatRoomRoutingRule::TYPE_ANY_ADDRESS:
                $needle = mb_strtolower($pattern);
                $emailQuery->where(function ($q) use ($needle) {
                    $q->whereRaw('LOWER(from_address) = ?', [$needle])
                      ->orWhereRaw('LOWER(to_address) LIKE ?', ['%' . $needle . '%'])
                      ->orWhereRaw('LOWER(cc) LIKE ?',         ['%' . $needle . '%'])
                      ->orWhereRaw('LOWER(bcc) LIKE ?',        ['%' . $needle . '%']);
                });
                break;
            case \App\Models\ChatRoomRoutingRule::TYPE_ANY_DOMAIN:
                $needle = ltrim(mb_strtolower($pattern), '@');
                $atNeedle = '@' . $needle;
                $emailQuery->where(function ($q) use ($atNeedle) {
                    $q->whereRaw('LOWER(from_address) LIKE ?', ['%' . $atNeedle])
                      ->orWhereRaw('LOWER(to_address) LIKE ?', ['%' . $atNeedle . '%'])
                      ->orWhereRaw('LOWER(cc) LIKE ?',         ['%' . $atNeedle . '%'])
                      ->orWhereRaw('LOWER(bcc) LIKE ?',        ['%' . $atNeedle . '%']);
                });
                break;
            default:
                return 0;
        }
        // any_address / any_domain は SQL LIKE の粗マッチを通過した行をモデル側 matches() で再検証.
        // (例: "abc@example.com" を any_address 検索で誤って "abcd@example.com" にヒットさせないため)
        $isPostFilterNeeded = in_array($rule->type, [
            \App\Models\ChatRoomRoutingRule::TYPE_ANY_ADDRESS,
            \App\Models\ChatRoomRoutingRule::TYPE_ANY_DOMAIN,
        ], true);
        if ($isPostFilterNeeded) {
            $candidateEmailIds = $emailQuery->distinct()->pluck('id')->all();
            if (empty($candidateEmailIds)) return 0;
            $threadIds = [];
            \App\Models\Email::whereIn('id', $candidateEmailIds)->chunkById(500, function ($emails) use (&$threadIds, $rule) {
                foreach ($emails as $e) {
                    if ($rule->matches($e)) {
                        $threadIds[$e->thread_id] = true;
                    }
                }
            });
            $threadIds = array_keys($threadIds);
        } else {
            $threadIds = $emailQuery->distinct()->pluck('thread_id')->all();
        }
        if (empty($threadIds)) return 0;

        // ピボットを 3 群に分けて扱う:
        //   - 既存・監査列既に入っている  → 何もしない (他のルールでマッチ済の方が古いので尊重)
        //   - 既存・監査列が NULL          → UPDATE で監査列を埋める (手動で追加 or 分割で継承された pair が
        //                                    実は今のルールでも当たることを表に出す)
        //   - 未紐付け                    → INSERT で新規 bundle (監査列も付ける)
        $existingPivots = \Illuminate\Support\Facades\DB::table('chat_room_thread')
            ->where('chat_room_id', $room->id)
            ->whereIn('email_thread_id', $threadIds)
            ->get(['email_thread_id', 'matched_rule_type']);

        $existingFilledIds  = []; // 既に監査列がある → 触らない
        $existingNullIds    = []; // 監査列 NULL → UPDATE 対象
        foreach ($existingPivots as $p) {
            if ($p->matched_rule_type === null || $p->matched_rule_type === '') {
                $existingNullIds[] = (int) $p->email_thread_id;
            } else {
                $existingFilledIds[] = (int) $p->email_thread_id;
            }
        }
        $allExistingIds = array_merge($existingFilledIds, $existingNullIds);
        $newIds = array_values(array_diff($threadIds, $allExistingIds));
        $now = now();

        // (a) 既存 NULL 行の監査列を UPDATE
        if (!empty($existingNullIds)) {
            \Illuminate\Support\Facades\DB::table('chat_room_thread')
                ->where('chat_room_id', $room->id)
                ->whereIn('email_thread_id', $existingNullIds)
                ->whereNull('matched_rule_type') // 二重実行レース対策
                ->update([
                    'matched_rule_type'    => $rule->type,
                    'matched_rule_pattern' => $rule->pattern,
                    'matched_at'           => $now,
                    'updated_at'           => $now,
                ]);
        }

        // (b) 未紐付け分を INSERT
        if (!empty($newIds)) {
            $rows = [];
            foreach ($newIds as $tid) {
                $rows[] = [
                    'chat_room_id'         => $room->id,
                    'email_thread_id'      => $tid,
                    'matched_rule_type'    => $rule->type,
                    'matched_rule_pattern' => $rule->pattern,
                    'matched_at'           => $now,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ];
            }
            \Illuminate\Support\Facades\DB::table('chat_room_thread')->insert($rows);
        }

        // 戻り値は「新規 + 更新」の合計 (上位の UI は「取り込み件数」として toast に出す).
        return count($newIds) + count($existingNullIds);
    }

    /**
     * ネスト条件ルール (AND/OR) の遡及適用.
     *
     * 戦略:
     *   1. ツリーを全リーフ条件に展開し、各リーフが SQL LIKE で生むかもしれない候補 email_id を
     *      OR 結合した粗フィルタクエリを作る. (AND ノードでも個別リーフは満たしうるので OR で十分.)
     *   2. 候補 email を chunk しつつ $rule->matches() で正確にネスト評価して thread_id を集める.
     *   3. その後の pivot 反映 (INSERT / 監査列 UPDATE) は単一条件版と同じロジックを使う.
     *
     * ※ AND が厳しい条件で粗フィルタが大量にヒットすると I/O が増えるが、業務メール量では現実的.
     *    将来パフォーマンスが問題になる場合は AND 子の全リーフを intersect する SQL に切り替える.
     */
    protected static function applyNestedRuleBackfill(ChatRoom $room, \App\Models\ChatRoomRoutingRule $rule): int
    {
        $tree = $rule->conditions;
        $leaves = \App\Models\ChatRoomRoutingRule::flattenLeaves($tree);
        if (empty($leaves)) return 0;

        // (1) 粗 LIKE クエリ: 各リーフが返しうる email を OR で取る.
        $emailQuery = \App\Models\Email::query()->whereNotNull('thread_id');
        $emailQuery->where(function ($outer) use ($leaves) {
            foreach ($leaves as $leaf) {
                $type    = (string) $leaf['type'];
                $pattern = (string) $leaf['pattern'];
                if ($pattern === '') continue;
                $outer->orWhere(function ($q) use ($type, $pattern) {
                    self::applyLeafLikeFilter($q, $type, $pattern);
                });
            }
        });

        // (2) PHP 側で正確にツリー評価して thread_id を集める.
        $candidateIds = $emailQuery->distinct()->pluck('id')->all();
        if (empty($candidateIds)) return 0;
        $threadIdsHash = [];
        \App\Models\Email::whereIn('id', $candidateIds)->chunkById(500, function ($emails) use (&$threadIdsHash, $rule) {
            foreach ($emails as $e) {
                if ($rule->matches($e)) {
                    $threadIdsHash[$e->thread_id] = true;
                }
            }
        });
        $threadIds = array_keys($threadIdsHash);
        if (empty($threadIds)) return 0;

        // (3) ピボット反映: 単一条件版と同じロジック (3 群分け + INSERT / UPDATE).
        $existingPivots = \Illuminate\Support\Facades\DB::table('chat_room_thread')
            ->where('chat_room_id', $room->id)
            ->whereIn('email_thread_id', $threadIds)
            ->get(['email_thread_id', 'matched_rule_type']);

        $existingFilledIds = [];
        $existingNullIds   = [];
        foreach ($existingPivots as $p) {
            if ($p->matched_rule_type === null || $p->matched_rule_type === '') {
                $existingNullIds[] = (int) $p->email_thread_id;
            } else {
                $existingFilledIds[] = (int) $p->email_thread_id;
            }
        }
        $allExistingIds = array_merge($existingFilledIds, $existingNullIds);
        $newIds = array_values(array_diff($threadIds, $allExistingIds));
        $now = now();

        // 監査列に保存する代表値: ネスト条件は文字列化できないので、type='nested' + pattern=ルートロジック.
        // 詳細はルール本体の conditions を見れば取れる.
        $auditType    = $rule->type ?: 'nested';
        $auditPattern = $rule->pattern ?: ('logic=' . ($tree['logic'] ?? 'or'));

        if (!empty($existingNullIds)) {
            \Illuminate\Support\Facades\DB::table('chat_room_thread')
                ->where('chat_room_id', $room->id)
                ->whereIn('email_thread_id', $existingNullIds)
                ->whereNull('matched_rule_type')
                ->update([
                    'matched_rule_type'    => $auditType,
                    'matched_rule_pattern' => $auditPattern,
                    'matched_at'           => $now,
                    'updated_at'           => $now,
                ]);
        }
        if (!empty($newIds)) {
            $rows = [];
            foreach ($newIds as $tid) {
                $rows[] = [
                    'chat_room_id'         => $room->id,
                    'email_thread_id'      => $tid,
                    'matched_rule_type'    => $auditType,
                    'matched_rule_pattern' => $auditPattern,
                    'matched_at'           => $now,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ];
            }
            \Illuminate\Support\Facades\DB::table('chat_room_thread')->insert($rows);
        }

        return count($newIds) + count($existingNullIds);
    }

    /**
     * リーフ条件用の粗 LIKE フィルタを Query ビルダに適用する.
     * 単一条件版の applyRoutingRuleBackfill のスイッチ文と同じ条件を切り出したもの.
     */
    protected static function applyLeafLikeFilter($q, string $type, string $pattern): void
    {
        switch ($type) {
            case \App\Models\ChatRoomRoutingRule::TYPE_FROM_ADDRESS:
                $q->whereRaw('LOWER(from_address) = ?', [mb_strtolower($pattern)]);
                break;
            case \App\Models\ChatRoomRoutingRule::TYPE_FROM_DOMAIN:
                $q->whereRaw('LOWER(from_address) LIKE ?', ['%@' . mb_strtolower($pattern)]);
                break;
            case \App\Models\ChatRoomRoutingRule::TYPE_SUBJECT_CONTAINS:
                $q->where('subject', 'like', '%' . $pattern . '%');
                break;
            case \App\Models\ChatRoomRoutingRule::TYPE_TO_CONTAINS:
                $q->where(function ($qq) use ($pattern) {
                    $qq->where('to_address', 'like', '%' . $pattern . '%')
                       ->orWhere('cc',  'like', '%' . $pattern . '%')
                       ->orWhere('bcc', 'like', '%' . $pattern . '%');
                });
                break;
            case \App\Models\ChatRoomRoutingRule::TYPE_ANY_ADDRESS:
                $needle = mb_strtolower($pattern);
                $q->where(function ($qq) use ($needle) {
                    $qq->whereRaw('LOWER(from_address) = ?', [$needle])
                       ->orWhereRaw('LOWER(to_address) LIKE ?', ['%' . $needle . '%'])
                       ->orWhereRaw('LOWER(cc) LIKE ?',         ['%' . $needle . '%'])
                       ->orWhereRaw('LOWER(bcc) LIKE ?',        ['%' . $needle . '%']);
                });
                break;
            case \App\Models\ChatRoomRoutingRule::TYPE_ANY_DOMAIN:
                $needle = ltrim(mb_strtolower($pattern), '@');
                $atNeedle = '@' . $needle;
                $q->where(function ($qq) use ($atNeedle) {
                    $qq->whereRaw('LOWER(from_address) LIKE ?', ['%' . $atNeedle])
                       ->orWhereRaw('LOWER(to_address) LIKE ?', ['%' . $atNeedle . '%'])
                       ->orWhereRaw('LOWER(cc) LIKE ?',         ['%' . $atNeedle . '%'])
                       ->orWhereRaw('LOWER(bcc) LIKE ?',        ['%' . $atNeedle . '%']);
                });
                break;
            default:
                // 未知の type は何もマッチさせない.
                $q->whereRaw('1 = 0');
        }
    }

    /**
     * 指定ルームの全 enabled ルールを既存メールへまとめて遡及適用。
     * 戻り値: ルール毎の取り込みスレッド数の合計 (重複は内部の syncWithoutDetaching で吸収).
     */
    public static function reapplyAllRulesForRoom(ChatRoom $room): int
    {
        if ($room->is_private) return 0;
        $rules = $room->routingRules()->where('enabled', true)->get();
        if ($rules->isEmpty()) return 0;
        // applyRoutingRuleBackfill が「新規 INSERT + 既存 NULL 行の監査列 UPDATE」の合算を返すので
        // before/after 件数差ではなく戻り値を素直に足す.
        $total = 0;
        foreach ($rules as $rule) {
            try {
                $total += self::applyRoutingRuleBackfill($room, $rule);
            } catch (\Throwable $e) {
                Log::warning('reapplyAllRulesForRoom: rule failed', [
                    'room_id' => $room->id, 'rule_id' => $rule->id, 'error' => $e->getMessage(),
                ]);
            }
        }
        return $total;
    }

    /**
     * 送信元アドレスから「過去にその送信元のメールが紐付いていた共有ルーム」を 1 件返す。
     *
     * ロジック:
     *   1. emails テーブルから from_address = $fromAddress のスレッド ID 集合を抽出
     *      ($excludeThreadId は今まさに振り分けようとしているスレッドなので除外する)
     *   2. それらのスレッドがどの (共有) ルームに紐付いているか chat_room_thread から逆引き
     *   3. 結果が **ちょうど 1 件** → そのルーム
     *      複数 → 曖昧 (社内担当者など複数ルームに跨る送信元) として **何もしない**
     *      0 件 → null
     *
     * 誤爆対策:
     *   - 完全アドレス一致のみ (例: gmail.com 全部に飛ぶような domain 一致はしない)
     *   - 個人ルームは対象外
     *   - 「複数ルームに散らばっている送信者」は曖昧として除外
     *     (内部担当者が複数顧客ルームに紐付いているケースで誤爆を防ぐ)
     *   - $excludeThreadId を渡せば「自分自身のスレッドが既に bundle されていることで
     *     ヒットしてしまう自己参照」を防げる。
     */
    public static function findRoomByPriorSender(string $fromAddress, ?int $excludeThreadId = null): ?ChatRoom
    {
        $fromAddress = trim($fromAddress);
        if ($fromAddress === '') return null;

        try {
            $threadIds = \App\Models\Email::query()
                ->where('from_address', $fromAddress)
                ->when($excludeThreadId, fn($q) => $q->where('thread_id', '!=', $excludeThreadId))
                ->whereNotNull('thread_id')
                ->distinct()
                ->pluck('thread_id')
                ->all();
            if (empty($threadIds)) return null;

            $rooms = ChatRoom::query()
                ->where('is_private', false)
                ->whereHas('bundledThreads', fn($q) => $q->whereIn('email_threads.id', $threadIds))
                ->get(['id', 'name', 'updated_at']);

            // 1 ルームだけに紐付いている送信者だけが「ユニークに対応付けられている」と判断する.
            // 複数ルームに分散している場合 (社内担当者 / 共通の問い合わせ窓口など) は
            // どこへ振り分けるのが正解か曖昧なので自動振り分けはしない.
            if ($rooms->count() !== 1) return null;
            return $rooms->first();
        } catch (\Throwable $e) {
            Log::warning('ChatRoomAutoBundler::findRoomByPriorSender failed', [
                'from_address' => $fromAddress,
                'error'        => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 件名にルーム名が部分文字列として含まれる「共有ルーム」を 1 件返す。
     * - ルーム名 3 文字未満は誤爆の温床なので除外。
     * - 複数ヒット時は名前が長い (= より具体的) ルームを優先。
     * - 大文字小文字 / 全角空白は無視。
     */
    public static function findRoomBySubject(?string $subject): ?ChatRoom
    {
        if ($subject === null || $subject === '') return null;
        $normSubject = self::normalize($subject);
        if ($normSubject === '') return null;

        $rooms = ChatRoom::where('is_private', false)
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'updated_at']);

        $matches = [];
        foreach ($rooms as $r) {
            $k = self::normalize($r->name);
            // 短すぎるルーム名は誤爆防止のためスキップ (2 文字以下)
            if (mb_strlen($k) < 3) continue;
            if (mb_strpos($normSubject, $k) !== false) {
                $matches[] = $r;
            }
        }
        if (empty($matches)) return null;
        // ルーム名が長い順 (より具体的なマッチを優先)
        usort($matches, fn($a, $b) => mb_strlen($b->name) <=> mb_strlen($a->name));
        return $matches[0];
    }

    /**
     * 顧客名と一致する共有ルームを 1 件返す (なければ null)。
     */
    public static function findMatchingRoom(string $normalizedName): ?ChatRoom
    {
        if ($normalizedName === '') return null;
        // 全件取得して PHP 側で比較する (件数は通常数百なので問題ない & SQL の
        // 大文字小文字/全角扱いに依存しない)。
        $rooms = ChatRoom::where('is_private', false)->orderByDesc('updated_at')->get(['id', 'name', 'updated_at']);
        foreach ($rooms as $r) {
            if (self::normalize($r->name) === $normalizedName) {
                return $r;
            }
        }
        return null;
    }

    /**
     * 旧 API 互換のスタブ (no-op)。
     *
     * かつては「顧客名 == ルーム名」のヒューリスティックで顧客のスレッドを自動振り分けしていたが、
     * 「ユーザによるフィルタがあって初めて振り分け」方針に変更したため何もしない。
     * 呼び出し元 (CustomerController) を残したまま安全に互換維持できるようにシグネチャは維持。
     */
    public static function bundleByCustomer(Customer $customer): array
    {
        return [null, 0];
    }

    /**
     * ルームに登録されている全ての振り分けルール (D) を既存メールへ遡及適用。
     * 戻り値: 取り込んだスレッド数 (重複は 1 件として集計)。
     *
     * ★ 旧バージョンの (A) 顧客名一致 / (B) 件名包含 / (C) 送信履歴 マッチは廃止。
     *   このメソッドは「ユーザが設定したルールだけ」を再評価する。
     */
    public static function bundleByRoom(ChatRoom $room): int
    {
        if ($room->is_private) return 0;
        $rules = $room->routingRules()->where('enabled', true)->get();
        if ($rules->isEmpty()) return 0;
        $before = $room->bundledThreads()->count();
        foreach ($rules as $rule) {
            try {
                self::applyRoutingRuleBackfill($room, $rule);
            } catch (\Throwable $e) {
                Log::warning('bundleByRoom: applyRoutingRuleBackfill failed', [
                    'room_id' => $room->id, 'rule_id' => $rule->id, 'error' => $e->getMessage(),
                ]);
            }
        }
        $after = $room->bundledThreads()->count();
        return max(0, $after - $before);
    }

    /** 旧: ヒューリスティックなルーム名一致版 (現在は未使用。 内部リファレンス用に残す) */
    public static function bundleByRoomLegacyHeuristic(ChatRoom $room): int
    {
        if ($room->is_private) return 0; // 個人ルームは対象外
        $key = self::normalize($room->name);
        if ($key === '') return 0;

        $threadIds = [];

        // (A) 顧客名一致
        $customers = Customer::query()->get(['id', 'name']);
        $matchedCustomerIds = $customers->filter(fn($c) => self::normalize($c->name) === $key)->pluck('id')->all();
        if (!empty($matchedCustomerIds)) {
            $threadIds = array_merge(
                $threadIds,
                EmailThread::whereIn('customer_id', $matchedCustomerIds)->pluck('id')->all()
            );
        }

        // (B) 件名包含マッチ (ルーム名 3 文字以上の場合のみ)
        if (mb_strlen($key) >= 3) {
            // LIKE は正規化前提では完璧ではないが、件名は元々ユーザ生成の自然文字列なので
            // ここは元の文字列で LIKE %name% を当てる (大半の日本語タイトルで動作する)
            $rawName = trim($room->name);
            if ($rawName !== '') {
                $threadIds = array_merge(
                    $threadIds,
                    EmailThread::where('subject', 'like', '%' . $rawName . '%')->pluck('id')->all()
                );
            }
        }

        // (C) 送信履歴マッチ: 既にこのルームに紐付いているスレッドの from_address のうち、
        //     「他の共有ルームにも出現していない (= このルーム専属)」アドレスだけを採用する。
        //     そのアドレスから来た他スレッドも同じルームへ取り込む。
        //
        //     なぜユニーク制約を入れるか:
        //       - 社内担当者 (例: helpdesk@my-company) が複数顧客ルームに紐付くケースで
        //         「全顧客ルームに彼の全メールが入る」誤爆を防ぐため。
        //       - 「このアドレス = このルーム専用」と確定できる場合だけ拡張する保守的方針。
        try {
            $currentBundledIds = $room->bundledThreads()->pluck('email_threads.id')->all();
            if (!empty($currentBundledIds)) {
                $fromAddresses = \App\Models\Email::query()
                    ->whereIn('thread_id', $currentBundledIds)
                    ->whereNotNull('from_address')
                    ->where('from_address', '!=', '')
                    ->distinct()
                    ->pluck('from_address')
                    ->all();
                // 各 from_address について「何個の共有ルームに紐付いているか」を数え、
                // 1 (= この room だけ) のものに絞る.
                $uniqueAddresses = [];
                foreach ($fromAddresses as $addr) {
                    $threadIdsForAddr = \App\Models\Email::where('from_address', $addr)
                        ->whereNotNull('thread_id')
                        ->distinct()
                        ->pluck('thread_id')
                        ->all();
                    if (empty($threadIdsForAddr)) continue;
                    $roomCount = ChatRoom::where('is_private', false)
                        ->whereHas('bundledThreads', fn($q) => $q->whereIn('email_threads.id', $threadIdsForAddr))
                        ->count();
                    if ($roomCount === 1) $uniqueAddresses[] = $addr;
                }
                if (!empty($uniqueAddresses)) {
                    $relatedThreadIds = \App\Models\Email::query()
                        ->whereIn('from_address', $uniqueAddresses)
                        ->whereNotNull('thread_id')
                        ->distinct()
                        ->pluck('thread_id')
                        ->all();
                    $threadIds = array_merge($threadIds, $relatedThreadIds);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ChatRoomAutoBundler::bundleByRoom (sender-history) failed', [
                'room_id' => $room->id, 'error' => $e->getMessage(),
            ]);
        }

        $threadIds = array_values(array_unique(array_map('intval', $threadIds)));
        if (empty($threadIds)) return 0;
        try {
            $room->bundledThreads()->syncWithoutDetaching($threadIds);
            $room->touch();
        } catch (\Throwable $e) {
            Log::warning('ChatRoomAutoBundler::bundleByRoom failed', [
                'room_id' => $room->id,
                'error'   => $e->getMessage(),
            ]);
            return 0;
        }
        return count($threadIds);
    }

    /**
     * 既存データの一括バックフィル。
     * 共有ルームごとに bundleByRoom() を呼ぶ。
     * ユーザ要望により bundleByRoom はルームの routing_rules のみ評価するので、
     * ルールを 1 件も持たないルームは何も取り込まれない (= 安全)。
     * 戻り値: ['matched_pairs' => N, 'bundled_threads' => M, 'details' => [...]]
     */
    public static function backfillAll(): array
    {
        $rooms = ChatRoom::where('is_private', false)->orderBy('id')->get(['id', 'name']);

        $matchedPairs   = 0;
        $totalThreads   = 0;
        $details        = [];

        foreach ($rooms as $r) {
            // 集計前の bundle 数 (差分で「新規取り込み数」を取りたい)
            $before = $r->bundledThreads()->count();
            $n = self::bundleByRoom($r);
            if ($n === 0) continue;
            $after = $r->bundledThreads()->count();
            $delta = max(0, $after - $before);
            if ($delta === 0) {
                // 全件が既に紐付け済みだった (= 冪等で何も変わらず)
                continue;
            }
            $matchedPairs++;
            $totalThreads += $delta;
            $details[] = [
                'room_id'     => $r->id,
                'room_name'   => $r->name,
                'attempted'   => $n,
                'newly_added' => $delta,
            ];
        }

        return [
            'matched_pairs'   => $matchedPairs,
            'bundled_threads' => $totalThreads,
            'details'         => $details,
        ];
    }
}
