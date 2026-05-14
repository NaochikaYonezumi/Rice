<?php

namespace App\Http\Controllers;

use App\Models\AiTask;
use Illuminate\Http\JsonResponse;

class AiTaskController extends Controller
{
    public function show(AiTask $task): JsonResponse
    {
        // 自分の投げたタスクのみ参照可
        if ($task->user_id !== null && $task->user_id !== auth()->id()) {
            return response()->json(['status' => 'error', 'message' => 'このタスクへのアクセス権がありません。'], 403);
        }

        return response()->json($this->present($task));
    }

    /**
     * 自分の AI タスクのうち、指定 id より新しい done/error のものを返す。
     * 通知に使うための軽量エンドポイント (最大 20 件、直近 6 時間に限定)。
     */
    public function recent(): JsonResponse
    {
        $sinceId = (int) request()->query('since_id', 0);
        $tasks = AiTask::where('user_id', auth()->id())
            ->whereIn('status', [AiTask::STATUS_DONE, AiTask::STATUS_ERROR])
            ->where('id', '>', $sinceId)
            ->where('finished_at', '>=', now()->subHours(6))
            ->orderBy('id', 'asc')
            ->limit(20)
            ->get()
            ->map(fn($t) => $this->present($t));
        return response()->json(['tasks' => $tasks]);
    }

    private function present(AiTask $task): array
    {
        // reply_assist の場合、関連メール ID を返して compose 画面に戻れるようにする
        $relatedEmailId = null;
        if ($task->task_type === AiTask::TYPE_REPLY_ASSIST && $task->thread_id) {
            $emailId = \App\Models\Email::where('thread_id', $task->thread_id)
                ->orderByDesc('received_at')->value('id');
            $relatedEmailId = $emailId;
        }

        return [
            'id'              => $task->id,
            'task_type'       => $task->task_type,
            'status'          => $task->status,
            'provider'        => $task->provider,
            'model'           => $task->model,
            'answer'          => $task->result_answer,
            'sources'         => $task->result_meta['sources'] ?? [],
            'skill_used'      => $task->result_meta['skill_used'] ?? null,
            'error_code'      => $task->error_code,
            'error_message'   => $task->error_message,
            'started_at'      => $task->started_at?->format('Y/m/d H:i:s'),
            'finished_at'     => $task->finished_at?->format('Y/m/d H:i:s'),
            'thread_id'       => $task->thread_id,
            'related_email_id' => $relatedEmailId,
        ];
    }
}
