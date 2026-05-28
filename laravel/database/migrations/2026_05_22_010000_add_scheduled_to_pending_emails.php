<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 予約送信 (scheduled-send) のため pending_emails に列を追加.
 *
 *   scheduled_for : 指定送信日時 (NULL なら即時 / 通常下書き).
 *                   status='scheduled' の行に対してのみ意味を持つ.
 *   send_attempts : 送信試行回数 (送信失敗時のリトライ抑制 / ログ).
 *   last_send_error : 直近の送信失敗エラー (UI で表示するため保存).
 *
 * status の値は文字列カラム (enum でなく VARCHAR) なので、'scheduled' を新たに使うだけで OK.
 * STATUS_SCHEDULED 定数の意味:
 *   - ユーザが「予約送信」を選択 → status = scheduled, scheduled_for = <日時>
 *   - cron で動くコマンド mail:send-scheduled が scheduled_for <= now() の行を pick
 *   - 送信成功時は status を approved (= 送信済み扱い) に, 失敗時は last_send_error を保存
 *
 * scheduled_for にインデックスを張ってスケジューラの WHERE が高速になるようにする.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('pending_emails', function (Blueprint $t) {
            $t->timestamp('scheduled_for')->nullable()->after('status');
            $t->unsignedInteger('send_attempts')->default(0)->after('scheduled_for');
            $t->text('last_send_error')->nullable()->after('send_attempts');
            // スケジューラのバッチ pick で WHERE status='scheduled' AND scheduled_for <= NOW() が走る
            $t->index(['status', 'scheduled_for'], 'pe_status_sched_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pending_emails', function (Blueprint $t) {
            $t->dropIndex('pe_status_sched_idx');
            $t->dropColumn(['scheduled_for', 'send_attempts', 'last_send_error']);
        });
    }
};
