<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * 振り分けルールの AND/OR ネスト条件化.
 *
 * 背景:
 *   既存の chat_room_routing_rules は (type, pattern) 単一条件で、ルーム内に複数ルールを
 *   置いた場合は暗黙的に OR 扱い. ユーザ要望:
 *     「1 ルール内で (A AND B) OR C のような複合条件を作りたい」.
 *
 * スキーマ追加:
 *   - logic (string nullable):
 *       'and' / 'or' / NULL. NULL は「下位互換: type+pattern の単一条件」.
 *   - conditions (JSON nullable):
 *       再帰可能なツリー構造. 各ノードは
 *         リーフ:  { "type": "from_domain", "pattern": "foo.com" }
 *         グループ: { "logic": "and"|"or", "items": [<node>, ...] }
 *       conditions カラムにはルートグループを格納する.
 *
 * 後方互換:
 *   - 既存行 (logic NULL, conditions NULL): そのまま type+pattern として評価される.
 *   - 新規 / 更新時: アプリ側が conditions を JSON で書き込み、type+pattern には
 *     ツリー先頭のリーフを fallback 用に保存する (一覧画面の簡易表示用).
 *
 * このマイグレーションでは既存行も「単一条件 = 1 ノードの OR グループ」として
 * conditions に backfill しておく. これにより以降のコードは常に conditions を見れば良い.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_room_routing_rules', function (Blueprint $t) {
            // 'and' / 'or'. NULL の場合はレガシー単一条件 (type+pattern).
            $t->string('logic', 8)->nullable()->after('pattern');
            // 再帰可能なルートグループ. アプリ側でデコード.
            $t->json('conditions')->nullable()->after('logic');
        });

        // 既存行を { logic: 'or', items: [{type, pattern}] } として埋める.
        // OR ルートに 1 リーフ — 評価上は単一条件と等価. 以降の matcher が常に
        // conditions を読めば良くなる.
        $rows = DB::table('chat_room_routing_rules')->select('id', 'type', 'pattern')->get();
        foreach ($rows as $r) {
            $tree = [
                'logic' => 'or',
                'items' => [
                    ['type' => $r->type, 'pattern' => $r->pattern],
                ],
            ];
            DB::table('chat_room_routing_rules')
                ->where('id', $r->id)
                ->update([
                    'logic'      => 'or',
                    'conditions' => json_encode($tree, JSON_UNESCAPED_UNICODE),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('chat_room_routing_rules', function (Blueprint $t) {
            $t->dropColumn(['logic', 'conditions']);
        });
    }
};
