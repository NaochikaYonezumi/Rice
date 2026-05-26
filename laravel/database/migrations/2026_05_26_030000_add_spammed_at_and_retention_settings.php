<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * 迷惑メールの自動完全削除 + ゴミ箱/迷惑メールの保持期間を管理者が設定できるように.
 *
 * 追加カラム:
 *   email_threads.spammed_at         (nullable timestamp)
 *       - status='spam' に切り替わった瞬間にここへ now() を入れる.
 *       - spam_retention_days を超えたものを `mail:purge-spam` が cascade ハード DELETE.
 *       - 既存の status='spam' 行には migrate 時に created_at (= 取り込み時刻) で埋めておく
 *         (受信時に spam 判定されたものはそれが起点). created_at が null なら now() に fallback.
 *
 *   mail_settings.trash_retention_days  (unsigned int, default 30)
 *   mail_settings.spam_retention_days   (unsigned int, default 30)
 *       - 管理者が「設定 → メール → 保持期間」で変更可能.
 *       - 既定 30 日. 0 にすれば自動削除無効化 (将来追加するなら 0 = 無効化として実装).
 *         本マイグレーションでは min:1 を運用ルールにする (UI 側で validate).
 *
 * EmailThread::TRASH_RETENTION_DAYS / SPAM_RETENTION_DAYS 定数はフォールバック値として残す
 * (mail_settings 行が無い環境 / カラム未存在環境でも動くように).
 */
return new class extends Migration {
    public function up(): void
    {
        // (1) email_threads.spammed_at
        Schema::table('email_threads', function (Blueprint $t) {
            $t->timestamp('spammed_at')->nullable()->after('trashed_at');
            $t->index('spammed_at');
        });

        // 既存の status='spam' 行を created_at で backfill (= 取り込み済の spam も期限カウント対象になる).
        // データ量に応じて 1 文 UPDATE で済ませる (千件規模なら 1 秒以内).
        // SQLite / MySQL 共通の COALESCE で created_at が null だったら now() に倒す.
        DB::statement("UPDATE email_threads
                       SET spammed_at = COALESCE(created_at, ?)
                       WHERE status = ? AND spammed_at IS NULL", [now(), 'spam']);

        // (2) mail_settings に保持期間カラム.
        Schema::table('mail_settings', function (Blueprint $t) {
            // 既存の send_policy の後ろに足す (順番は機能的には無関係だが運用上わかりやすい場所).
            $t->unsignedInteger('trash_retention_days')->default(30)->after('send_policy');
            $t->unsignedInteger('spam_retention_days')->default(30)->after('trash_retention_days');
        });
    }

    public function down(): void
    {
        Schema::table('email_threads', function (Blueprint $t) {
            $t->dropIndex(['spammed_at']);
            $t->dropColumn('spammed_at');
        });
        Schema::table('mail_settings', function (Blueprint $t) {
            $t->dropColumn(['trash_retention_days', 'spam_retention_days']);
        });
    }
};
