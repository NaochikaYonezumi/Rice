<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // メール毎のチャット用 (NULL = スレッド全体)
        Schema::table('thread_comments', function (Blueprint $table) {
            $table->foreignId('email_id')->nullable()->after('chat_room_id')
                  ->constrained('emails')->cascadeOnDelete();
            $table->index(['thread_id', 'email_id']);
        });
    }

    public function down(): void
    {
        Schema::table('thread_comments', function (Blueprint $table) {
            $table->dropForeign(['email_id']);
            $table->dropIndex(['thread_id', 'email_id']);
            $table->dropColumn('email_id');
        });
    }
};
