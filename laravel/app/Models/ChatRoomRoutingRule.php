<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ルームごとの振り分けルール (パターン/フィルタ)。
 *
 * 1 ルームに複数ルール (OR 条件)。 1 件でもマッチした受信メールは、
 * そのルームへ自動 bundle される。
 *
 * type 体系:
 *   from_address     … From アドレス完全一致 (大文字小文字無視)
 *   from_domain      … From の @ 以降が一致
 *   subject_contains … 件名に部分一致
 *   to_contains      … To/Cc/Bcc に部分一致 (文字列含む)
 *
 *   ★ 新規 (ユーザ要望対応):
 *   any_address      … From / To / Cc / Bcc のいずれかに該当アドレス完全一致
 *   any_domain       … From / To / Cc / Bcc のいずれかのドメイン部が一致
 *
 *   "info1@example.com を含むメールは Acme チームに" のように、From にあろうと
 *   To/Cc にあろうと同じルームに振り分けたい時に any_address / any_domain を使う.
 */
class ChatRoomRoutingRule extends Model
{
    public const TYPE_FROM_ADDRESS     = 'from_address';
    public const TYPE_FROM_DOMAIN      = 'from_domain';
    public const TYPE_SUBJECT_CONTAINS = 'subject_contains';
    public const TYPE_TO_CONTAINS      = 'to_contains';
    public const TYPE_ANY_ADDRESS      = 'any_address';
    public const TYPE_ANY_DOMAIN       = 'any_domain';

    public const TYPES = [
        self::TYPE_ANY_ADDRESS,
        self::TYPE_ANY_DOMAIN,
        self::TYPE_FROM_ADDRESS,
        self::TYPE_FROM_DOMAIN,
        self::TYPE_SUBJECT_CONTAINS,
        self::TYPE_TO_CONTAINS,
    ];

    public const TYPE_LABELS = [
        self::TYPE_ANY_ADDRESS      => 'メールアドレス (From/To/Cc 全部)',
        self::TYPE_ANY_DOMAIN       => 'ドメイン (From/To/Cc 全部)',
        self::TYPE_FROM_ADDRESS     => '差出人アドレス (From のみ)',
        self::TYPE_FROM_DOMAIN      => '差出人ドメイン (From のみ)',
        self::TYPE_SUBJECT_CONTAINS => '件名に含む',
        self::TYPE_TO_CONTAINS      => '宛先 (To/Cc/Bcc) に含む',
    ];

    /**
     * AND/OR ネスト条件のサポート (新). conditions カラムはルートグループを保存.
     *
     * グループノード:  ['logic' => 'and'|'or', 'items' => [<node>, ...]]
     * リーフノード:    ['type'  => <TYPES>,    'pattern' => <string>]
     *
     * logic カラムは便宜上 conditions ルートの logic を冗長保存 (一覧クエリでの軽量フィルタ用).
     * conditions が NULL の場合はレガシー単一条件 (type+pattern) として評価する.
     */
    public const LOGIC_AND = 'and';
    public const LOGIC_OR  = 'or';
    public const LOGICS    = [self::LOGIC_AND, self::LOGIC_OR];

    /** ネスト深さの上限. 無制限ネストは UI 編集が破綻するので保存時に弾く. */
    public const MAX_DEPTH = 5;

    protected $fillable = [
        'chat_room_id', 'type', 'pattern', 'enabled',
        'match_count', 'last_matched_at', 'created_by_user_id',
        'logic', 'conditions',
    ];

    protected $casts = [
        'enabled'         => 'boolean',
        'match_count'     => 'integer',
        'last_matched_at' => 'datetime',
        // ネスト条件ツリー. 例:
        //   ['logic'=>'or','items'=>[
        //       ['logic'=>'and','items'=>[
        //           ['type'=>'from_domain','pattern'=>'foo.com'],
        //           ['type'=>'subject_contains','pattern'=>'お問い合わせ'],
        //       ]],
        //       ['type'=>'from_domain','pattern'=>'bar.com'],
        //   ]]
        'conditions'      => 'array',
    ];

