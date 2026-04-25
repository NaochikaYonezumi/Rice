<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 既存データの退避（もしあれば）
        $existing = DB::table('tag_notes')->get();

        Schema::table('tag_notes', function (Blueprint $table) {
            $table->json('content')->nullable()->after('tag');
        });

        foreach ($existing as $row) {
            $wikis = [];
            if (!empty($row->wiki)) {
                $wikis[] = [
                    'title' => '初期Wiki',
                    'body' => $row->wiki
                ];
            }
            if (!empty($row->memo)) {
                $wikis[] = [
                    'title' => '初期メモ',
                    'body' => $row->memo
                ];
            }
            
            DB::table('tag_notes')->where('id', $row->id)->update([
                'content' => json_encode($wikis)
            ]);
        }

        Schema::table('tag_notes', function (Blueprint $table) {
            $table->dropColumn(['memo', 'wiki']);
        });
    }

    public function down(): void
    {
        Schema::table('tag_notes', function (Blueprint $table) {
            $table->text('memo')->nullable();
            $table->longText('wiki')->nullable();
        });

        $existing = DB::table('tag_notes')->get();
        foreach ($existing as $row) {
            $content = json_decode($row->content, true);
            if (is_array($content) && count($content) > 0) {
                DB::table('tag_notes')->where('id', $row->id)->update([
                    'wiki' => $content[0]['body'] ?? '',
                    'memo' => $content[1]['body'] ?? ''
                ]);
            }
        }

        Schema::table('tag_notes', function (Blueprint $table) {
            $table->dropColumn('content');
        });
    }
};
