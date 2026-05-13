<?php

namespace Modules\Knowledge\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Knowledge\Services\NeuronService;

class KnowledgeController extends Controller
{
    public function index()
    {
        return view('knowledge::index');
    }

    public function crawl(Request $request, NeuronService $neuron)
    {
        $request->validate(['url' => 'required|url']);

        $result = $neuron->startCrawl($request->url);

        return back()->with('success', 'クローリングを開始しました。');
    }

    /**
     * Phase 6-1: 利用可能な RAG コレクション一覧を返す。
     *
     * 解決順:
     *  1) Python 側 GET /collections があればそのレスポンスを使う
     *  2) なければ scraped_urls.collection / customers.rag_collection の
     *     distinct をマージしたフォールバック
     *
     * レスポンス: { collections: [ { name: string, source: 'rag-api'|'db' }, ... ] }
     */
    public function collections(): JsonResponse
    {
        $baseUrl = rtrim(config('services.rag_api.url', env('RAG_API_URL', 'http://rag-api:8000')), '/');

        // (1) Python 側 /collections の試行
        try {
            $res = Http::timeout(5)->acceptJson()->get("{$baseUrl}/collections");
            if ($res->ok()) {
                $body = $res->json();
                $names = $this->extractCollectionNames($body);
                if (!empty($names)) {
                    return response()->json([
                        'collections' => array_map(fn($n) => ['name' => $n, 'source' => 'rag-api'], $names),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // フォールバックへ
        }

        // (2) DB フォールバック
        $names = collect();

        try {
            if (Schema::hasTable('scraped_urls') && Schema::hasColumn('scraped_urls', 'collection')) {
                $names = $names->merge(
                    DB::table('scraped_urls')
                        ->whereNotNull('collection')
                        ->where('collection', '!=', '')
                        ->distinct()
                        ->pluck('collection')
                );
            }
        } catch (\Throwable $e) { /* noop */ }

        try {
            if (Schema::hasColumn('customers', 'rag_collection')) {
                $names = $names->merge(
                    Customer::whereNotNull('rag_collection')
                        ->where('rag_collection', '!=', '')
                        ->distinct()
                        ->pluck('rag_collection')
                );
            }
        } catch (\Throwable $e) { /* noop */ }

        $names = $names->push('default')->unique()->values();

        return response()->json([
            'collections' => $names->map(fn($n) => ['name' => (string) $n, 'source' => 'db'])->all(),
        ]);
    }

    /**
     * Python 側レスポンスから collection 名を抜き出す。
     */
    private function extractCollectionNames($body): array
    {
        if (is_array($body)) {
            if (isset($body['collections'])) {
                return collect($body['collections'])
                    ->map(fn($x) => is_array($x) ? ($x['name'] ?? null) : (string) $x)
                    ->filter()
                    ->values()
                    ->all();
            }
            return collect($body)
                ->filter(fn($x) => is_string($x) && $x !== '')
                ->values()
                ->all();
        }
        return [];
    }
}
