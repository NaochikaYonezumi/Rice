<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // スタンドアロンチャットルーム (メールスレッドに紐付かない)
        Schema::create('chat_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 既存 thread_comments に chat_room_id を追加 (任意 1 つだけ非 NULL)
        Schema::table('thread_comments', function (Blueprint $table) {
            $table->foreignId('chat_room_id')->nullable()->after('thread_id')
                  ->constrained('chat_rooms')->cascadeOnDelete();
            $table->index('chat_room_id');
            // thread_id を nullable に変更 (チャットルーム用は NULL)
        });

        // thread_id は元々 NOT NULL だった可能性。MySQL では型変更が必要。
        // 既存データは全て thread_id != NULL なので、nullable に変えるだけ。
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE thread_comments MODIFY thread_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        Schema::table('thread_comments', function (Blueprint $table) {
            $table->dropForeign(['chat_room_id']);
            $table->dropColumn('chat_room_id');
        });
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE thread_comments MODIFY thread_id BIGINT UNSIGNED NOT NULL');
        Schema::dropIfExists('chat_rooms');
    }
};
