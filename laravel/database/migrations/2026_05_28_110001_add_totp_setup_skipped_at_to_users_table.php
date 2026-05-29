<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TOTP セットアップを「あとで」したユーザの記録列.
 * - NULL  : まだ案内されていない / スキップしていない → ログイン後に setup ページへ自動誘導
 * - 値あり: ユーザが「あとで」を押した時刻. 以降は自動誘導しない (= プロフィール画面から手動で設定)
 *
 * TOTP を確定する (totp_confirmed_at が入る) 時に, この列もクリア (NULL) してよい.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('totp_setup_skipped_at')->nullable()->after('totp_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('totp_setup_skipped_at');
        });
    }
};
