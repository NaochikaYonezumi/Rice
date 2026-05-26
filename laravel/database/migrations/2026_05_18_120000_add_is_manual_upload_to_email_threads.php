<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 添付ファイル管理画面の「アップロード」機能から生成された合成スレッドを
 * 通常のメール一覧に出さないためのフラグ。
 *
 * - true: 手動アップロード経由で作られた合成スレッド (UI のメール一覧では非表示)
 * - false: 通常の受信/送信スレッド (デフォルト)
 *
 * 添付ファイル一覧やルームのバンドル先には引き続き出現する。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('email_threads', function (Blueprint $table) {
            $table->boolean('is_manual_upload')->default(false)->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('email_threads', function (Blueprint $table) {
            $table->dropIndex(['is_manual_upload']);
            $table->dropColumn('is_manual_upload');
        });
    }
};
