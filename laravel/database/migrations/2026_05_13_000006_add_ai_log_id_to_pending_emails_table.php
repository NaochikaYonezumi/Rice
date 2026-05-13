<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6-3: pending_emails が生成元の AI ログを参照できるようにする。
 * 採用率判定 (AdoptionEvaluator) で使用。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('pending_emails', function (Blueprint $table) {
            if (!Schema::hasColumn('pending_emails', 'ai_log_id')) {
                $table->unsignedBigInteger('ai_log_id')->nullable()->after('memo');
                $table->foreign('ai_log_id')->references('id')->on('ext_ai_logs')->nullOnDelete();
                $table->index('ai_log_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pending_emails', function (Blueprint $table) {
            if (Schema::hasColumn('pending_emails', 'ai_log_id')) {
                try { $table->dropForeign(['ai_log_id']); } catch (\Throwable $e) {}
                $table->dropColumn('ai_log_id');
            }
        });
    }
};
