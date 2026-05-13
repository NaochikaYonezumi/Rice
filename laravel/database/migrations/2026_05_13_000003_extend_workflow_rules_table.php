<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6-2: 送信元アドレス/ドメイン別の自動割当ルール拡張。
 *
 * 既存の ext_workflow_rules には condition_type/condition_operator/condition_value/actions
 * が存在するが、Phase 6-2 仕様では下記カラムを追加して、より直接的なマッチング
 * (match_type/match_value/assign_user_id) を可能にする。
 *
 * 既存のカラムは互換性のため残し、新しいエンジンは新カラムを優先する。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ext_workflow_rules', function (Blueprint $table) {
            if (!Schema::hasColumn('ext_workflow_rules', 'match_type')) {
                // ENUM 相当: 'from_address', 'from_domain', 'subject_contains', 'to_address'
                // ポータビリティのため string で表現し、モデル側で値域を制限する
                $table->string('match_type', 32)->default('from_address')->after('name');
            }
            if (!Schema::hasColumn('ext_workflow_rules', 'match_value')) {
                $table->string('match_value', 255)->nullable()->after('match_type');
            }
            if (!Schema::hasColumn('ext_workflow_rules', 'assign_user_id')) {
                $table->unsignedBigInteger('assign_user_id')->nullable()->after('match_value');
                $table->index('assign_user_id');
                // 外部キー (users 削除時は NULL に戻す)
                $table->foreign('assign_user_id')->references('id')->on('users')->nullOnDelete();
            }
        });

        // 同じ (match_type, match_value) のルールが重複しないように
        // (NULL を許す環境差異があるため、index を貼るだけにする)
        if (!$this->indexExists('ext_workflow_rules', 'ext_workflow_rules_match_type_match_value_index')) {
            Schema::table('ext_workflow_rules', function (Blueprint $table) {
                $table->index(['match_type', 'match_value'], 'ext_workflow_rules_match_type_match_value_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('ext_workflow_rules', function (Blueprint $table) {
            if (Schema::hasColumn('ext_workflow_rules', 'assign_user_id')) {
                try { $table->dropForeign(['assign_user_id']); } catch (\Throwable $e) {}
                $table->dropColumn('assign_user_id');
            }
            if (Schema::hasColumn('ext_workflow_rules', 'match_value')) {
                $table->dropColumn('match_value');
            }
            if (Schema::hasColumn('ext_workflow_rules', 'match_type')) {
                $table->dropColumn('match_type');
            }
            try { $table->dropIndex('ext_workflow_rules_match_type_match_value_index'); } catch (\Throwable $e) {}
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $connection = Schema::getConnection();
            $driver = $connection->getDriverName();
            if ($driver === 'mysql') {
                $rows = $connection->select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
                return !empty($rows);
            }
            // SQLite / その他は雑に true 扱いでスキップ (= 二重作成を回避)
        } catch (\Throwable $e) { /* noop */ }
        return false;
    }
};
