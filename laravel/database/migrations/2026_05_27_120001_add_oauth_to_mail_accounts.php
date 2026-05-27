<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table) {
            // 認証方式. password (既定: ID + Password / アプリパスワード)
            // または oauth_microsoft (Microsoft 365 の XOAUTH2).
            // 将来 oauth_google も追加できる構造にしておく.
            $table->string('auth_type', 32)->default('password')->after('is_active');

            // OAuth プロバイダ ('microsoft' / 'google' 等). auth_type === 'password' なら NULL.
            $table->string('oauth_provider', 32)->nullable()->after('auth_type');

            // OAuth 認可で取得したトークン. 暗号化保存 (encrypted cast).
            $table->text('oauth_access_token')->nullable()->after('oauth_provider');
            $table->text('oauth_refresh_token')->nullable()->after('oauth_access_token');
            $table->timestamp('oauth_expires_at')->nullable()->after('oauth_refresh_token');

            // 同意で取得したスコープ (debug / 失効判定用).
            $table->string('oauth_scope', 500)->nullable()->after('oauth_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('mail_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'auth_type', 'oauth_provider',
                'oauth_access_token', 'oauth_refresh_token',
                'oauth_expires_at', 'oauth_scope',
            ]);
        });
    }
};
