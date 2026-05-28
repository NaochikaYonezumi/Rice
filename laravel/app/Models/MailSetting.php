<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailSetting extends Model
{
    protected $fillable = [
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password',
        'smtp_from_address', 'smtp_from_name',
        'inbox_protocol',
        'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_password', 'imap_folder',
        'pop_host', 'pop_port', 'pop_encryption', 'pop_username', 'pop_password',
        'last_fetch_at', 'last_fetch_success_at', 'last_fetch_error', 'last_fetch_error_at',
        'last_fetch_count', 'consecutive_failures',
        'send_policy',
        // 保持期間 (= ゴミ箱/迷惑メールが自動完全削除されるまでの日数). 既定 30.
        'trash_retention_days', 'spam_retention_days',
    ];

    protected $casts = [
        'last_fetch_at'         => 'datetime',
        'last_fetch_success_at' => 'datetime',
        'last_fetch_error_at'   => 'datetime',
        'last_fetch_count'      => 'integer',
        'consecutive_failures'  => 'integer',
        'trash_retention_days'  => 'integer',
        'spam_retention_days'   => 'integer',
    ];

    /** 保持期間のフォールバック既定値 (= mail_settings 行が無い / カラム未存在のとき). */
    public const DEFAULT_TRASH_RETENTION_DAYS = 30;
    public const DEFAULT_SPAM_RETENTION_DAYS  = 30;
    /** 保持期間 UI でユーザに許可する範囲. 0 = 即時削除 (= 自動 purge 無効化に相当する代わり) を避けるため 1 始まり. */
    public const MIN_RETENTION_DAYS = 1;
    public const MAX_RETENTION_DAYS = 3650;

    /**
     * 送信ポリシー定数:
     *   - SEND_POLICY_FLEXIBLE         : 自己送信 (即時) と 承認経由 のどちらも選べる (デフォルト)
     *   - SEND_POLICY_APPROVAL_REQUIRED: 承認経由でしか送れない (作成者は下書き + 承認依頼のみ)
     * 管理者だけが変更可能.
     */
    public const SEND_POLICY_FLEXIBLE          = 'flexible';
    public const SEND_POLICY_APPROVAL_REQUIRED = 'approval_required';

    public function isApprovalRequired(): bool
    {
        return $this->send_policy === self::SEND_POLICY_APPROVAL_REQUIRED;
    }

    public static function getSettings(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'inbox_protocol' => 'imap',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'pop_port' => 995,
            'pop_encryption' => 'ssl',
            'trash_retention_days' => self::DEFAULT_TRASH_RETENTION_DAYS,
            'spam_retention_days'  => self::DEFAULT_SPAM_RETENTION_DAYS,
        ]);
    }

    /**
     * 設定値からゴミ箱/迷惑メールの保持期間を返すヘルパ.
     *
     * 例外安全性:
     *   - mail_settings テーブルがまだ無い / 該当カラムが無い / DB 接続失敗 など、
     *     どの段階で落ちても DEFAULT_*_DAYS を返す.
     *   - 値が null / 0 以下 / int に変換できない場合もデフォルトを返す.
     *
     * これにより、テスト / インストール直後 / マイグレーション未適用環境でも
     * 30 日扱いで動作する.
     */
    public static function trashRetentionDays(): int
    {
        try {
            $v = (int) (self::getSettings()->trash_retention_days ?? 0);
            return $v > 0 ? $v : self::DEFAULT_TRASH_RETENTION_DAYS;
        } catch (\Throwable) {
            return self::DEFAULT_TRASH_RETENTION_DAYS;
        }
    }

    public static function spamRetentionDays(): int
    {
        try {
            $v = (int) (self::getSettings()->spam_retention_days ?? 0);
            return $v > 0 ? $v : self::DEFAULT_SPAM_RETENTION_DAYS;
        } catch (\Throwable) {
            return self::DEFAULT_SPAM_RETENTION_DAYS;
        }
    }

    /**
     * 直近の取得結果を記録 (成功時)。
     *
     * @param int $count 取り込み件数
     * @param array $perMailErrors 個別エラー (= 部分成功時の警告。0 件なら完全成功)
     */
    public function recordFetchSuccess(int $count, array $perMailErrors = []): void
    {
        try {
            $now = now();
            $this->forceFill([
                'last_fetch_at'         => $now,
                'last_fetch_success_at' => $now,
                'last_fetch_error'      => empty($perMailErrors) ? null : (
                    '個別エラー ' . count($perMailErrors) . ' 件 (取り込み自体は成功)'
                ),
                'last_fetch_error_at'   => empty($perMailErrors) ? null : $now,
                'last_fetch_count'      => $count,
                'consecutive_failures'  => 0,
            ])->save();
        } catch (\Throwable $e) {
            // 永続記録に失敗してもメイン処理は止めない (ただし必ずログ)
            \Illuminate\Support\Facades\Log::warning('MailSetting.recordFetchSuccess failed: ' . $e->getMessage());
        }
    }

    /**
     * 直近の取得結果を記録 (失敗時)。
     */
    public function recordFetchFailure(string $errorMessage): void
    {
        try {
            $now = now();
            $this->forceFill([
                'last_fetch_at'        => $now,
                'last_fetch_error'     => mb_substr($errorMessage, 0, 5000),
                'last_fetch_error_at'  => $now,
                'consecutive_failures' => ((int) ($this->consecutive_failures ?? 0)) + 1,
            ])->save();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('MailSetting.recordFetchFailure failed: ' . $e->getMessage());
        }
    }
}
