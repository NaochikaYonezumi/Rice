<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * scraped_urls にナレッジソース 1 件あたり 複数のコレクションを紐付けられる
 * `collections` JSON カラムを追加する。
 *
 * 既存の `collection` (VARCHAR 64, 単一値) はそのまま残し、
 *   - 後方互換のため「主コレクション = 配列の先頭」として運用
 *   - 検索/集計は `collections` 配列を JSON_CONTAINS で行う
 * という二段構えにする。これでフィルタや RAG クエリの段階的な移行が可能。
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('scraped_urls')) return;

        if (!Schema::hasColumn('scraped_urls', 'collections')) {
            Schema::table('scraped_urls', function (Blueprint $table) {
                // MySQL/MariaDB なら JSON、それ以外は text に降格して自前で json_decode する
                if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                    $table->json('collections')->nullable()->after('collection');
                } else {
                    $table->text('collections')->nullable()->after('collection');
                }
            });
        }

        // 既存レコードを 単一値 collection → 配列 [collection] でバックフィル
        try {
            DB::table('scraped_urls')
                ->whereNull('collections')
                ->orWhere('collections', '')
                ->orderBy('id')
                ->chunkById(500, function ($rows) {
                    foreach ($rows as $row) {
                        $name = trim((string) ($row->collection ?? ''));
                        if ($name === '') $name = 'default';
                        DB::table('scraped_urls')
                            ->where('id', $row->id)
                            ->update(['collections' => json_encode([$name], JSON_UNESCAPED_UNICODE)]);
                    }
                });
        } catch (\Throwable $e) {
            // バックフィル失敗してもスキーマ追加は成功扱いにする (実行時にコントローラ側で吸収)
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('scraped_urls') && Schema::hasColumn('scraped_urls', 'collections')) {
            Schema::table('scraped_urls', function (Blueprint $table) {
                $table->dropColumn('collections');
            });
        }
    }
};
