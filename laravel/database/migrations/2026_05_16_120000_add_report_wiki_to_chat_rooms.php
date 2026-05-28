<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table) {
            // 共有ルーム専用: レポート (経過・成果まとめ) と Wiki (ナレッジ・手順) の本文
            $table->longText('report_content')->nullable()->after('is_private');
            $table->longText('wiki_content')->nullable()->after('report_content');
            $table->timestamp('report_updated_at')->nullable()->after('wiki_content');
            $table->timestamp('wiki_updated_at')->nullable()->after('report_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->dropColumn(['report_content', 'wiki_content', 'report_updated_at', 'wiki_updated_at']);
        });
    }
};
