<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 迷惑メール判定ルール用テーブル。
 *
 * 列挙:
 *  - sender_address … 完全一致 (例: "foo@example.com")
 *  - sender_domain  … 後方一致 (例: "example.com" → "@example.com" でマッチ)
 *  - subject_keyword … subject に含まれる場合
 *  - body_keyword    … body_text / body_html に含まれる場合
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('mail_block_rules', function (Blueprint $table) {
            $table->id();
            $table->enum('type', [
                'sender_address',
                'sender_domain',
                'subject_keyword',
                'body_keyword',
            ]);
            $table->string('pattern', 255);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('match_count')->default(0);
            $table->timestamp('last_matched_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'enabled']);
            $table->unique(['type', 'pattern']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_block_rules');
    }
};
