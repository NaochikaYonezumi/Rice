<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6-4: Agent 別メール署名フィールドを users に追加。
 *
 * - signature_text     : プレーンテキスト署名
 * - signature_html     : HTML 署名 (リッチエディタ対応、許可タグ限定でサニタイズ済を保存)
 * - signature_enabled  : 自動付与を有効にするか
 *
 * いずれも NULL 許可。既存ユーザーは NULL のまま → AiSetting.agent_signature にフォールバック。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'signature_text')) {
                $table->text('signature_text')->nullable()->after('role');
            }
            if (!Schema::hasColumn('users', 'signature_html')) {
                $table->longText('signature_html')->nullable()->after('signature_text');
            }
            if (!Schema::hasColumn('users', 'signature_enabled')) {
                $table->boolean('signature_enabled')->default(true)->after('signature_html');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $drop = [];
            foreach (['signature_text', 'signature_html', 'signature_enabled'] as $c) {
                if (Schema::hasColumn('users', $c)) $drop[] = $c;
            }
            if (!empty($drop)) $table->dropColumn($drop);
        });
    }
};
