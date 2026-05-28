<?php

namespace App\Jobs;

use App\Models\AiTask;
use App\Services\RagApiException;
use App\Services\RagApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessAiTask implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(public int $taskId) {}

    public function handle(RagApiService $ragApi): void
    {
        $task = AiTask::find($this->taskId);
        if (!$task) return;

        $task->status     = AiTask::STATUS_PROCESSING;
        $task->started_at = now();
        $task->save();

        try {
            $result = $ragApi->query(
                (string) $task->prompt,
                3,
                $task->provider,
                $task->model,
            );
            $task->result_answer = $result['answer'] ?? '';
            $task->result_meta   = ['sources' => $result['sources'] ?? []];
            $task->status        = AiTask::STATUS_DONE;
            $task->finished_at   = now();
            $task->error_code    = null;
            $task->error_message = null;
            $task->save();
        } catch (RagApiException $e) {
            $task->status        = AiTask::STATUS_ERROR;
            $task->error_code    = $e->errorCode;
            $task->error_message = $e->getMessage() . ($e->raw ? "\n\n[詳細]\n" . mb_substr($e->raw, 0, 600) : '');
            $task->finished_at   = now();
            $task->save();
        } catch (\Throwable $e) {
            Log::error('ProcessAiTask failed', [
                'task_id' => $task->id,
                'task_type' => $task->task_type,
                'error' => $e->getMessage(),
            ]);
            $task->status        = AiTask::STATUS_ERROR;
            $task->error_code    = 'internal_error';
            $task->error_message = $e->getMessage();
            $task->finished_at   = now();
            $task->save();
        }
    }
}
