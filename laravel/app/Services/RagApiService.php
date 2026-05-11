<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class RagApiService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.rag_api.url', env('RAG_API_URL', 'http://rag-api:8000')), '/');
    }

    public function query(string $question, int $topK = 5, ?string $provider = null, ?string $model = null): array
    {
        $payload = array_filter([
            'query' => $question,
            'top_k' => $topK,
            'provider' => $provider,
            'model' => $model,
        ]);

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

        $response = Http::timeout(120)->post("{$this->baseUrl}/query", $payload);
        $response->throw();
        return $response->json();
    }

    public function getModels(): array
    {
        $response = Http::timeout(10)->get("{$this->baseUrl}/models");
        $response->throw();
        return $response->json();
    }

    public function scrape(string $url, string $collection = 'default'): array
    {
        $response = Http::timeout(60)
            ->post("{$this->baseUrl}/scrape", [
                'url' => $url,
                'collection' => $collection,
            ]);

        $response->throw();

        return $response->json();
    }

    public function health(): array
    {
        $response = Http::timeout(5)->get("{$this->baseUrl}/health");
        $response->throw();
        return $response->json();
    }

    public function deleteSource(string $sourceUrl, string $collection = 'default'): array
    {
        $response = Http::timeout(10)->post("{$this->baseUrl}/delete-source", [
            'source_url' => $sourceUrl,
            'collection' => $collection,
        ]);
        $response->throw();
        return $response->json();
    }

    public function deleteCollection(string $collection): array
    {
        $response = Http::timeout(10)->delete("{$this->baseUrl}/collection/{$collection}");
        $response->throw();
        return $response->json();
    }
}
