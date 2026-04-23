<?php

namespace App\Jobs;

use App\Models\ChatQuery;
use App\Services\RagApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessChatQuery implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;
    public int $tries = 1;

    public function __construct(public string $queryId) {}

    public function handle(RagApiService $ragApi): void
    {
        $record = ChatQuery::findOrFail($this->queryId);

        try {
            $result = $ragApi->query($record->question, provider: $record->provider, model: $record->model);
            $record->update([
                'answer' => $result['answer'] ?? '',
                'sources' => $result['sources'] ?? [],
                'status' => 'done',
            ]);
        } catch (\Throwable $e) {
            $record->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
