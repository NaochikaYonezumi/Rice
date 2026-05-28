<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pending_emails', function (Blueprint $table) {
            // 承認を依頼する相手 (NULL の場合は誰でも承認可能 = 旧来のロジック)
            $table->foreignId('target_approver_user_id')
                ->nullable()
                ->after('approved_by_user_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['target_approver_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('pending_emails', function (Blueprint $table) {
            $table->dropIndex(['target_approver_user_id', 'status']);
            $table->dropForeign(['target_approver_user_id']);
            $table->dropColumn('target_approver_user_id');
        });
    }
};
