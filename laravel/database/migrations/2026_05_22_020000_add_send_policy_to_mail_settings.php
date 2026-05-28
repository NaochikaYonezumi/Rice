<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 送信ポリシー (= 承認必須 / 自己送信も許可) を mail_settings に追加.
 *
 * 値:
 *   'flexible'         : 自己送信 (即時) と 承認経由 のどちらも選べる (デフォルト)
 *   'approval_required': 承認経由でしか送れない (作成者は下書き作成 + 承認依頼のみ)
 *
 * 既定は flexible (= 既存ユーザに影響しない). 管理者が画面から変更可能.
 * UI 側はこのポリシーを参照して送信ボタンの出し分けを行う.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('mail_settings', function (Blueprint $t) {
            $t->string('send_policy', 32)->default('flexible')->after('consecutive_failures');
        });
    }

    public function down(): void
    {
        Schema::table('mail_settings', function (Blueprint $t) {
            $t->dropColumn('send_policy');
        });
    }
};
