<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 添付ファイル一覧 (/attachments) でユーザが「削除」を押した時に、
 * 実ファイル・DB レコードを削除せず、一覧画面上だけ非表示にするためのフラグ。
 *
 * メール本文・スレッド詳細・チャットの添付プレビューはこのフラグの影響を受けない。
 * これにより「添付メニューを掃除する」操作で過去のメール本文の添付が壊れない。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('email_attachments', function (Blueprint $table) {
            $table->boolean('hidden_from_list')->default(false)->after('disk_path')->index();
        });
    }

    public function down(): void
    {
        Schema::table('email_attachments', function (Blueprint $table) {
            $table->dropIndex(['hidden_from_list']);
            $table->dropColumn('hidden_from_list');
        });
    }
};
