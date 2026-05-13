<?php

namespace App\Support;

/**
 * Phase 6-4: 署名 HTML を XSS なしで安全に保存・表示するためのサニタイザ。
 *
 * 許可タグ:
 *   p, br, b, strong, i, em, u, span, div, a, ul, ol, li, hr, img
 *
 * 危険要素 (script/style/iframe/on* 属性/javascript: URI) を除去する。
 */
class SignatureSanitizer
{
    private const ALLOWED_TAGS = '<p><br><b><strong><i><em><u><span><div><a><ul><ol><li><hr><img>';

    public static function sanitize(?string $html): ?string
    {
        if ($html === null || $html === '') return $html;

        $html = preg_replace('#<\s*(script|style|iframe|object|embed|form|input|textarea|select|button)\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html) ?? $html;
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $html) ?? $html;
        $html = preg_replace('/javascript\s*:/i', 'about:blank#', $html) ?? $html;
        $html = strip_tags($html, self::ALLOWED_TAGS);

        return $html;
    }
}
