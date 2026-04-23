<?php

namespace App\Http\Controllers;

use App\Models\ScrapedUrl;
use App\Services\RagApiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ScrapeController extends Controller
{
    public function __construct(private RagApiService $ragApi) {}

    public function index()
    {
        $urls = ScrapedUrl::orderByDesc('created_at')->get();
        $urlRows = $urls->map(fn($u) => [
            'id' => $u->id,
            'url' => $u->url,
            'collection' => $u->collection,
            'chunks_indexed' => $u->chunks_indexed,
            'status' => $u->status,
            'created_at' => $u->created_at->format('Y/m/d H:i'),
        ])->values();
        return view('chat.scrape', compact('urls', 'urlRows'));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|url|max:2048',
            'collection' => 'nullable|string|alpha_dash|max:64',
        ]);

        $url = $request->input('url');
        $collection = $request->input('collection', 'default');

        try {
            $result = $this->ragApi->scrape($url, $collection);

            $scraped = ScrapedUrl::create([
                'url' => $url,
                'collection' => $collection,
                'chunks_indexed' => $result['chunks_added'] ?? 0,
                'status' => 'ok',
            ]);

            return response()->json(array_merge($result, ['id' => $scraped->id]));
        } catch (\Exception $e) {
            ScrapedUrl::create([
                'url' => $url,
                'collection' => $collection,
                'chunks_indexed' => 0,
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroyUrl(ScrapedUrl $scrapedUrl): JsonResponse
    {
        try {
            $this->ragApi->deleteSource($scrapedUrl->url, $scrapedUrl->collection);
        } catch (\Exception) {}

        $scrapedUrl->delete();
        return response()->json(['status' => 'deleted']);
    }

    public function destroy(string $collection): JsonResponse
    {
        try {
            $result = $this->ragApi->deleteCollection($collection);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
