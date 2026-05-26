<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * 迷惑メール (MailBlockRule) にも AND/OR ネスト条件を追加.
 *
 * ChatRoomRoutingRule で既に導入済みの logic+conditions パターンを mail_block_rules に対しても
 * 適用する. 既存の単一条件ルール (type+pattern) は backfill により conditions ツリーへ移行.
 *
 * スキーマ:
 *   - logic       (string nullable): 'and'|'or'. NULL はレガシー単一条件 (type+pattern) で評価.
 *   - conditions  (JSON nullable)  : 再帰ツリー. ノードは
 *                                       リーフ:  { "type": "<TYPE>", "pattern": "<string>" }
 *                                       グループ: { "logic": "and"|"or", "items": [<node>, ...] }
 *
 * 既存制約の変更:
 *   - unique(type, pattern) は単一条件時の重複防止用だが、ネスト条件ルールは同じ first-leaf
 *     を持つ複数ルールが正当に並びうる (例: (A AND B) と (A AND C)). 制約を維持すると
 *     意図せぬ衝突が起こるため drop する. controller 側でレガシールールのみ firstOrNew で
 *     重複統合する.
 *
 * 後方互換:
 *   - 既存行を { logic:'or', items:[{type,pattern}] } で conditions に backfill.
 *   - matches() 経路は conditions があればそれを使い、無ければ type+pattern にフォールバック.
 */
return new class extends Migration {
    public function up(): void
    {
        // 1) ユニーク制約を先に外す (カラム追加と独立に行いたい).
        Schema::table('mail_block_rules', function (Blueprint $t) {
            // MySQL では unique index 名は "mail_block_rules_type_pattern_unique" が既定.
            // try/catch ガード相当: drop は existence 不問だが、Doctrine がカラム不在で失敗する
            // 環境を考慮して dropUnique で対応.
            try {
                $t->dropUnique(['type', 'pattern']);
            } catch (\Throwable) {
                // 既に外れていれば無視.
            }
        });

        // 2) logic / conditions カラム追加.
        Schema::table('mail_block_rules', function (Blueprint $t) {
            $t->string('logic', 8)->nullable()->after('pattern');
            $t->json('conditions')->nullable()->after('logic');
        });

        // 3) 既存行を conditions ツリーへ backfill.
        //    1 リーフだけの OR グループとして格納 (= matches() 上は単一条件と等価).
        $rows = DB::table('mail_block_rules')->select('id', 'type', 'pattern')->get();
        foreach ($rows as $r) {
            $tree = [
                'logic' => 'or',
                'items' => [
                    ['type' => $r->type, 'pattern' => $r->pattern],
                ],
            ];
            DB::table('mail_block_rules')
                ->where('id', $r->id)
                ->update([
                    'logic'      => 'or',
                    'conditions' => json_encode($tree, JSON_UNESCAPED_UNICODE),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('mail_block_rules', function (Blueprint $t) {
            $t->dropColumn(['logic', 'conditions']);
            // unique を復元する (down で対称性を保つ).
            try {
                $t->unique(['type', 'pattern']);
            } catch (\Throwable) {
                // 既に存在していれば無視.
            }
        });
    }
};
