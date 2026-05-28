<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * chat_room_thread pivot に「どの振り分けルールでこのスレッドがこのルームに入ったか」の
 * 監査情報を保存する 3 列を追加.
 *
 *   matched_rule_type    : 'from_address' / 'from_domain' / 'subject_contains' / 'to_contains' / 'manual' / null
 *   matched_rule_pattern : マッチした実値 (例: "support@univ-x.ac.jp")
 *   matched_at           : 振り分け時刻
 *
 * NULL のままなら「手動で追加された (= L キー等)」または「マイグレーション前から存在」.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_room_thread', function (Blueprint $t) {
            $t->string('matched_rule_type', 32)->nullable()->after('email_thread_id');
            $t->string('matched_rule_pattern', 500)->nullable()->after('matched_rule_type');
            $t->timestamp('matched_at')->nullable()->after('matched_rule_pattern');
        });
    }

    public function down(): void
    {
        Schema::table('chat_room_thread', function (Blueprint $t) {
            $t->dropColumn(['matched_rule_type', 'matched_rule_pattern', 'matched_at']);
        });
    }
};
