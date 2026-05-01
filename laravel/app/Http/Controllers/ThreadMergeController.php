<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\EmailThread;
use App\Models\ThreadMerge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThreadMergeController extends Controller
{
    public function merge(Request $request, EmailThread $thread): JsonResponse
    {
        $validated = $request->validate([
            'merge_thread_id' => 'required|integer|exists:email_threads,id',
        ]);

        $sourceThread = EmailThread::findOrFail($validated['merge_thread_id']);

        if ($sourceThread->id === $thread->id) {
            return response()->json(['status' => 'error', 'message' => '同じスレッドにはマージできません'], 422);
        }

        $emailIds = $sourceThread->emails()->pluck('id')->toArray();

        if (empty($emailIds)) {
            return response()->json(['status' => 'error', 'message' => 'マージ元にメールがありません'], 422);
        }

        // Virtual merge: only link them, do not modify or delete source thread's emails.
        ThreadMerge::updateOrCreate([
            'target_thread_id'          => $thread->id,
            'source_thread_id_original' => $sourceThread->id,
        ], [
            'source_subject'            => $sourceThread->subject,
            'source_tags'               => $sourceThread->tags,
            'merged_email_ids'          => $emailIds,
        ]);
        
        // ベースメールの件名に統一 (要件)
        $sourceThread->update(['subject' => $thread->subject]);

        return response()->json(['status' => 'ok']);
    }

    public function unmerge(ThreadMerge $threadMerge): JsonResponse
    {
        $sourceThread = EmailThread::find($threadMerge->source_thread_id_original);
        if ($sourceThread) {
            // Restore original subject if needed, or leave it. The requirement says:
            // "マージ解除時、マージ中に行った個別操作の状態は各メールに保持したまま解除"
            $sourceThread->update(['subject' => $threadMerge->source_subject]);
        }

        $threadMerge->delete();

        return response()->json(['status' => 'ok']);
    }
}
