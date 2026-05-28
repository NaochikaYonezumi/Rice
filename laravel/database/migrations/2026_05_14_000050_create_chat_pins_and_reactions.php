<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // チャットのピン留め (per-user, スレッドとルーム両対応)
        Schema::create('user_chat_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('pinnable_type', 16);  // 'thread' | 'room'
            $table->unsignedBigInteger('pinnable_id');
            $table->timestamps();
            $table->unique(['user_id', 'pinnable_type', 'pinnable_id']);
            $table->index(['pinnable_type', 'pinnable_id']);
        });

        // チャットメッセージへの絵文字リアクション
        Schema::create('chat_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained('thread_comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestamps();
            $table->unique(['comment_id', 'user_id', 'emoji']);
            $table->index('comment_id');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('chat_reactions');
        Schema::dropIfExists('user_chat_pins');
    }
};
