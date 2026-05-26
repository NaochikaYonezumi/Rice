<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\EmailThread;
use App\Models\MailBlockRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 迷惑メール判定ルール (送信元アドレス/ドメイン, 件名/本文キーワード) の CRUD と、
 * メール一覧から「これを迷惑メールに分類」する手動操作のエンドポイント。
 */
class MailBlockRuleController extends Controller
{
    /** 迷惑メール設定画面 (Blade) */
    public function page()
    {
        return view('settings.spam');
    }

    /** 全ルールを返す (新しい順)。フィルタなし */
    public function index(Request $request): JsonResponse
    {
        $rules = MailBlockRule::orderByDesc('id')->get()->map(fn ($r) => $this->serialize($r));
        return response()->json([
            'rules' => $rules,
            'types' => [
                // 送信元
                ['key' => MailBlockRule::TYPE_SENDER_ADDRESS,     'label' => '送信元アドレス',         'placeholder' => 'spam@example.com',         'hint' => 'From アドレス完全一致 (大文字小文字無視)'],
                ['key' => MailBlockRule::TYPE_SENDER_DOMAIN,      'label' => '送信元ドメイン',         'placeholder' => 'spam.example.com',         'hint' => 'From の @ 以降と完全一致'],
                // 宛先 (To / Cc / Bcc 横断)
                ['key' => MailBlockRule::TYPE_RECIPIENT_ADDRESS,  'label' => '宛先アドレス (To/Cc/Bcc)', 'placeholder' => 'list@example.com',         'hint' => 'To/Cc/Bcc のいずれかと完全一致'],
                ['key' => MailBlockRule::TYPE_RECIPIENT_DOMAIN,   'label' => '宛先ドメイン (To/Cc/Bcc)', 'placeholder' => 'example.com',              'hint' => 'To/Cc/Bcc のいずれかのドメインと完全一致'],
                ['key' => MailBlockRule::TYPE_RECIPIENT_CONTAINS, 'label' => '宛先に含む (To/Cc/Bcc)',  'placeholder' => 'support@',                 'hint' => 'To/Cc/Bcc 全体に部分一致'],
                // 件名 / 本文
                ['key' => MailBlockRule::TYPE_SUBJECT_KEYWORD,    'label' => '件名キーワード',         'placeholder' => '副業, 当選, …',            'hint' => '件名に部分一致 (大文字小文字無視)'],
                ['key' => MailBlockRule::TYPE_BODY_KEYWORD,       'label' => '本文キーワード',         'placeholder' => '怪しいフレーズ',           'hint' => '本文に部分一致'],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // (B) conditions が渡されたらネスト条件ルールとして作成. (A) なら従来の単一条件.
        // ChatRoomRoutingRule と同じ二経路 API. UI 側はモードで切り替える.
        $rawConditions = $request->input('conditions');
        if (is_array($rawConditions)) {
            try {
                $normTree = MailBlockRule::validateAndNormalizeConditions($rawConditions);
            } catch (\InvalidArgumentException $e) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
            }
            // 一覧表示や旧 legacy 経路の代表値として first leaf を type/pattern に backfill.
            $firstLeaf = MailBlockRule::firstLeaf($normTree) ?? ['type' => MailBlockRule::TYPE_SUBJECT_KEYWORD, 'pattern' => ''];
            $rule = new MailBlockRule();
            $rule->type       = $firstLeaf['type'];
            $rule->pattern    = $firstLeaf['pattern'];
            $rule->logic      = $normTree['logic'] ?? 'or';
            $rule->conditions = $normTree;
            $rule->enabled    = $request->boolean('enabled', true);
            $rule->created_by = Auth::id();
            $rule->save();
            return response()->json(['status' => 'ok', 'rule' => $this->serialize($rule->fresh())]);
        }

        // (A) レガシー単一条件パス.
        $data = $request->validate([
            'type'    => ['required', 'in:' . implode(',', MailBlockRule::TYPES)],
            'pattern' => ['required', 'string', 'max:255'],
            'enabled' => ['nullable', 'boolean'],
        ]);
        $pattern = MailBlockRule::normalizePattern($data['type'], $data['pattern']);
        if ($pattern === '') {
            return response()->json(['status' => 'error', 'message' => 'pattern が空です'], 422);
        }
        // 重複統合: レガシー単一条件ルール (conditions が NULL もしくは 1 リーフ OR グループ)
        // に対してのみ firstOrNew で重複を吸収する. ネストルールは別物として保存する.
        $rule = MailBlockRule::query()
            ->where('type', $data['type'])
            ->where('pattern', $pattern)
            ->where(function ($q) {
                $q->whereNull('conditions')
                  ->orWhereRaw("JSON_EXTRACT(conditions, '$.items[1]') IS NULL"); // items が 1 件のみ ≒ 単一条件相当
            })
            ->first();
        if (!$rule) $rule = new MailBlockRule(['type' => $data['type'], 'pattern' => $pattern]);
        $rule->enabled    = $data['enabled'] ?? true;
        $rule->created_by = $rule->created_by ?: Auth::id();
        // 単一条件ルールも内部表現としては 1 リーフ OR グループの conditions を持たせる
        // (matcher を統一して常に conditions を読めば良い形にする).
        $rule->logic      = 'or';
        $rule->conditions = ['logic' => 'or', 'items' => [['type' => $data['type'], 'pattern' => $pattern]]];
        $rule->save();
        return response()->json(['status' => 'ok', 'rule' => $this->serialize($rule->fresh())]);
    }

    public function update(Request $request, MailBlockRule $rule): JsonResponse
    {
        $this->authorizeMutate($rule);
        $data = $request->validate([
            'pattern' => ['nullable', 'string', 'max:255'],
            'enabled' => ['nullable', 'boolean'],
        ]);
        // conditions ツリー差し替え (リクエストに含まれていれば).
        $rawConditions = $request->input('conditions');
        if (is_array($rawConditions)) {
            try {
                $normTree = MailBlockRule::validateAndNormalizeConditions($rawConditions);
            } catch (\InvalidArgumentException $e) {
                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
            }
            $firstLeaf = MailBlockRule::firstLeaf($normTree) ?? ['type' => $rule->type, 'pattern' => ''];
            $rule->type       = $firstLeaf['type'];
            $rule->pattern    = $firstLeaf['pattern'];
            $rule->logic      = $normTree['logic'] ?? 'or';
            $rule->conditions = $normTree;
        }
        if (array_key_exists('pattern', $data) && $data['pattern'] !== null) {
            $rule->pattern = MailBlockRule::normalizePattern($rule->type, $data['pattern']);
            // 単一条件ルール (1 リーフ OR) の場合は conditions も同期更新する.
            if (is_array($rule->conditions) && isset($rule->conditions['items']) && count($rule->conditions['items']) === 1) {
                $rule->conditions = [
                    'logic' => $rule->conditions['logic'] ?? 'or',
                    'items' => [['type' => $rule->type, 'pattern' => $rule->pattern]],
                ];
            }
        }
        if (array_key_exists('enabled', $data) && $data['enabled'] !== null) {
            $rule->enabled = (bool) $data['enabled'];
        }
        $rule->save();
        return response()->json(['status' => 'ok', 'rule' => $this->serialize($rule)]);
    }

    public function destroy(MailBlockRule $rule): JsonResponse
    {
        $this->authorizeMutate($rule);
        $rule->delete();
        return response()->json(['status' => 'ok']);
    }

    /**
     * ルール編集/削除権限のチェック:
     * - 管理者は全ルールを操作可
     * - それ以外は自分が作成したルールのみ操作可
     */
    protected function authorizeMutate(MailBlockRule $rule): void
    {
        $user = Auth::user();
        if (!$user) abort(401);
        // isAdmin() メソッドがあれば管理者は素通り
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) return;
        if ($rule->created_by !== $user->id) {
            abort(403, 'このルールは他のユーザが作成したため編集できません。');
        }
    }

    /**
     * 指定スレッドを「迷惑メール」にマークする。
     * - thread.status を 'spam' に変更
     * - addRule=true (デフォルト) なら、ルールを自動登録する.
     *   rule_type / rule_pattern が指定された場合はそれを使用 (ルームと同様、UI から自由に選べる).
     *   未指定なら従来通り「最新メールの from_address」を sender_address として登録.
     */
    public function markThreadAsSpam(Request $request, EmailThread $thread): JsonResponse
    {
        $request->validate([
            'add_rule'     => 'nullable|boolean',
            'rule_type'    => ['nullable', 'in:' . implode(',', MailBlockRule::TYPES)],
            'rule_pattern' => ['nullable', 'string', 'max:255'],
        ]);
        $addRule     = $request->boolean('add_rule', true);
        $ruleType    = $request->input('rule_type', MailBlockRule::TYPE_SENDER_ADDRESS);
        $rulePattern = trim((string) $request->input('rule_pattern', ''));

        // status='spam' に切り替える際は spammed_at = now() を同時に立てる.
        // mail:purge-spam がこの spammed_at を基準に保持期間を判定するため.
        // 既に spam だった場合 (= spammed_at が既存) はそのまま温存して「最初に spam にした時刻」を起点にする.
        $now = now();
        $thread->update([
            'status'     => EmailThread::STATUS_SPAM,
            'spammed_at' => $thread->spammed_at ?: $now,
        ]);

        // マージ ソースのステータスも同じにそろえる (要望: target を完了/迷惑等にしたら
        // マージで吸収されたスレッドも同じ扱いにする)
        try {
            $sourceIds = \App\Models\ThreadMerge::where('target_thread_id', $thread->id)
                ->pluck('source_thread_id_original')->all();
            if (!empty($sourceIds)) {
                // 各ソーススレッドの「既存 spammed_at が無い行だけ now() で埋める」必要があるが、
                // 一括 UPDATE では COALESCE で安全に書く. SQLite / MySQL 共通.
                EmailThread::whereIn('id', $sourceIds)
                    ->update([
                        'status'     => EmailThread::STATUS_SPAM,
                        'spammed_at' => \Illuminate\Support\Facades\DB::raw('COALESCE(spammed_at, ' . \Illuminate\Support\Facades\DB::getPdo()->quote((string) $now) . ')'),
                    ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('markThreadAsSpam: cascade failed', [
                'thread_id' => $thread->id, 'error' => $e->getMessage(),
            ]);
        }

        $createdRule = null;
        if ($addRule) {
            // UI から明示的にパターンが渡されていればそれを優先 (件名 / 宛先 / CC など何でも).
            // 未指定なら従来挙動: 最新メールの from を sender_address / sender_domain として登録.
            $pattern = $rulePattern;
            if ($pattern === '') {
                $latest = Email::where('thread_id', $thread->id)
                    ->orderByDesc('received_at')
                    ->first();
                if ($latest && $latest->from_address) {
                    $pattern = $ruleType === MailBlockRule::TYPE_SENDER_DOMAIN
                        ? MailBlockRule::extractDomain($latest->from_address)
                        : $latest->from_address;
                }
            }
            $pattern = MailBlockRule::normalizePattern($ruleType, $pattern);
            if ($pattern !== '') {
                $createdRule = MailBlockRule::firstOrNew(['type' => $ruleType, 'pattern' => $pattern]);
                $createdRule->enabled    = true;
                $createdRule->created_by = $createdRule->created_by ?: Auth::id();
                $createdRule->save();
            }
        }

        return response()->json([
            'status'      => 'ok',
            'thread_id'   => $thread->id,
            'thread_status' => $thread->status,
            'created_rule' => $createdRule ? $this->serialize($createdRule) : null,
        ]);
    }

    /**
     * スレッドを迷惑メールから解除して inbox に戻す。
     * (関連ルールは触らない。手動で消したい場合は destroy で削除する)
     */
    public function unmarkThreadAsSpam(Request $request, EmailThread $thread): JsonResponse
    {
        // 迷惑メール解除時は spammed_at もクリア (= purge 対象から外す).
        $thread->update([
            'status'     => EmailThread::STATUS_INBOX,
            'spammed_at' => null,
        ]);
        // マージ ソースも同じく inbox に戻す
        try {
            $sourceIds = \App\Models\ThreadMerge::where('target_thread_id', $thread->id)
                ->pluck('source_thread_id_original')->all();
            if (!empty($sourceIds)) {
                EmailThread::whereIn('id', $sourceIds)
                    ->update([
                        'status'     => EmailThread::STATUS_INBOX,
                        'spammed_at' => null,
                    ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('unmarkThreadAsSpam: cascade failed', [
                'thread_id' => $thread->id, 'error' => $e->getMessage(),
            ]);
        }
        return response()->json(['status' => 'ok', 'thread_id' => $thread->id, 'thread_status' => $thread->status]);
    }

    protected function serialize(MailBlockRule $r): array
    {
        // 作成者名 (削除済みユーザは "(unknown)") を引いて返す
        $creator = null;
        if ($r->created_by) {
            $u = \App\Models\User::find($r->created_by);
            $creator = $u?->name ?? null;
        }
        return [
            'id'              => $r->id,
            'type'            => $r->type,
            'pattern'         => $r->pattern,
            // ネスト条件 (新). UI が type/pattern と conditions の両方を扱えるよう両方返す.
            'logic'           => $r->logic,
            'conditions'      => $r->conditions,
            'enabled'         => (bool) $r->enabled,
            'match_count'     => (int) $r->match_count,
            'last_matched_at' => $r->last_matched_at?->format('Y-m-d H:i'),
            'created_at'      => $r->created_at?->format('Y-m-d H:i'),
            'created_by'      => $r->created_by,
            'created_by_name' => $creator,
            // フロントで「自分が作ったルールか」を判定して操作可否に使う
            'is_mine'         => $r->created_by === \Illuminate\Support\Facades\Auth::id(),
        ];
    }
}
