<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ルームの親子関係 (フォルダ構成) を実現するため、chat_rooms に self-FK を追加.
 *
 * 仕様:
 *  - parent_room_id NULL なら ルート (= 階層なしの普通のルーム)
 *  - 親に何かが入っているなら そのルームは下位ルームになる
 *  - 親が削除されたら子も連動して削除 (cascade)
 *
 * 階層深さ: アプリ側 (UI / クエリ) は再帰 (深さ無制限) で扱う想定.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_rooms', function (Blueprint $t) {
            $t->unsignedBigInteger('parent_room_id')->nullable()->after('created_by_user_id');
            $t->foreign('parent_room_id')->references('id')->on('chat_rooms')->cascadeOnDelete();
            $t->index('parent_room_id');
        });
    }

    public function down(): void
    {
        Schema::table('chat_rooms', function (Blueprint $t) {
            $t->dropForeign(['parent_room_id']);
            $t->dropIndex(['parent_room_id']);
            $t->dropColumn('parent_room_id');
        });
    }
};
