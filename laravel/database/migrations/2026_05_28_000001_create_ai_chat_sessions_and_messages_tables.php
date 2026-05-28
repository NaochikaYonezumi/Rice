<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI チャットセッション + メッセージのテーブルを作成する.
 *
 * 用途: 「AI要約」「AI返信案」を 1 ショットではなくチャット形式で複数ターンやり取りして
 *       ブラッシュアップできるようにするための永続ストア.
 *
 * 構造:
 *   ai_chat_sessions      : (user_id, thread_id, kind) で一意. 1 スレッド 1 種類 1 ユーザに対し
 *                           セッションは 1 個だけ作る (再訪したら続きから).
 *   ai_chat_messages      : セッション配下にユーザ / アシスタントのメッセージを時系列で積む.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('thread_id')->constrained('email_threads')->cascadeOnDelete();
            // 'summary' (AI要約) or 'reply' (AI返信案). 将来 'general' を足しても良い.
            $table->string('kind', 16);
            // LLM 設定. セッション中は基本固定 (会話の一貫性のため).
            $table->string('provider', 32)->nullable();   // 'ollama' / 'claude' / 'gemini'
            $table->string('model', 128)->nullable();     // e.g. qwen2.5:3b
            // system プロンプト (AiSkill から起動時にコピー). セッション中は固定.
            $table->text('system_prompt')->nullable();
            // ユーザが選んだスキルキー (履歴用)
            $table->string('skill_key', 64)->nullable();
            // 直近の活動時刻 (ソート用)
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            // 同じユーザが同じスレッドで同じ kind をもう一度開いたら, 既存セッションを引き継ぐ.
            $table->unique(['user_id', 'thread_id', 'kind'], 'ai_chat_sessions_uniq');
            $table->index(['thread_id', 'kind']);
        });

        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('ai_chat_sessions')->cascadeOnDelete();
            // 'user' / 'assistant'. system プロンプトは ai_chat_sessions.system_prompt に保持.
            $table->string('role', 16);
            // 本文 (Markdown 想定). サイズは TEXT (= 64KB) で十分.
            $table->text('content');
            // 処理状態: 'done' (= 完了) / 'pending' (= assistant 生成中) / 'error'.
            // user メッセージは常に 'done'. assistant のみ pending → done/error を取る.
            $table->string('status', 16)->default('done');
            // エラー時の詳細
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            // 計測用 (任意).
            $table->unsignedInteger('elapsed_ms')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
        Schema::dropIfExists('ai_chat_sessions');
    }
};
