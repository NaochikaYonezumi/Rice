<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * emails / email_threads / email_attachments テーブルの文字コードを
 * utf8mb4 / utf8mb4_unicode_ci に揃える。
 *
 * 受信メールの本文には iso-2022-jp や PGP 暗号バイナリなど 3-byte utf8 では
 * 入りきらない 4-byte 文字や非UTF8バイト列が混じることがあり、
 * 「Incorrect string value: '\xEB...' for column 'body_text'」エラーで取り込みが
 * 停止する。EmailFetcher 側でサニタイズも行うが、カラム自体が utf8mb4 で
 * 無いと根本的に救えないケースが残る。
 *
 * MySQL/MariaDB のみ対象。他 DB ドライバ (sqlite/postgres) は no-op。
 */
return new class extends Migration {
    public function up(): void
    {
        $driver = DB::getDriverName();
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach (['emails', 'email_threads', 'email_attachments'] as $table) {
            if (!Schema::hasTable($table)) continue;
            try {
                DB::statement("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } catch (\Throwable $e) {
                // 容量不足等で fail しても次のテーブルに進む。エラーはマイグレーションログに残る
                logger()->warning("ensure_emails_utf8mb4: ALTER {$table} failed", ['error' => $e->getMessage()]);
            }
        }
    }

    public function down(): void
    {
        // 文字コード変更を巻き戻す合理的なターゲットが無いので no-op
    }
};
