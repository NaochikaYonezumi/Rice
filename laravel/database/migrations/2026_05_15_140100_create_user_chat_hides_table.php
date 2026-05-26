<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ユーザーごとに、サイドバーから非表示にしたいルーム/スレッドを記録
        Schema::create('user_chat_hides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('hidable_type', ['room', 'thread']);
            $table->unsignedBigInteger('hidable_id');
            $table->timestamps();
            $table->unique(['user_id', 'hidable_type', 'hidable_id']);
            $table->index(['hidable_type', 'hidable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_chat_hides');
    }
};
