<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ルーム振り分けルール: 各 ChatRoom に紐付く「メール → ルーム」のフィルタ。
 *
 * type:
 *   - from_address    : Email.from_address が pattern と完全一致
 *   - from_domain     : Email.from_address のドメイン部が pattern と一致 (大文字小文字無視)
 *   - subject_contains: Email.subject に pattern が部分文字列として含まれる (大文字小文字無視)
 *   - to_contains     : Email.to_address に pattern が部分文字列として含まれる
 *
 * 評価方針:
 *   - enabled = true のルールだけ評価
 *   - 1 件でもマッチすればそのスレッドをルームに bundle (syncWithoutDetaching)
 *   - 統計用に match_count / last_matched_at を更新
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_room_routing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_room_id')->constrained('chat_rooms')->cascadeOnDelete();
            // type と pattern: 振り分け条件の種別と値
            $table->string('type', 32);
            $table->string('pattern', 500);
            // enabled: ルールの ON/OFF (削除せず一時無効化したい時用)
            $table->boolean('enabled')->default(true);
            // 統計: ヒット回数と最終ヒット日時 (デバッグ / 振り分け状況の可視化)
            $table->unsignedInteger('match_count')->default(0);
            $table->timestamp('last_matched_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['chat_room_id', 'enabled']);
            $table->index(['type', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_room_routing_rules');
    }
};
