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

        ThreadMerge::create([
            'target_thread_id'          => $thread->id,
            'source_thread_id_original' => $sourceThread->id,
            'source_subject'            => $sourceThread->subject,
            'source_tags'               => $sourceThread->tags,
            'merged_email_ids'          => $emailIds,
        ]);

        Email::whereIn('id', $emailIds)->update(['thread_id' => $thread->id]);

        $latestAt = Email::where('thread_id', $thread->id)->max('received_at');
        $thread->update(['last_email_at' => $latestAt]);

        $sourceThread->delete();

        return response()->json(['status' => 'ok']);
    }

    public function unmerge(ThreadMerge $threadMerge): JsonResponse
    {
        $restoredThread = EmailThread::create([
            'subject'       => $threadMerge->source_subject,
            'tags'          => $threadMerge->source_tags,
            'last_email_at' => Email::whereIn('id', $threadMerge->merged_email_ids)->max('received_at'),
        ]);

        Email::whereIn('id', $threadMerge->merged_email_ids)
            ->update(['thread_id' => $restoredThread->id]);

        $targetThread = $threadMerge->targetThread;
        if ($targetThread) {
            $latestAt = Email::where('thread_id', $targetThread->id)->max('received_at');
            $targetThread->update(['last_email_at' => $latestAt]);
        }

        $threadMerge->delete();

        return response()->json(['status' => 'ok', 'restored_thread_id' => $restoredThread->id]);
    }
}