    public function chatRoom(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class);
    }

    /**
     * 入力された pattern を type ごとに正規化 (空白除去 / 小文字化 等)。
     */
    public static function normalizePattern(string $type, string $pattern): string
    {
        $p = trim($pattern);
        if ($p === '') return '';
        // 完全一致系: 小文字化
        if (in_array($type, [self::TYPE_FROM_ADDRESS, self::TYPE_ANY_ADDRESS], true)) {
            return mb_strtolower($p);
        }
        // ドメイン系: 先頭 "@" を許容 + 小文字化
        if (in_array($type, [self::TYPE_FROM_DOMAIN, self::TYPE_ANY_DOMAIN], true)) {
            $p = ltrim($p, '@');
            return mb_strtolower($p);
        }
        // 部分一致系: そのまま (matches() 側で大文字小文字を吸収)
        return $p;
    }

    /**
     * "Foo <a@x.com>, b@y.com" などのヘッダ文字列から、メールアドレス部だけを配列で返す.
     * any_address / any_domain の判定で全参加者アドレス集合を作る時に使う.
     */
    public static function extractAddressList(string $raw): array
    {
        if (trim($raw) === '') return [];
        $parts = preg_split('/[,;\r\n]+/u', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') continue;
            if (preg_match('/<([^>]+)>/', $p, $m)) $p = trim($m[1]);
            if ($p !== '' && strpos($p, '@') !== false) $out[] = $p;
        }
        return $out;
    }

    /**
     * このルールが指定メールにマッチするかを判定。
     *
     * 優先順位:
     *   - conditions (JSON ツリー) が設定されていればそれを再帰評価する.
     *   - 無ければ従来通り単一条件 (type+pattern) で評価 (= レガシー後方互換).
     */
    public function matches(Email $email): bool
    {
        if (!$this->enabled) return false;
        $tree = $this->conditions;
        if (is_array($tree) && !empty($tree)) {
            return self::evaluateNode($tree, $email);
        }
        // レガシー: conditions 未設定なら type+pattern の単一条件で判定.
        return self::evaluateLeaf((string) $this->type, (string) $this->pattern, $email);
    }

    /**
     * ノードを再帰評価. グループノード (logic + items) と リーフノード (type + pattern) を扱う.
     * 空グループは false (= マッチしない) を返す.
     *
     *   グループ: { "logic": "and"|"or", "items": [<node>, ...] }
     *   リーフ:  { "type":  "<TYPE>",   "pattern": "<string>" }
     */
    public static function evaluateNode(array $node, Email $email): bool
    {
        // グループノード判定. logic + items の組み合わせがあればグループ.
        if (isset($node['logic']) && isset($node['items']) && is_array($node['items'])) {
            $logic = $node['logic'] === self::LOGIC_AND ? self::LOGIC_AND : self::LOGIC_OR;
            $items = $node['items'];
            if (empty($items)) return false;
            if ($logic === self::LOGIC_AND) {
                // AND: 1 つでも非マッチで false.
                foreach ($items as $child) {
                    if (!is_array($child)) return false;
                    if (!self::evaluateNode($child, $email)) return false;
                }
                return true;
            }
            // OR: 1 つでもマッチで true.
            foreach ($items as $child) {
                if (!is_array($child)) continue;
                if (self::evaluateNode($child, $email)) return true;
            }
            return false;
        }
        // リーフノード.
        $type    = (string) ($node['type'] ?? '');
        $pattern = (string) ($node['pattern'] ?? '');
        if ($type === '' || $pattern === '') return false;
        return self::evaluateLeaf($type, $pattern, $email);
    }

    /**
     * 単一条件 (type + pattern) を評価. 旧 matches() のスイッチをここに切り出した.
     * グループ評価の最下層 + レガシー単一条件ルールの両方から呼ぶ.
     */
    protected static function evaluateLeaf(string $type, string $pattern, Email $email): bool
    {
        if ($pattern === '') return false;

        switch ($type) {
            case self::TYPE_FROM_ADDRESS:
                return mb_strtolower((string) $email->from_address) === mb_strtolower($pattern);

            case self::TYPE_FROM_DOMAIN:
                $addr = (string) $email->from_address;
                $at   = strrpos($addr, '@');
                if ($at === false) return false;
                $domain = mb_strtolower(substr($addr, $at + 1));
                return $domain === mb_strtolower($pattern);

            case self::TYPE_SUBJECT_CONTAINS:
                return mb_stripos((string) $email->subject, $pattern) !== false;

            case self::TYPE_TO_CONTAINS:
                // To だけでなく Cc / Bcc にも含まれるかをチェックする (= 宛名集合).
                return mb_stripos((string) $email->to_address, $pattern) !== false
                    || mb_stripos((string) ($email->cc  ?? ''), $pattern) !== false
                    || mb_stripos((string) ($email->bcc ?? ''), $pattern) !== false;

            case self::TYPE_ANY_ADDRESS:
                // From / To / Cc / Bcc のどのアドレスとも完全一致するかをチェック.
                $needle = mb_strtolower($pattern);
                if ($needle === '') return false;
                $allAddrs = array_merge(
                    [(string) $email->from_address],
                    self::extractAddressList((string) $email->to_address),
                    self::extractAddressList((string) ($email->cc  ?? '')),
                    self::extractAddressList((string) ($email->bcc ?? '')),
                );
                foreach ($allAddrs as $a) {
                    if (mb_strtolower(trim($a)) === $needle) return true;
                }
                return false;

            case self::TYPE_ANY_DOMAIN:
                // From / To / Cc / Bcc のいずれかのアドレスのドメイン部と完全一致するかをチェック.
                $needle = ltrim(mb_strtolower($pattern), '@');
                if ($needle === '') return false;
                $allAddrs = array_merge(
                    [(string) $email->from_address],
                    self::extractAddressList((string) $email->to_address),
                    self::extractAddressList((string) ($email->cc  ?? '')),
                    self::extractAddressList((string) ($email->bcc ?? '')),
                );
                foreach ($allAddrs as $a) {
                    $a = trim($a);
                    $at = strrpos($a, '@');
                    if ($at === false) continue;
                    $d = mb_strtolower(substr($a, $at + 1));
                    if ($d === $needle) return true;
                }
                return false;
        }
        return false;
    }

    /**
     * conditions ツリーをバリデート + 正規化する.
     * 失敗時は \InvalidArgumentException を投げる. controller の store / update から呼ぶ.
     *
     *   - ルートはグループ ({ logic, items }) であること
     *   - 各リーフは TYPES に含まれる type + 非空 pattern を持つこと
     *   - グループの items は 1 件以上
     *   - ネスト深さは MAX_DEPTH まで
     *
     * 戻り値: 正規化済みのツリー (pattern は normalizePattern 適用済み).
     */
    public static function validateAndNormalizeConditions(array $tree, int $depth = 0): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw new \InvalidArgumentException('conditions のネストが深すぎます (上限 ' . self::MAX_DEPTH . ' 階層)');
        }
        // グループノード.
        if (isset($tree['logic']) || isset($tree['items'])) {
            $logic = $tree['logic'] ?? self::LOGIC_OR;
            if (!in_array($logic, self::LOGICS, true)) {
                throw new \InvalidArgumentException("logic は 'and' または 'or' を指定してください");
            }
            $items = $tree['items'] ?? [];
            if (!is_array($items) || empty($items)) {
                throw new \InvalidArgumentException('グループには 1 件以上の条件が必要です');
            }
            $normItems = [];
            foreach ($items as $child) {
                if (!is_array($child)) {
                    throw new \InvalidArgumentException('items の各要素はオブジェクトである必要があります');
                }
                $normItems[] = self::validateAndNormalizeConditions($child, $depth + 1);
            }
            return ['logic' => $logic, 'items' => $normItems];
        }
        // リーフノード.
        $type    = (string) ($tree['type'] ?? '');
        $pattern = (string) ($tree['pattern'] ?? '');
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException("不明な type: {$type}");
        }
        $pattern = self::normalizePattern($type, $pattern);
        if ($pattern === '') {
            throw new \InvalidArgumentException("pattern が空です (type={$type})");
        }
        return ['type' => $type, 'pattern' => $pattern];
    }

    /**
     * ツリーの先頭リーフを返す. UI 一覧 (旧型) や検索 LIKE クエリ用の代表条件として
     * type / pattern カラムに backfill するために使う.
     */
    public static function firstLeaf(array $tree): ?array
    {
        if (isset($tree['type'], $tree['pattern'])) {
            return ['type' => (string) $tree['type'], 'pattern' => (string) $tree['pattern']];
        }
        foreach (($tree['items'] ?? []) as $child) {
            if (is_array($child)) {
                $leaf = self::firstLeaf($child);
                if ($leaf) return $leaf;
            }
        }
        return null;
    }

    /**
     * ツリーをフラットなリーフ配列に展開する (重複は許容). backfill SQL の粗フィルタ
     * (= 「いずれかのリーフ条件にマッチしうる候補メール」を LIKE で絞る) に使う.
     */
    public static function flattenLeaves(array $tree): array
    {
        if (isset($tree['type'], $tree['pattern'])) {
            return [['type' => (string) $tree['type'], 'pattern' => (string) $tree['pattern']]];
        }
        $out = [];
        foreach (($tree['items'] ?? []) as $child) {
            if (is_array($child)) {
                foreach (self::flattenLeaves($child) as $leaf) $out[] = $leaf;
            }
        }
        return $out;
    }

    /**
     * マッチ統計を更新する (ヒット回数 + 最終時刻)。失敗してもアプリは継続。
     */
    public function recordHit(): void
    {
        try {
            $this->increment('match_count');
            $this->update(['last_matched_at' => now()]);
        } catch (\Throwable) {}
    }
}
