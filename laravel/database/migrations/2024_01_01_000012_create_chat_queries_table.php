<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_queries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('question');
            $table->longText('answer')->nullable();
            $table->json('sources')->nullable();
            $table->string('status', 16)->default('pending'); // pending, done, error
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_queries');
    }
};
