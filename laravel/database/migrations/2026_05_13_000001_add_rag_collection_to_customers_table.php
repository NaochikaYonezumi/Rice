<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6-1: 顧客ごとに RAG コレクションを指定可能にする。
 * 例: MF (顧客 A) → 'mf_faq', Hive (顧客 B) → 'hive_faq'。
 * null の場合は AiSetting.default_collection、または 'default' を使う。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('rag_collection', 100)->nullable()->after('domain');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('rag_collection');
        });
    }
};
