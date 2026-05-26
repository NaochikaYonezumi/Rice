<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Email extends Model
{
    protected $fillable = [
        'thread_id', 'message_id', 'in_reply_to', 'subject',
        'from_address', 'from_name', 'to_address', 'cc', 'bcc',
        'body_text', 'body_html', 'is_read', 'received_at',
        // 個別メール単位のゴミ箱フラグ. trashed_at IS NOT NULL = ゴミ箱に入っている.
        // 30 日経過後に mail:purge-trash でハード削除される.
        'trashed_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'trashed_at'  => 'datetime',
        'is_read' => 'boolean',
    ];

    /** ゴミ箱の保持期間 (日). EmailThread と同じ値を使う. */
    public const TRASH_RETENTION_DAYS = 30;

    /** この個別メールはゴミ箱に入っているか. */
    public function isTrashed(): bool
    {
        return $this->trashed_at !== null;
    }

    protected $appends = ['from_label', 'plain_body'];
    // safe_body_html は重いので $appends に入れず、`->append('safe_body_html')` で明示的に追加する。
    // スレッド詳細 (EmailController::thread) のみで使用。

    public function thread(): BelongsTo
    {
        return $this->belongsTo(EmailThread::class, 'thread_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }

    public function getFromLabelAttribute(): string
    {
        // from_name → from_address の順に空でない値を採用する。
        // Elvis (?:) は空文字列も falsy として扱うため、"From: Name <>" のような
        // 不完全なヘッダで空文字列が入っていても次のフォールバックへ進む。
        $name = trim((string) ($this->from_name ?? ''));
        if ($name !== '') return $name;

        $addr = trim((string) ($this->from_address ?? ''));
        if ($addr !== '') return $addr;

        return '';
    }

    public function getPlainBodyAttribute(): string
    {
        if ($this->body_text) {
            return $this->body_text;
        }
        $html = $this->body_html ?? '';
        if ($html === '') return '';

        // 改行を保持しつつタグを除去
        $html = preg_replace('/<\s*br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/(p|div|h[1-6]|li|tr)>/i', "\n", $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // 連続した空行を圧縮
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }

    /**
     * XSS 安全な形にサニタイズされた body_html を返す。
     *
     * 方針 (依存ライブラリなしで実装):
     *  - <script> <style> <iframe> <object> <embed> <link> <meta> <form> <input> など
     *    アクティブコンテンツ系タグは丸ごと除去
     *  - 全タグから on* (onclick/onerror …) 属性を除去
     *  - href / src の javascript: / data:text/html: スキームを除去
     *  - <a> には rel="noopener noreferrer" + target="_blank" を強制
     *  - <img src=...> はそのまま許可するが、外部画像読み込みはレンダリング側で
     *    sandbox iframe にすることで制限する想定
     */
    public function getSafeBodyHtmlAttribute(): string
    {
        $html = (string) ($this->body_html ?? '');
        if ($html === '') return '';
        return self::sanitizeHtml($html);
    }

    /**
     * 簡易 HTML サニタイザ。
     * 大型ライブラリ (HTMLPurifier 等) は導入せず正規表現ベースで XSS 主要ベクタを潰す。
     * 完全な防御ではないため、メール HTML は最終的に sandbox iframe 内で描画する前提。
     */
    public static function sanitizeHtml(string $html): string
    {
        if ($html === '') return '';

        // 0) iframe srcdoc に埋め込んだとき内側に DOCTYPE や XML 宣言が居ると
        //    HTML5 パーサが parser モードでつまずいて body が描画されない事象が起きるため、
        //    XHTML/Office HTML 由来の document prologue を先頭から剥がしておく.
        //    (コメント内に <?xml の閉じ記号を書くと PHP 自身の終端と誤認されるので散文表現にしてある.)
        $html = preg_replace('#<\\?xml\\b[^?]*\\?>#iu', '', $html) ?? $html;
        $html = preg_replace('#<!DOCTYPE[^>]*>#iu', '', $html) ?? $html;

        // 1) 危険タグをコメントごと除去 (中身の script コードも捨てる).
        //    NOTE: 旧実装は <style> も剥がしていたが、HTML メールはほぼ全て <style> ブロックで
        //          レイアウト (table-based) を組んでいるため、これを消すと「テキスト同然」の
        //          見た目になってしまっていた. <style> は CSS のみで JS 実行は不可、かつ
        //          描画は sandbox iframe 内で行うため、ここでは残す.
        //          (CSS による情報漏洩リスクは存在するが、iframe sandbox の同一オリジン制約と
        //           CSP `default-src 'self' data:` で外部リソース読み込みを大きく制限している.)
        $blockTags = ['script', 'iframe', 'object', 'embed', 'applet', 'meta', 'link', 'form', 'input', 'button', 'select', 'textarea', 'base'];
        foreach ($blockTags as $t) {
            $html = preg_replace("#<\\s*{$t}\\b[^>]*>.*?<\\s*/\\s*{$t}\\s*>#siu", '', $html) ?? $html;
            // 自己閉じ / 開きのみのバリエーション
            $html = preg_replace("#<\\s*{$t}\\b[^>]*/?>#siu", '', $html) ?? $html;
        }
        // <style> 自体は残すが、中身に @import や url() 経由の javascript: スキームが
        // 紛れ込んでいたら無害化する (CSS から JS 実行できる古い IE 拡張対策).
        $html = preg_replace_callback('#<style\\b[^>]*>(.*?)</style>#siu', function ($m) {
            $css = $m[1];
            $css = preg_replace('#javascript\\s*:#iu', '/* blocked: */', $css) ?? $css;
            $css = preg_replace('#expression\\s*\\(#iu', '/* blocked: */ (', $css) ?? $css;
            $css = preg_replace('#@import\\b[^;]*;#iu', '/* blocked-import */', $css) ?? $css;
            return '<style>' . $css . '</style>';
        }, $html) ?? $html;

        // 2) on* 属性 (onload, onclick, onerror 等) を全タグから除去
        //    `on` で始まり `=` が続く属性をクオートあり/なし両対応で削除
        $html = preg_replace('#\\s+on[a-z]+\\s*=\\s*"[^"]*"#iu', '', $html) ?? $html;
        $html = preg_replace("#\\s+on[a-z]+\\s*=\\s*'[^']*'#iu", '', $html) ?? $html;
        $html = preg_replace('#\\s+on[a-z]+\\s*=\\s*[^\\s>]+#iu', '', $html) ?? $html;

        // 3) javascript: / vbscript: / data: スキームの href/src/action を無害化
        $html = preg_replace('#(href|src|action|formaction)\\s*=\\s*"\\s*(javascript|vbscript|data\\s*:\\s*text/html|data\\s*:\\s*application)[^"]*"#iu', '$1="#"', $html) ?? $html;
        $html = preg_replace("#(href|src|action|formaction)\\s*=\\s*'\\s*(javascript|vbscript|data\\s*:\\s*text/html|data\\s*:\\s*application)[^']*'#iu", "$1='#'", $html) ?? $html;

        // 4) <a> に rel/target を強制 (target=_blank の opener 攻撃対策 + 外部リンクを別タブ)
        $html = preg_replace_callback('#<a\\b([^>]*)>#iu', function ($m) {
            $attrs = $m[1];
            // 既存 target / rel を削除して再注入
            $attrs = preg_replace('#\\s+target\\s*=\\s*"[^"]*"#iu', '', $attrs);
            $attrs = preg_replace("#\\s+target\\s*=\\s*'[^']*'#iu", '', $attrs);
            $attrs = preg_replace('#\\s+rel\\s*=\\s*"[^"]*"#iu', '', $attrs);
            $attrs = preg_replace("#\\s+rel\\s*=\\s*'[^']*'#iu", '', $attrs);
            return '<a' . $attrs . ' target="_blank" rel="noopener noreferrer">';
        }, $html);

        return $html;
    }
}
