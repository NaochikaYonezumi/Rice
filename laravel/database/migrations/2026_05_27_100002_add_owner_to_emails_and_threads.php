<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_threads', function (Blueprint $table) {
            // owner_user_id: そのスレッドの所有者 (個人受信箱から取得した場合に設定)
            // null = システム共通プール (全員が閲覧可能)
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('mail_account_id')->nullable()->constrained('mail_accounts')->nullOnDelete();
            $table->index('owner_user_id');
        });

        Schema::table('emails', function (Blueprint $table) {
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('mail_account_id')->nullable()->constrained('mail_accounts')->nullOnDelete();
            $table->index('owner_user_id');
        });

        Schema::table('pending_emails', function (Blueprint $table) {
            // どのアカウントから送信予定か。null=システム既定 (mail_settings)
            $table->foreignId('mail_account_id')->nullable()->after('reply_type')->constrained('mail_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pending_emails', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mail_account_id');
        });
        Schema::table('emails', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropConstrainedForeignId('mail_account_id');
        });
        Schema::table('email_threads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropConstrainedForeignId('mail_account_id');
        });
    }
};
