<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\RagApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function __construct(private RagApiService $ragApi) {}

    public function index()
    {
        $documents = Document::orderByDesc('created_at')->get();
        return view('documents.index', compact('documents'));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:20480',
            'collection' => 'nullable|string|alpha_dash|max:64',
        ]);

        $file = $request->file('file');
        $collection = $request->input('collection', 'documents');
        $path = $file->store('documents', 'local');

        $document = Document::create([
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'collection' => $collection,
        ]);

        // Python RAG API にファイルを送ってインデックス化
        try {
            $ragApiUrl = config('services.rag_api.url');
            $response = Http::timeout(120)
                ->attach('file', file_get_contents(Storage::path($path)), $file->getClientOriginalName())
                ->post("{$ragApiUrl}/ingest-file", [
                    'collection' => $collection,
                    'document_id' => (string) $document->id,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $document->update([
                    'chunks_indexed' => $data['chunks_added'] ?? 0,
                    'is_indexed' => true,
                    'extracted_text' => $data['extracted_text'] ?? null,
                ]);
            }
        } catch (\Exception $e) {
            // インデックス失敗でもアップロード自体は成功扱い
        }

        return response()->json([
            'id' => $document->id,
            'original_name' => $document->original_name,
            'type_icon' => $document->type_icon,
            'is_indexed' => $document->is_indexed,
            'chunks_indexed' => $document->chunks_indexed,
        ]);
    }

    public function destroy(Document $document): JsonResponse
    {
        Storage::disk('local')->delete($document->stored_path);

        try {
            $this->ragApi->deleteCollection($document->collection);
        } catch (\Exception $e) {
            Log::warning('DocumentController.destroy: RAG deleteCollection failed', ['collection' => $document->collection, 'error' => $e->getMessage()]);
        }

        $document->delete();
        return response()->json(['status' => 'deleted']);
    }
}
