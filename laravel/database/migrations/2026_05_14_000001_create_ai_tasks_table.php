<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('thread_id')->nullable()->index();
            // 'thread_summary' | 'reply_assist' | 'compose_assist'
            $table->string('task_type', 32)->index();
            // 'pending' | 'processing' | 'done' | 'error'
            $table->string('status', 16)->default('pending')->index();
            $table->string('provider', 16)->nullable();
            $table->string('model', 128)->nullable();
            $table->longText('prompt')->nullable();
            $table->longText('result_answer')->nullable();
            $table->json('result_meta')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tasks');
    }
};
