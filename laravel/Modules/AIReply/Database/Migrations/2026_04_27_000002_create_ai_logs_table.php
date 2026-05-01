<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ext_ai_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_thread_id')->constrained('email_threads')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users');
            $table->string('provider');
            $table->text('prompt_summary');
            $table->text('generated_reply');
            $table->integer('confidence_score')->nullable();
            $table->json('safety_checks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ext_ai_logs');
    }
};
