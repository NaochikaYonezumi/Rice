<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 迷惑メール判定ルール。
 *
 * type の意味:
 *   送信者系 (FROM ヘッダ)
 *     - sender_address     … from_address と完全一致
 *     - sender_domain      … from_address の "@xxx" 部分が pattern と完全一致 (大文字小文字無視)
 *   宛先系 (TO / CC / BCC ヘッダ全部を横断検索)
 *     - recipient_address  … To/Cc/Bcc に pattern と完全一致するアドレスが含まれる (大文字小文字無視)
 *     - recipient_domain   … To/Cc/Bcc のいずれかのドメイン部が pattern と完全一致
 *     - recipient_contains … To/Cc/Bcc 全体の文字列に部分一致
 *   件名・本文
 *     - subject_keyword    … subject に部分一致 (大文字小文字無視)
 *     - body_keyword       … body_text または strip_tags(body_html) に部分一致
 *
 * 「ルームと同じ条件でスパムも振り分けたい」という要望に応えるため、
 * ChatRoomRoutingRule の TYPE_TO_CONTAINS と同等の宛先マッチを追加してある.
 */
class MailBlockRule extends Model
{
    // 送信元
    public const TYPE_SENDER_ADDRESS     = 'sender_address';
    public const TYPE_SENDER_DOMAIN      = 'sender_domain';
    // 宛先 (To / Cc / Bcc 全部を対象に横断検索する)
    public const TYPE_RECIPIENT_ADDRESS  = 'recipient_address';
    public const TYPE_RECIPIENT_DOMAIN   = 'recipient_domain';
    public const TYPE_RECIPIENT_CONTAINS = 'recipient_contains';
    // 件名 / 本文
    public const TYPE_SUBJECT_KEYWORD    = 'subject_keyword';
    public const TYPE_BODY_KEYWORD       = 'body_keyword';

    public const TYPES = [
        self::TYPE_SENDER_ADDRESS,
        self::TYPE_SENDER_DOMAIN,
        self::TYPE_RECIPIENT_ADDRESS,
        self::TYPE_RECIPIENT_DOMAIN,
        self::TYPE_RECIPIENT_CONTAINS,
        self::TYPE_SUBJECT_KEYWORD,
        self::TYPE_BODY_KEYWORD,
    ];

    protected $fillable = ['type', 'pattern', 'enabled', 'match_count', 'last_matched_at', 'created_by', 'logic', 'conditions'];

    protected $casts = [
        'enabled'         => 'boolean',
        'last_matched_at' => 'datetime',
        'match_count'     => 'integer',
        // ネスト条件ツリー (ChatRoomRoutingRule と同一形式).
        //   ['logic'=>'and'|'or', 'items'=>[<node>, ...]]
        //   リーフ: ['type'=><TYPE>, 'pattern'=><string>]
        'conditions'      => 'array',
    ];

    /** ネスト条件 (AND/OR) 用の定数. ChatRoomRoutingRule と揃える. */
    public const LOGIC_AND = 'and';
    public const LOGIC_OR  = 'or';
    public const LOGICS    = [self::LOGIC_AND, self::LOGIC_OR];
    /** UI 編集の限界. それ以上のネストはサーバ側で弾く. */
    public const MAX_DEPTH = 5;

