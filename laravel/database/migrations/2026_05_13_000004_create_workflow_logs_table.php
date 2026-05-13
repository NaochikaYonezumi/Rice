<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6-2: 自動割当の履歴を残す ext_workflow_logs テーブル。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ext_workflow_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('email_threads')->cascadeOnDelete();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->unsignedBigInteger('rule_id')->nullable();
            // 'rule' | 'round_robin' | 'manual'
            $table->string('assigned_by', 16);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('assigned_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('rule_id')->references('id')->on('ext_workflow_rules')->nullOnDelete();

            $table->index(['thread_id', 'created_at']);
            $table->index('assigned_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_workflow_logs');
    }
};
