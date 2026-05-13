<?php

namespace Modules\AIReply\Services;

use App\Models\AiLog;
use App\Models\PendingEmail;

/**
 * Phase 6-3: AI 生成案がどれだけ採用されたかを評価する。
 *
 * - pending_email に ai_log_id が紐付いている場合のみ動く
 * - 編集距離 (Levenshtein) を計算し、10% 以下なら採用、超過なら破棄
 * - 結果を ext_ai_logs.was_adopted / edit_distance / sent_at に書き込む
 *
 * PHP 標準の levenshtein() は内部実装で 255 バイトを超える文字列に対して
 * false を返す。長文の場合は先頭 255 バイトで比較する。
 */
class AdoptionEvaluator
{
    /** 採用と判定する Levenshtein 距離の上限比率 (0.1 = 10%) */
    public const ADOPTION_THRESHOLD = 0.1;

    /** levenshtein() の入力上限 (PHP 標準) */
    private const MAX_LEN_FOR_LEVENSHTEIN = 255;

    public function evaluate(PendingEmail $pending): void
    {
        $logId = $pending->ai_log_id ?? null;
        if (!$logId) return;

        $log = AiLog::find($logId);
        if (!$log) return;

        $generated = (string) $log->generated_reply;
        $sentBody  = (string) $pending->body;

        // 距離 (バイト切り詰めで比較)
        $a = $this->truncate($generated);
        $b = $this->truncate($sentBody);
        $distance = levenshtein($a, $b);
        if ($distance === -1) {
            // 念のためのフォールバック
            $distance = abs(strlen($a) - strlen($b));
        }

        // 採用判定: 短い側の長さで正規化、空文字対策
        $maxLen = max(1, max(mb_strlen($generated, 'UTF-8'), mb_strlen($sentBody, 'UTF-8')));
        $ratio = $distance / $maxLen;
        $wasAdopted = $ratio <= self::ADOPTION_THRESHOLD ? 1 : 0;

        $log->update([
            'was_adopted'   => $wasAdopted,
            'edit_distance' => $distance,
            'sent_at'       => now(),
        ]);
    }

    private function truncate(string $s): string
    {
        // 単純なバイト切り詰めで OK (UTF-8 でも分布的に十分な比較になる)
        if (strlen($s) > self::MAX_LEN_FOR_LEVENSHTEIN) {
            return substr($s, 0, self::MAX_LEN_FOR_LEVENSHTEIN);
        }
        return $s;
    }
}
