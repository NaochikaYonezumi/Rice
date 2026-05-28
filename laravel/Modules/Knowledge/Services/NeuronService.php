<?php

namespace Modules\Knowledge\Services;

use Illuminate\Support\Facades\Http;

class NeuronService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.rag_api.url', 'http://rag-api:8000'), '/');
    }

    /**
     * バックグラウンドでクロールを開始する (即時 202 が返るだけ)。
     */
    public function startCrawl(string $url, int $maxDepth = 2): array
    {
        return Http::post("{$this->baseUrl}/scrape", [
            'url' => $url,
            'max_depth' => $maxDepth,
        ])->json() ?? [];
    }

    /**
     * 同期クロール。完了後にチャンク数を含むレスポンスを返す。
     * タイムアウトを長めに取る (デフォルト 15 分)。
     */
    public function scrapeSync(
        string $url,
        int $maxDepth = 2,
        int $maxPages = 30,
        int $timeoutSec = 900,
    ): array {
        $response = Http::timeout($timeoutSec)
            ->post("{$this->baseUrl}/scrape/sync", [
                'url' => $url,
                'max_depth' => $maxDepth,
                'max_pages' => $maxPages,
            ]);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * 既存ドキュメントを削除した上で再クロールする。
     */
    public function refreshSource(
        string $url,
        int $maxDepth = 2,
        int $maxPages = 30,
        int $timeoutSec = 900,
    ): array {
        $response = Http::timeout($timeoutSec)
            ->post("{$this->baseUrl}/sources/refresh", [
                'url' => $url,
                'max_depth' => $maxDepth,
                'max_pages' => $maxPages,
            ]);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * 指定 URL 配下のドキュメントを vector DB から削除する。
     */
    public function deleteSource(string $url, int $timeoutSec = 60): array
    {
        $response = Http::timeout($timeoutSec)
            ->delete("{$this->baseUrl}/sources", [
                'url' => $url,
            ]);

        $response->throw();

        return $response->json() ?? [];
    }

    /**
     * Query the knowledge base.
     *
     * @param array|null $collections フィルタするコレクション名の配列 (OR マッチ)
     */
    public function query(string $query, ?array $collections = null): array
    {
        $payload = ['query' => $query];
        if (!empty($collections)) {
            $payload['collections'] = array_values($collections);
        }
        return Http::post("{$this->baseUrl}/query", $payload)->json() ?? [];
    }

    /**
     * アップロードファイルをベクター DB にインデックス。
     * - $sourceId は `scraped_urls.url` と同じく一意な識別子になる
     * - $collections に配列を渡すとドキュメント側に複数コレクションが記録される
     */
    public function uploadDocument(
        string $sourceId,
        string $absoluteLocalPath,
        string $filename,
        string $mimeType,
        ?string $title = null,
        ?string $collection = null,         // 互換 (主コレクション)
        int $timeoutSec = 900,
        ?array $collections = null,         // 新: 配列 (全コレクション)
    ): array {
        // multipart/form-data に collection (互換) と collections (JSON 文字列) を両方乗せる。
        // FastAPI 側は collections を優先的に解釈する。
        $form = array_filter([
            'source_id'   => $sourceId,
            'title'       => $title,
            'collection'  => $collection,
            'collections' => !empty($collections) ? json_encode(array_values($collections), JSON_UNESCAPED_UNICODE) : null,
        ], fn($v) => $v !== null && $v !== '');

        $response = Http::timeout($timeoutSec)
            ->attach('file', fopen($absoluteLocalPath, 'r'), $filename, ['Content-Type' => $mimeType])
            ->post("{$this->baseUrl}/documents/upload", $form);

        $response->throw();
        return $response->json() ?? [];
    }

    /**
     * 指定 source_id の vector DB レコードのコレクションを上書きする (再インデックスなし)。
     */
    public function updateSourceCollections(string $sourceId, array $collections, int $timeoutSec = 60): array
    {
        $payload = [
            'source_id'   => $sourceId,
            'collections' => array_values($collections),
        ];
        $response = Http::timeout($timeoutSec)
            ->post("{$this->baseUrl}/sources/collections", $payload);
        $response->throw();
        return $response->json() ?? [];
    }

    /**
     * 指定 source_id に紐づく vector DB 内のテキストを結合して取得。
     */
    public function getSourceText(string $sourceId, int $timeoutSec = 30): string
    {
        $response = Http::timeout($timeoutSec)
            ->get("{$this->baseUrl}/sources/text", ['source_id' => $sourceId]);
        $response->throw();
        return (string) ($response->json()['text'] ?? '');
    }

    /**
     * メール本文等の任意テキストをベクター DB にインデックスする。
     */
    public function indexText(
        string $sourceId,
        string $content,
        ?string $title = null,
        ?string $collection = null,         // 互換
        int $timeoutSec = 600,
        ?array $collections = null,         // 新: 配列
    ): array {
        $payload = array_filter([
            'source_id'   => $sourceId,
            'content'     => $content,
            'title'       => $title,
            'collection'  => $collection,
            'collections' => !empty($collections) ? array_values($collections) : null,
        ], fn($v) => $v !== null && $v !== '');

        $response = Http::timeout($timeoutSec)
            ->post("{$this->baseUrl}/documents/text", $payload);

        $response->throw();
        return $response->json() ?? [];
    }
}