    /**
     * 与えられたメール情報がこのルールにマッチするか。
     *
     * @param  string $fromAddress  メールの From アドレス
     * @param  string $subject      件名
     * @param  string $bodyText     プレーンテキスト本文
     * @param  string $bodyHtml     HTML 本文
     * @param  string $toAddress    To ヘッダ ("a@x.com, b@y.com" 形式の生文字列を許容)
     * @param  string $cc           Cc ヘッダ (同上)
     * @param  string $bcc          Bcc ヘッダ (同上)
     */
    public function matches(
        string $fromAddress,
        string $subject,
        string $bodyText,
        string $bodyHtml = '',
        string $toAddress = '',
        string $cc = '',
        string $bcc = ''
    ): bool {
        // 評価コンテキストを 1 度だけ作って全リーフ評価で共有する.
        // 「宛先一括文字列 + トークン化アドレス配列」も含めて先に計算.
        $ctx = self::buildContext($fromAddress, $subject, $bodyText, $bodyHtml, $toAddress, $cc, $bcc);

        // 優先: conditions ツリーがあれば再帰評価.
        $tree = $this->conditions;
        if (is_array($tree) && !empty($tree)) {
            return self::evaluateNode($tree, $ctx);
        }
        // フォールバック: 単一条件 (type+pattern) で評価.
        return self::evaluateLeaf((string) $this->type, (string) $this->pattern, $ctx);
    }

    /**
     * matches() の準備計算をまとめたコンテキスト構造体 (associative array).
     *   from_lower, subject_lower, recipients_lower, recipient_list[],
     *   body_mix_lower (= body_text + strip_tags(body_html) lowercased)
     *
     * 計算結果を 1 度作っておけば、リーフ N 個の評価でも N 回計算しなくて済む.
     */
    protected static function buildContext(
        string $fromAddress,
        string $subject,
        string $bodyText,
        string $bodyHtml,
        string $toAddress,
        string $cc,
        string $bcc
    ): array {
        $recipientsRaw   = trim(implode(', ', array_filter([$toAddress, $cc, $bcc], fn($s) => $s !== '')));
        return [
            'from_lower'       => mb_strtolower($fromAddress),
            'subject_lower'    => mb_strtolower($subject),
            'from_address'     => $fromAddress, // extractDomain 用にオリジナルも持つ
            'recipients_lower' => mb_strtolower($recipientsRaw),
            'recipient_list'   => self::splitAddressList($recipientsRaw),
            'body_mix_lower'   => mb_strtolower(
                $bodyText . "\n" . ($bodyHtml !== '' ? strip_tags($bodyHtml) : '')
            ),
        ];
    }

    /**
     * ノード (グループ or リーフ) を再帰評価.
     *   グループ: { 'logic': 'and'|'or', 'items': [<node>, ...] }
     *   リーフ:   { 'type':  <TYPE>,    'pattern': <string>   }
     */
    public static function evaluateNode(array $node, array $ctx): bool
    {
        if (isset($node['logic']) && isset($node['items']) && is_array($node['items'])) {
            $logic = $node['logic'] === self::LOGIC_AND ? self::LOGIC_AND : self::LOGIC_OR;
            $items = $node['items'];
            if (empty($items)) return false;
            if ($logic === self::LOGIC_AND) {
                foreach ($items as $child) {
                    if (!is_array($child)) return false;
                    if (!self::evaluateNode($child, $ctx)) return false;
                }
                return true;
            }
            // OR
            foreach ($items as $child) {
                if (!is_array($child)) continue;
                if (self::evaluateNode($child, $ctx)) return true;
            }
            return false;
        }
        // リーフ
        $type    = (string) ($node['type'] ?? '');
        $pattern = (string) ($node['pattern'] ?? '');
        if ($type === '' || $pattern === '') return false;
        return self::evaluateLeaf($type, $pattern, $ctx);
    }

