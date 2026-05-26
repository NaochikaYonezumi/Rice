<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapedUrl extends Model
{
    public const SOURCE_URL   = 'url';
    public const SOURCE_FILE  = 'file';
    public const SOURCE_EMAIL = 'email';

    protected $fillable = [
        'url', 'source_type', 'title', 'meta', 'raw_text',
        'collection', 'collections', 'chunks_indexed', 'status', 'error_message',
    ];

    protected $casts = [
        'meta'        => 'array',
        'collections' => 'array',
    ];

    public function displayLabel(): string
    {
        return $this->title ?: $this->url;
    }

    /**
     * 文字列または配列で渡された値を、正規化されたコレクション名の配列に変換する。
     *
     * - カンマ / 全角カンマ / 改行で区切る
     * - trim + 空除去 + 重複除去 (順序維持)
     * - 不正文字 (空白 / / \ # ? &) は静かに除去
     * - 何も無い場合は ['default']
     */
    public static function normalizeCollections(string|array|null $input): array
    {
        if ($input === null) return ['default'];
        // PHP 8 で `a ? b : c ?: d` のような無括弧 ネスト三項演算子は禁止されている。
        // 必ず括弧でグループ化する。
        if (is_array($input)) {
            $list = $input;
        } else {
            $split = preg_split('/[,，\n\r]+/u', $input);
            $list  = ($split !== false) ? $split : [];
        }
        $out = [];
        foreach ($list as $raw) {
            $name = trim((string) $raw);
            if ($name === '') continue;
            // 禁止文字を含むトークンは破棄 (個別バリデーションエラーにはせず救う)
            if (preg_match('/[\s\/\\\\#?&]/u', $name)) continue;
            if (mb_strlen($name) > 64) $name = mb_substr($name, 0, 64);
            if (!in_array($name, $out, true)) $out[] = $name;
        }
        return ($out !== []) ? $out : ['default'];
    }

    /**
     * 「主コレクション」 (互換用)。`collections` 配列の先頭、無ければ `collection` カラム。
     */
    public function primaryCollection(): string
    {
        $arr = is_array($this->collections) ? $this->collections : [];
        if (!empty($arr) && is_string($arr[0])) return $arr[0];
        return $this->collection ?: 'default';
    }

    /**
     * このソースが持つ全コレクション名 (配列)。互換用に `collection` も拾う。
     */
    public function allCollections(): array
    {
        $arr = is_array($this->collections) ? $this->collections : [];
        if (empty($arr) && $this->collection) $arr = [$this->collection];
        return $arr !== [] ? $arr : ['default'];
    }
}
