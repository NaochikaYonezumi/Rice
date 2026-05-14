<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('scraped_urls', function (Blueprint $table) {
            // 'url' | 'file' | 'email'
            $table->string('source_type', 16)->default('url')->after('url');
            // ユーザーが見る表示名 (ファイル名 / メール件名 等)
            $table->string('title', 500)->nullable()->after('source_type');
            // ファイルのストレージパス / メール ID 等の自由情報
            $table->json('meta')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('scraped_urls', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'title', 'meta']);
        });
    }
};
