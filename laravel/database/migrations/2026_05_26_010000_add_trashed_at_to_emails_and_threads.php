<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ゴミ箱 (Trash) 機能用のマイグレーション.
 *
 * 仕様:
 *   - スレッド/個別メールを「削除」したとき、即座にハード DELETE せず
 *     trashed_at にタイムスタンプを入れて論理削除する.
 *   - 30 日経過後、`mail:purge-trash` コマンドがハード DELETE する (cascade).
 *   - 通常ビュー (inbox / hold / completed / spam) では trashed_at IS NOT NULL を除外する.
 *   - ゴミ箱ビューでのみ表示する.
 *
 * 設計上の決定:
 *   - SoftDeletes trait を使わない理由:
 *     既存コードは `EmailThread::status` で複数状態を持ち、削除取り消し (= 復元)
 *     も「status を inbox に戻す」操作になる. SoftDeletes の `deleted_at` だと
 *     status カラムと意味が二重化するので、独立した `trashed_at` を導入する.
 *   - スレッドにも個別メールにも両方追加する理由:
 *     ユーザ要望「スレッド + 個別メール両方をゴミ箱対応」.
 *     スレッドが trash でも個別メールが trash でなければ、復元時に個別メールはそのまま生きる.
 *   - status='trash' との両立:
 *     スレッドは status='trash' AND trashed_at=now() の両方を立てる.
 *     status だけでなく trashed_at も持たせるのは、30 日カウントの起点を厳密にするため
 *     (status は手動で再変更されうるが、trashed_at は purge コマンドの判定にのみ使う).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('email_threads', function (Blueprint $t) {
            // status='trash' と組で立てる. 30 日カウントの起点.
            // index は purge クエリ (WHERE trashed_at < NOW() - 30d) のためにも有用.
            $t->timestamp('trashed_at')->nullable()->after('last_email_at');
            $t->index('trashed_at');
        });

        Schema::table('emails', function (Blueprint $t) {
            // 個別メール単位のゴミ箱. スレッド全体ではなく特定メールだけ消したい時に使う.
            $t->timestamp('trashed_at')->nullable()->after('received_at');
            $t->index('trashed_at');
        });
    }

    public function down(): void
    {
        Schema::table('email_threads', function (Blueprint $t) {
            $t->dropIndex(['trashed_at']);
            $t->dropColumn('trashed_at');
        });
        Schema::table('emails', function (Blueprint $t) {
            $t->dropIndex(['trashed_at']);
            $t->dropColumn('trashed_at');
        });
    }
};
