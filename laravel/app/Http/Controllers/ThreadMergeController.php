<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\EmailThread;
use App\Models\ThreadMerge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ThreadMergeController extends Controller
{
    /**
     * 2 つのスレッドを「仮想マージ」する。
     *
     * - 物理的にメール行を移動せず、ThreadMerge ピボットで結びつけるだけ。
     * - 結果として、メール一覧で source は非表示、target は両方のメールをまとめて表示。
     * - source の件名は target の件名で上書きする (再受信時の同一スレッド復元のため)。
     * - エラー時は理由をクライアントに JSON で返す (UI 側でトーストに出る)。
     */
    public function merge(Request $request, EmailThread $thread): JsonResponse
    {
        $validated = $request->validate([
            'merge_thread_id' => 'required|integer|exists:email_threads,id',
        ]);

        // target (= $thread) への owner_user_id check.
        $authId = auth()->id();
        if ($thread->owner_user_id !== null && $thread->owner_user_id !== $authId) {
            return response()->json(['status' => 'error', 'message' => 'このスレッドへのアクセス権がありません'], 403);
        }

        $sourceThread = EmailThread::find($validated['merge_thread_id']);
        if (!$sourceThread) {
            return response()->json(['status' => 'error', 'message' => 'マージ元スレッドが見つかりません'], 404);
        }

        // source への owner_user_id check (他人の個人スレッドを覗き見/マージするのを防止).
        if ($sourceThread->owner_user_id !== null && $sourceThread->owner_user_id !== $authId) {
            return response()->json(['status' => 'error', 'message' => 'マージ元スレッドへのアクセス権がありません'], 403);
        }

        // owner スコープ越えのマージは禁止 (個人 ↔ 共有 など).
        //   個人と共有を 1 本にすると、 個人メールが共有メール一覧/ルームメンバーに漏れる.
        if (($thread->owner_user_id ?? null) !== ($sourceThread->owner_user_id ?? null)) {
            return response()->json([
                'status'  => 'error',
                'message' => '個人メールと共有メールを跨ぐマージはできません',
            ], 422);
        }

        if ($sourceThread->id === $thread->id) {
            return response()->json(['status' => 'error', 'message' => '同じスレッドにはマージできません'], 422);
        }

        // 既にマージ済み (target, source 同じ組み合わせ) なら冪等に成功扱い
        $alreadyMerged = ThreadMerge::where('target_thread_id', $thread->id)
            ->where('source_thread_id_original', $sourceThread->id)
            ->exists();
        if ($alreadyMerged) {
            return response()->json(['status' => 'ok', 'message' => '既にマージ済みです', 'already_merged' => true]);
        }

        // source 自体が他の target にマージ済みなら新しい target には移せない (二重マージ防止)
        $existingMergeAsSource = ThreadMerge::where('source_thread_id_original', $sourceThread->id)->first();
        if ($existingMergeAsSource && $existingMergeAsSource->target_thread_id !== $thread->id) {
            return response()->json([
                'status'  => 'error',
                'message' => '別のスレッドへ既にマージされています (先にそちらのマージを解除してください)',
            ], 409);
        }

        // target 自体が別の target のソースとしてマージされている (= 既に source 扱い) ならエラー
        $targetIsAlreadySource = ThreadMerge::where('source_thread_id_original', $thread->id)->exists();
        if ($targetIsAlreadySource) {
            return response()->json([
                'status'  => 'error',
                'message' => 'ベース側のスレッドが既に別のスレッドへマージ済みです',
            ], 409);
        }

        $emailIds = $sourceThread->emails()->pluck('id')->toArray();
        if (empty($emailIds)) {
            return response()->json(['status' => 'error', 'message' => 'マージ元にメールがありません'], 422);
        }

        try {
            DB::transaction(function () use ($thread, $sourceThread, $emailIds) {
                // Virtual merge: pivot 行を作るだけ。 source の email_id は据え置き。
                ThreadMerge::updateOrCreate([
                    'target_thread_id'          => $thread->id,
                    'source_thread_id_original' => $sourceThread->id,
                ], [
                    'source_subject'            => $sourceThread->subject,
                    'source_tags'               => $sourceThread->tags,
                    'merged_email_ids'          => $emailIds,
                ]);

                // 旧コードは「source の subject を target の subject にリネーム」していたが、
                // これが原因で findOrCreateThread の subject 一致検索が source にも当たるようになり、
                // 「マージ後 source に無関係のメールが落ちる」深刻なバグになっていた.
                //
                //   旧:   $sourceThread->update(['subject' => $thread->subject]);
                //
                // 削除済み. ThreadMerge レコードと email_threads.id があれば
                // フロント側で source 配下のメールを target にバンドル表示できるため、
                // subject を書き換える必要は無い.

                // ★ source が紐付いていたルームを target にも紐付ける.
                // 旧実装ではこれが無く、merge 後に source は email 一覧から消えるのに
                // chat_room_thread は source を指したまま残ってしまい、
                // 「ルームフィルタで絞り込むと該当スレッドが見えない」バグになっていた。
                try {
                    $sourceRoomIds = $sourceThread->chatRooms()->pluck('chat_rooms.id')->all();
                    if (!empty($sourceRoomIds)) {
                        $thread->chatRooms()->syncWithoutDetaching($sourceRoomIds);
                        \App\Models\ChatRoom::whereIn('id', $sourceRoomIds)->update(['updated_at' => now()]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('ThreadMerge: bundle transfer failed', [
                        'target' => $thread->id, 'source' => $sourceThread->id,
                        'error'  => $e->getMessage(),
                    ]);
                }

                // ★ source の方が「アクティブ」なステータスを持っていれば target に昇格させる.
                //   例: target=completed, source=inbox の場合 → target を inbox に戻す.
                //   理由: ユーザは「新しい未対応スレッドが来た」のでマージしたのに、
                //         古い完了スレッドに吸収されて inbox から消えるのは想定外。
                $active = ['inbox', 'hold', 'pending'];
                $done   = ['completed', 'no_action', 'spam'];
                if (in_array($sourceThread->status, $active, true)
                    && in_array($thread->status, $done, true)) {
                    $thread->update(['status' => $sourceThread->status]);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('ThreadMerge: merge failed', [
                'target' => $thread->id,
                'source' => $sourceThread->id,
                'error'  => $e->getMessage(),
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'DB エラーでマージできませんでした: ' . $e->getMessage(),
            ], 500);
        }

        // クライアント側 Undo (Ctrl+Z) で「このマージを解除」できるよう merge_id を返す.
        $created = ThreadMerge::where('target_thread_id', $thread->id)
            ->where('source_thread_id_original', $sourceThread->id)
            ->first();
        return response()->json([
            'status'   => 'ok',
            'merge_id' => $created?->id,
        ]);
    }

    public function unmerge(ThreadMerge $threadMerge): JsonResponse
    {
        $sourceThread = EmailThread::find($threadMerge->source_thread_id_original);
        try {
            DB::transaction(function () use ($sourceThread, $threadMerge) {
                // 新仕様: merge 時に source の subject を書き換えないので、unmerge 時に「戻す」必要も無い.
                // ただし、過去 (旧仕様時) のバグで上書きされたままの source は補正しておく.
                if ($sourceThread && !empty($threadMerge->source_subject)
                    && (string) $sourceThread->subject !== (string) $threadMerge->source_subject) {
                    $sourceThread->update(['subject' => $threadMerge->source_subject]);
                }
                $threadMerge->delete();
            });
        } catch (\Throwable $e) {
            Log::warning('ThreadMerge: unmerge failed', [
                'merge_id' => $threadMerge->id,
                'error'    => $e->getMessage(),
            ]);
            return response()->json([
                'status'  => 'error',
                'message' => 'DB エラーでマージ解除できませんでした: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json(['status' => 'ok']);
    }
}
