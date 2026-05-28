<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * pending_emails テーブルに body_html カラムを追加する。
 *
 * 目的: メール送信を multipart (text + html) に対応させ、
 *  - リッチエディタで作成された HTML 本文を保存
 *  - 送信時に text/html を併送 (受信側のクライアントで HTML 表示可)
 *  - 既存の plain `body` カラムは互換のため残し、HTML 未入力の場合のフォールバックに使う
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('pending_emails', function (Blueprint $table) {
            // body の直後に置く。 LONGTEXT 相当 ($table->longText()) で十分大きく確保。
            $table->longText('body_html')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('pending_emails', function (Blueprint $table) {
            $table->dropColumn('body_html');
        });
    }
};
