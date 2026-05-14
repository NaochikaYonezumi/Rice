<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class RagApiException extends \RuntimeException
{
    public function __construct(
        public string $errorCode,
        string $message,
        public int $statusCode = 500,
        public ?string $raw = null,
    ) {
        parent::__construct($message);
    }
}

class RagApiService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.rag_api.url', env('RAG_API_URL', 'http://rag-api:8000')), '/');
    }

    /** rag-api への HTTP 呼び出しを実行し、接続不可は friendly な日本語例外に変換する。 */
    private function callRagApi(\Closure $fn): \Illuminate\Http\Client\Response
    {
        try {
            return $fn();
        } catch (ConnectionException) {
            throw new RagApiException(
                'rag_api_unreachable',
                "RAG API サービスに接続できません ({$this->baseUrl})。`docker compose up -d rag-api` で起動しているか確認してください。",
                503,
            );
        }
    }

    /** rag-api からのエラーレスポンスを構造化された RagApiException に変換 */
    private function throwTranslated(\Illuminate\Http\Client\Response $response): void
    {
        $body = $response->json();
        $detail = $body['detail'] ?? null;

        if (is_array($detail) && isset($detail['error_code'])) {
            throw new RagApiException(
                errorCode: $detail['error_code'],
                message: $detail['message'] ?? 'AI処理でエラーが発生しました。',
                statusCode: $response->status(),
                raw: $detail['raw'] ?? null,
            );
        }

        $raw = is_string($detail) ? $detail : (json_encode($detail) ?: $response->body());
        $lower = strtolower((string) $raw);
        if (str_contains($lower, 'credit balance is too low')) {
            throw new RagApiException('insufficient_credits',
                'Claude API のクレジット残高が不足しています。Anthropic コンソールでクレジットを購入してください。',
                402);
        }

        $response->throw();
    }

    public function query(string $question, int $topK = 5, ?string $provider = null, ?string $model = null, ?string $collection = null): array
    {
        $payload = array_filter([
            // Python 側 FastAPI は `query` フィールドを期待する
            'query'      => $question,
            'top_k'      => $topK,
            'provider'   => $provider,
            'model'      => $model,
            'collection' => $collection,
        ], fn($v) => $v !== null && $v !== '');

        $settings = null;
        if (in_array($provider, ['claude', 'gemini'], true)) {
            try {
                $settings = \App\Models\AiSetting::getSettings();
            } catch (\Throwable) {}
        }

        if ($provider === 'claude' && $settings) {
            $key = $settings->anthropic_api_key;
            if ($key) $payload['anthropic_api_key'] = $key;
        }

        if ($provider === 'gemini' && $settings) {
            $key = $settings->gemini_api_key;
            if ($key) $payload['gemini_api_key'] = $key;
        }

        $response = $this->callRagApi(fn() => Http::timeout(120)->post("{$this->baseUrl}/query", $payload));
        if (!$response->successful()) {
            $this->throwTranslated($response);
        }
        return $response->json();
    }

    public function getModels(): array
    {
        $response = $this->callRagApi(fn() => Http::timeout(10)->get("{$this->baseUrl}/models"));
        $response->throw();
        return $response->json();
    }

    public function scrape(string $url, string $collection = 'default'): array
    {
        $response = $this->callRagApi(fn() => Http::timeout(60)
            ->post("{$this->baseUrl}/scrape", [
                'url' => $url,
                'collection' => $collection,
            ]));

        if (!$response->successful()) {
            $this->throwTranslated($response);
        }
        return $response->json();
    }

    public function refreshSource(string $sourceUrl, string $collection = 'default', int $maxDepth = 2): array
    {
        $response = $this->callRagApi(fn() => Http::timeout(300)
            ->post("{$this->baseUrl}/sources/refresh", [
                'url' => $sourceUrl,
                'collection' => $collection,
                'max_depth' => $maxDepth,
            ]));

        if (!$response->successful()) {
            $this->throwTranslated($response);
        }
        return $response->json();
    }

    public function health(): array
    {
        $response = $this->callRagApi(fn() => Http::timeout(5)->get("{$this->baseUrl}/health"));
        $response->throw();
        return $response->json();
    }

    public function deleteSource(string $sourceUrl, string $collection = 'default'): array
    {
        $response = $this->callRagApi(fn() => Http::timeout(10)->post("{$this->baseUrl}/delete-source", [
            'source_url' => $sourceUrl,
            'collection' => $collection,
        ]));
        $response->throw();
        return $response->json();
    }

    public function deleteCollection(string $collection): array
    {
        $response = $this->callRagApi(fn() => Http::timeout(10)->delete("{$this->baseUrl}/collection/{$collection}"));
        $response->throw();
        return $response->json();
    }
}
