<?php

namespace Modules\Knowledge\Services;

use Illuminate\Support\Facades\Http;

class NeuronService
{
    protected $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.rag_api.url', 'http://rag-api:8000');
    }

    /**
     * Start a background crawl.
     */
    public function startCrawl(string $url, int $maxDepth = 2)
    {
        return Http::post("{$this->baseUrl}/scrape", [
            'url' => $url,
            'max_depth' => $maxDepth,
        ])->json();
    }

    /**
     * Query the knowledge base.
     */
    public function query(string $query)
    {
        return Http::post("{$this->baseUrl}/query", [
            'query' => $query,
        ])->json();
    }
}
