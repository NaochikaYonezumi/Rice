<?php

namespace App\Jobs;

use App\Models\ScrapedUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Modules\Knowledge\Services\NeuronService;

class ProcessKnowledgeCrawl implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(
        public int $sourceId,
        public int $maxDepth = 2,
        public int $maxPages = 30,
        public bool $refresh = false,
    ) {}

    public function handle(NeuronService $neuron): void
    {
        $source = ScrapedUrl::find($this->sourceId);
        if (!$source) return;

        try {
            $result = $this->refresh
                ? $neuron->refreshSource($source->url, $this->maxDepth, $this->maxPages)
                : $neuron->scrapeSync($source->url, $this->maxDepth, $this->maxPages);

            $source->status = 'ok';
            $source->chunks_indexed = (int) ($result['chunks_indexed'] ?? 0);
            $source->error_message = null;
            $source->save();
        } catch (\Throwable $e) {
            Log::error('ProcessKnowledgeCrawl failed', [
                'source_id' => $this->sourceId,
                'url' => $source->url,
                'refresh' => $this->refresh,
                'error' => $e->getMessage(),
            ]);
            $source->status = 'error';
            $source->error_message = mb_substr($this->humanize($e->getMessage()), 0, 1000);
            $source->save();
        }
    }

    private function humanize(string $msg): string
    {
        if (str_contains($msg, 'Operation timed out') || str_contains($msg, 'cURL error 28')) {
            return 'rag-api がタイムアウトしました。max_pages を減らすか、しばらく待ってから再試行してください。';
        }
        if (str_contains($msg, 'Could not resolve host') || str_contains($msg, 'Connection refused') || str_contains($msg, 'cURL error 7')) {
            return 'rag-api に接続できません。`docker compose up -d rag-api` でコンテナが起動しているか確認してください。';
        }
        if (str_contains($msg, 'credit balance is too low')) {
            return 'Claude API のクレジット残高が不足しています。';
        }
        if (preg_match('/"detail":\s*(\{[^}]*"message":\s*"([^"]+)"[^}]*\})/u', $msg, $m)) {
            return $m[2];
        }
        if (preg_match('/\{"detail":"([^"]+)"\}/', $msg, $m)) {
            return $m[1];
        }
        return $msg;
    }
}
