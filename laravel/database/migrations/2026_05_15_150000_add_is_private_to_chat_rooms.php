<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table) {
            // true = 作成者のみ閲覧可 (個人用)
            // false = 全員共有 (デフォルト)
            $table->boolean('is_private')->default(false)->after('created_by_user_id');
            $table->index(['is_private', 'created_by_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->dropColumn('is_private');
        });
    }
};
