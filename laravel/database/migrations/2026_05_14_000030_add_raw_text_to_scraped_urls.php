<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('scraped_urls', function (Blueprint $table) {
            // 抽出済みテキスト本文 (ファイル/メール由来は格納、URL は ベクター DB から取得)
            $table->longText('raw_text')->nullable()->after('meta');
        });
    }

    public function down(): void
    {
        Schema::table('scraped_urls', function (Blueprint $table) {
            $table->dropColumn('raw_text');
        });
    }
};
