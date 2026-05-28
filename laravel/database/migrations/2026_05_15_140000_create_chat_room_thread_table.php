<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // チャットルームに紐づける email_thread の m2m 中間テーブル
        // (1つのルームに複数スレッドをまとめられる)
        Schema::create('chat_room_thread', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_room_id')->constrained('chat_rooms')->cascadeOnDelete();
            $table->foreignId('email_thread_id')->constrained('email_threads')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['chat_room_id', 'email_thread_id']);
            $table->index('email_thread_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_room_thread');
    }
};
