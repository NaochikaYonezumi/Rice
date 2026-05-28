<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * mail_settings に最終取得結果を保持するためのカラムを追加。
 *
 * UI 側 (画面再読込時 / バックグラウンド) で「直近の同期失敗を継続的に表示する」
 * ためのストレージ。
 *
 * - last_fetch_at        … 最後に取得が試みられた時刻
 * - last_fetch_success_at … 最後に成功した時刻 (= サーバ接続+取得が通った)
 * - last_fetch_error      … 失敗時の人間向けメッセージ
 * - last_fetch_error_at   … 失敗を観測した時刻
 * - last_fetch_count      … 直近の取り込み件数
 * - consecutive_failures  … 連続失敗回数 (成功で 0 にリセット)
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('mail_settings')) return;
        Schema::table('mail_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('mail_settings', 'last_fetch_at'))         $table->timestamp('last_fetch_at')->nullable();
            if (!Schema::hasColumn('mail_settings', 'last_fetch_success_at')) $table->timestamp('last_fetch_success_at')->nullable();
            if (!Schema::hasColumn('mail_settings', 'last_fetch_error'))      $table->text('last_fetch_error')->nullable();
            if (!Schema::hasColumn('mail_settings', 'last_fetch_error_at'))   $table->timestamp('last_fetch_error_at')->nullable();
            if (!Schema::hasColumn('mail_settings', 'last_fetch_count'))      $table->unsignedInteger('last_fetch_count')->default(0);
            if (!Schema::hasColumn('mail_settings', 'consecutive_failures'))  $table->unsignedInteger('consecutive_failures')->default(0);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('mail_settings')) return;
        Schema::table('mail_settings', function (Blueprint $table) {
            foreach (['last_fetch_at','last_fetch_success_at','last_fetch_error','last_fetch_error_at','last_fetch_count','consecutive_failures'] as $col) {
                if (Schema::hasColumn('mail_settings', $col)) $table->dropColumn($col);
            }
        });
    }
};
