<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * users テーブルに TOTP (認証アプリ) 二段階認証用カラムを追加する.
 *
 * - totp_secret        : Crypt で暗号化した Base32 シークレット (NULL = 未設定)
 * - totp_confirmed_at  : ユーザがアプリ側で初回コード入力に成功した時刻
 *                        (NULL = 設定途中 or 未設定. != NULL = 有効化済)
 *
 * 既存のメール 2FA と並走する形で運用する:
 *   - totp_confirmed_at != NULL のユーザはログイン時に TOTP コードで認証
 *   - そうでなければ既存のメール一時コードフローを使う
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('totp_secret')->nullable()->after('two_factor_recovery_generated_at');
            $table->timestamp('totp_confirmed_at')->nullable()->after('totp_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['totp_secret', 'totp_confirmed_at']);
        });
    }
};