    /**
     * 単一条件 (type + pattern) の評価. 旧 matches() のスイッチを切り出したもの.
     * グループ評価の最下層 + レガシー単一条件ルールの両方から呼ぶ.
     */
    protected static function evaluateLeaf(string $type, string $pattern, array $ctx): bool
    {
        $pattern = trim($pattern);
        if ($pattern === '') return false;
        $patternLower = mb_strtolower($pattern);

        switch ($type) {
            case self::TYPE_SENDER_ADDRESS:
                return $ctx['from_lower'] !== '' && $ctx['from_lower'] === $patternLower;

            case self::TYPE_SENDER_DOMAIN:
                $domain = mb_strtolower(self::extractDomain($ctx['from_address']));
                $needle = ltrim($patternLower, '@');
                return $domain !== '' && $needle !== '' && $domain === $needle;

            case self::TYPE_RECIPIENT_ADDRESS:
                if ($patternLower === '') return false;
                foreach ($ctx['recipient_list'] as $addr) {
                    if (mb_strtolower($addr) === $patternLower) return true;
                }
                return false;

            case self::TYPE_RECIPIENT_DOMAIN:
                $needle = ltrim($patternLower, '@');
                if ($needle === '') return false;
                foreach ($ctx['recipient_list'] as $addr) {
                    $d = mb_strtolower(self::extractDomain($addr));
                    if ($d !== '' && $d === $needle) return true;
                }
                return false;

            case self::TYPE_RECIPIENT_CONTAINS:
                return $patternLower !== '' && $ctx['recipients_lower'] !== ''
                    && mb_strpos($ctx['recipients_lower'], $patternLower) !== false;

            case self::TYPE_SUBJECT_KEYWORD:
                return $patternLower !== '' && mb_strpos($ctx['subject_lower'], $patternLower) !== false;

            case self::TYPE_BODY_KEYWORD:
                return mb_strpos($ctx['body_mix_lower'], $patternLower) !== false;
        }
        return false;
    }

    /**
     * conditions ツリーをバリデート + 正規化. controller の store / update から呼ぶ.
     * 失敗時は \InvalidArgumentException.
     */
    public static function validateAndNormalizeConditions(array $tree, int $depth = 0): array
    {
        if ($depth > self::MAX_DEPTH) {
            throw new \InvalidArgumentException('conditions のネストが深すぎます (上限 ' . self::MAX_DEPTH . ' 階層)');
        }
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
        // リーフ
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
     * ツリーから代表となる先頭リーフを返す. legacy 表示用 type / pattern カラムに backfill する用途.
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
     * ツリーを全リーフ配列に展開. 重複は許容. UI 表示や検索 LIKE 用の粗フィルタに使う.
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

    public static function extractDomain(?string $address): string
    {
        if (!$address) return '';
        $address = trim($address);
        $pos = strrpos($address, '@');
        if ($pos === false) return '';
        return substr($address, $pos + 1);
    }

    /**
     * "Foo <a@x.com>, bar@y.com" のような文字列からアドレス部だけを取り出した配列を返す。
     * 区切り文字: カンマ / セミコロン / 改行。  <...> の中身を優先して、なければ生のトークンを使う。
     */
    public static function splitAddressList(string $raw): array
    {
        if (trim($raw) === '') return [];
        // カンマ / セミコロン / 改行で分割
        $parts = preg_split('/[,;\r\n]+/u', $raw) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') continue;
            // "Name <addr@host>" 形式があればその中身を採用
            if (preg_match('/<([^>]+)>/', $p, $m)) {
                $cand = trim($m[1]);
            } else {
                $cand = $p;
            }
            // @ を含まないものはメールアドレスと見做せないのでスキップ
            if ($cand !== '' && strpos($cand, '@') !== false) {
                $out[] = $cand;
            }
        }
        return $out;
    }

    /**
     * type / pattern を正規化 (重複登録回避用)
     */
    public static function normalizePattern(string $type, string $pattern): string
    {
        $pattern = trim($pattern);
        // 完全一致系: 小文字化
        if (in_array($type, [self::TYPE_SENDER_ADDRESS, self::TYPE_RECIPIENT_ADDRESS], true)) {
            return mb_strtolower($pattern);
        }
        // ドメイン系: 先頭 @ 除去 + 小文字化
        if (in_array($type, [self::TYPE_SENDER_DOMAIN, self::TYPE_RECIPIENT_DOMAIN], true)) {
            return mb_strtolower(ltrim($pattern, '@'));
        }
        // 部分一致系: そのまま (大文字小文字は matches() 側で吸収)
        return $pattern;
    }
}
