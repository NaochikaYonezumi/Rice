<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pending_emails', function (Blueprint $table) {
            // 却下された元の pending_email の id。
            // 却下時に作成する下書きへセットし、その下書きが再承認依頼として送信された際に
            // 元の却下済レコードを削除する目印として使用する。
            $table->unsignedBigInteger('source_rejected_id')->nullable()->after('rejected_at');
            $table->index('source_rejected_id');
        });
    }

    public function down(): void
    {
        Schema::table('pending_emails', function (Blueprint $table) {
            $table->dropIndex(['source_rejected_id']);
            $table->dropColumn('source_rejected_id');
        });
    }
};
