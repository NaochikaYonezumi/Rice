<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6-1: 顧客に RAG コレクションが紐付いていない場合の既定コレクションを
 * グローバル設定として持たせる。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->string('default_collection', 100)->nullable()->default('default')->after('default_model');
        });
    }

    public function down(): void
    {
        Schema::table('ai_settings', function (Blueprint $table) {
            $table->dropColumn('default_collection');
        });
    }
};
