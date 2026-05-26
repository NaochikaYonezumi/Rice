<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1 ルームに対して複数の Wiki カードを持てるようにする。
 *
 * 既存の chat_rooms.wiki_content (単一テキスト) は 1 枚目のカードへ
 * 自動移行する。chat_rooms.wiki_content / wiki_updated_at は互換用に
 * 残し、新規書き込みは chat_room_wikis 側に対して行う。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_room_wikis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_room_id')
                ->constrained('chat_rooms')
                ->cascadeOnDelete();
            $table->string('title', 255)->default('メモ');
            $table->text('content')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index(['chat_room_id', 'sort_order']);
        });

        // 既存 wiki_content を 1 枚目のカードへ移行
        if (Schema::hasColumn('chat_rooms', 'wiki_content')) {
            $rows = DB::table('chat_rooms')
                ->select('id', 'wiki_content', 'wiki_updated_at')
                ->whereNotNull('wiki_content')
                ->where('wiki_content', '!=', '')
                ->get();

            $now = now();
            $insert = [];
            foreach ($rows as $r) {
                $insert[] = [
                    'chat_room_id' => $r->id,
                    'title'        => 'メモ',
                    'content'      => $r->wiki_content,
                    'sort_order'   => 0,
                    'created_at'   => $r->wiki_updated_at ?: $now,
                    'updated_at'   => $r->wiki_updated_at ?: $now,
                ];
            }
            if (!empty($insert)) {
                foreach (array_chunk($insert, 200) as $chunk) {
                    DB::table('chat_room_wikis')->insert($chunk);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_room_wikis');
    }
};
