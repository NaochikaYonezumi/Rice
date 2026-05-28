<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_room_chat_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('chat_room_id');
            $table->timestamp('last_read_at');
            $table->timestamps();
            $table->unique(['user_id', 'chat_room_id']);
            $table->index('chat_room_id');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('user_room_chat_reads');
    }
};
