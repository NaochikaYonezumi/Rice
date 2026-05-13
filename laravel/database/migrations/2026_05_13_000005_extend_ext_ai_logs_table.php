<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6-3: AI 返信ログに採用率算定用カラムを追加。
 *
 * - collection         : Phase 6-1 で記録される値
 * - pending_email_id   : 紐付く下書き/承認待ち
 * - was_adopted        : null=未確定, 0=破棄, 1=採用
 * - edit_distance      : 生成案と実送信本文の Levenshtein 距離
 * - sent_at            : SMTP 送信完了タイムスタンプ
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ext_ai_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('ext_ai_logs', 'collection')) {
                $table->string('collection', 100)->nullable()->after('provider');
            }
            if (!Schema::hasColumn('ext_ai_logs', 'pending_email_id')) {
                $table->unsignedBigInteger('pending_email_id')->nullable()->after('email_thread_id');
                $table->foreign('pending_email_id')->references('id')->on('pending_emails')->nullOnDelete();
            }
            if (!Schema::hasColumn('ext_ai_logs', 'was_adopted')) {
                $table->tinyInteger('was_adopted')->nullable()->after('confidence_score');
                $table->index('was_adopted');
            }
            if (!Schema::hasColumn('ext_ai_logs', 'edit_distance')) {
                $table->integer('edit_distance')->nullable()->after('was_adopted');
            }
            if (!Schema::hasColumn('ext_ai_logs', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('edit_distance');
                $table->index('sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ext_ai_logs', function (Blueprint $table) {
            if (Schema::hasColumn('ext_ai_logs', 'pending_email_id')) {
                try { $table->dropForeign(['pending_email_id']); } catch (\Throwable $e) {}
                $table->dropColumn('pending_email_id');
            }
            $cols = [];
            foreach (['collection', 'was_adopted', 'edit_distance', 'sent_at'] as $c) {
                if (Schema::hasColumn('ext_ai_logs', $c)) $cols[] = $c;
            }
            if (!empty($cols)) $table->dropColumn($cols);
        });
    }
};
