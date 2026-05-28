<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('user_ai_skills', function (Blueprint $table) {
            // ピッカーに表示するかどうかをカテゴリ別に制御
            $table->boolean('show_in_summary')->default(true)->after('is_default_reply');
            $table->boolean('show_in_reply')->default(true)->after('show_in_summary');
        });

        // 既存スキルに skill_key からの推測値を入れる
        // - summarize / action_items は要約寄り → reply ピッカーから外す
        // - reply は返信寄り → summary ピッカーから外す
        // 既存ユーザーの違和感を減らすため、デフォルトはどちらも true にしておくほうが安全。
        // → ここでは触らずデフォルト true のままにする。
    }

    public function down(): void
    {
        Schema::table('user_ai_skills', function (Blueprint $table) {
            $table->dropColumn(['show_in_summary', 'show_in_reply']);
        });
    }
};
