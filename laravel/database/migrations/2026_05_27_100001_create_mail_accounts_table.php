<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');                  // 表示用ラベル (例: 個人Gmail)
            $table->string('email_address');         // 送信時の From 表示用 / マッチング用
            $table->boolean('is_active')->default(true);

            // 受信設定 (IMAP/POP3/disabled)
            $table->string('inbox_protocol')->default('imap'); // imap / pop3 / disabled
            $table->string('imap_host')->nullable();
            $table->integer('imap_port')->default(993);
            $table->string('imap_encryption')->default('ssl');
            $table->string('imap_username')->nullable();
            $table->text('imap_password')->nullable();
            $table->string('imap_folder')->default('INBOX');
            $table->string('pop_host')->nullable();
            $table->integer('pop_port')->default(995);
            $table->string('pop_encryption')->default('ssl');
            $table->string('pop_username')->nullable();
            $table->text('pop_password')->nullable();

            // 送信設定 (SMTP)
            $table->boolean('smtp_enabled')->default(false);
            $table->string('smtp_host')->nullable();
            $table->integer('smtp_port')->default(587);
            $table->string('smtp_encryption')->default('tls');
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();
            $table->string('smtp_from_name')->nullable();

            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_accounts');
    }
};
