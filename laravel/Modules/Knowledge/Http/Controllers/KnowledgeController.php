<?php

namespace Modules\Knowledge\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessKnowledgeCrawl;
use App\Models\Customer;
use App\Models\Email;
use App\Models\ScrapedUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Knowledge\Services\NeuronService;

class KnowledgeController extends Controller
{
    /**
     * ナレッジソース一覧画面。
     */
    public function index()
    {
        $sources = ScrapedUrl::orderByDesc('id')->get();

        return view('knowledge::index', compact('sources'));
    }

    /**
     * ベースURLを登録してクローリングを非同期で開始する。
     * - URL を `pending` で即時保存して一覧に表示
     * - 実際のスクレイピングはキューに投げてバックグラウンドで実行
     */
    public function crawl(Request $request)
    {
        $data = $request->validate([
            'url' => 'required|url|max:2048',
            // 単一文字列でもカンマ区切りでもクライアント側で配列にしても受け取れるよう緩める
            'collection'    => 'nullable',
            'collections'   => 'nullable',
            'max_pages' => 'nullable|integer|min:1|max:300',
            'max_depth' => 'nullable|integer|min:0|max:5',
        ]);

        $url = $this->normalizeUrl($data['url']);
        $collections = ScrapedUrl::normalizeCollections($data['collections'] ?? $data['collection'] ?? null);
        $maxPages = (int) ($data['max_pages'] ?? 30);
        $maxDepth = (int) ($data['max_depth'] ?? 2);

        // 同一 URL は upsert 扱い
        $source = ScrapedUrl::firstOrNew(['url' => $url]);
        $source->collection  = $collections[0];     // 互換: 主コレクション = 配列の先頭
        $source->collections = $collections;        // 全コレクション (JSON)
        $source->status = 'pending';
        $source->chunks_indexed = $source->chunks_indexed ?? 0;
        $source->error_message = null;
        $source->save();

        ProcessKnowledgeCrawl::dispatch($source->id, $maxDepth, $maxPages, refresh: false);

        return back()->with('success', "URL を登録しました。バックグラウンドでクロール処理中です。一覧で進捗を確認できます: {$url}");
    }

    /**
     * 1件のソースを削除する。
     * - Python (vector DB) からも該当ドキュメントを削除
     * - Laravel 側の scraped_urls からも削除
     */
    public function destroy(ScrapedUrl $source, NeuronService $neuron)
    {
        try {
            $neuron->deleteSource($source->url);
        } catch (\Throwable $e) {
            // vector DB 側の削除に失敗しても、scraped_urls は消す方針 (整合性は次回 refresh で回復)
            Log::warning('knowledge.destroy: rag-api delete failed', [
                'url' => $source->url,
                'error' => $e->getMessage(),
            ]);
        }

        $url = $source->url;
        $source->delete();

        return back()->with('success', "削除しました: {$url}");
    }

    /**
     * 1件のソースを再クロール (vector DB を一度クリアしてから再取得)。非同期。
     */
    public function refresh(Request $request, ScrapedUrl $source)
    {
        $data = $request->validate([
            'max_pages' => 'nullable|integer|min:1|max:300',
            'max_depth' => 'nullable|integer|min:0|max:5',
        ]);
        $maxPages = (int) ($data['max_pages'] ?? 30);
        $maxDepth = (int) ($data['max_depth'] ?? 2);

        $source->status = 'pending';
        $source->error_message = null;
        $source->save();

        ProcessKnowledgeCrawl::dispatch($source->id, $maxDepth, $maxPages, refresh: true);

        return back()->with('success', "再クロールをバックグラウンドで開始しました: {$source->url}");
    }

    /**
     * 1 ソースの詳細 (本文込み) を JSON で返す (側パネル用)。
     * 本文 (raw_text) が無い URL ソース等は rag-api から取得を試みる。
     */
    public function show(ScrapedUrl $source, NeuronService $neuron): JsonResponse
    {
        $content = $source->raw_text;
        $fetchedFromRag = false;
        if (empty($content)) {
            try {
                $content = $neuron->getSourceText($source->url);
                $fetchedFromRag = true;
            } catch (\Throwable $e) {
                $content = null;
            }
        }
        return response()->json([
            'id'             => $source->id,
            'url'            => $source->url,
            'source_type'    => $source->source_type ?: 'url',
            'title'          => $source->title,
            'collection'     => $source->primaryCollection(), // 互換用 (単一値)
            'collections'    => $source->allCollections(),    // 配列形 (新)
            'status'         => $source->status,
            'chunks_indexed' => (int) $source->chunks_indexed,
            'error_message'  => $source->error_message,
            'updated_at'     => $source->updated_at?->format('Y-m-d H:i'),
            'meta'           => $source->meta,
            'content'        => $content,
            'content_editable' => in_array($source->source_type, ['file', 'email'], true),
            'fetched_from_rag' => $fetchedFromRag,
        ]);
    }

    /**
     * ソースのタイトル/本文を更新し、再インデックスする。
     * URL ソースは編集不可 (再クロールで対応)。
     */
    public function updateContent(Request $request, ScrapedUrl $source, NeuronService $neuron): JsonResponse
    {
        if (!in_array($source->source_type, ['file', 'email'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'URL ソースは編集できません。再クロールを使用してください。',
            ], 422);
        }
        $data = $request->validate([
            'title'   => 'nullable|string|max:255',
            'content' => 'required|string|max:500000',
        ]);

        try {
            // 再インデックス: 全コレクションを RAG API に渡す
            $result = $neuron->indexText(
                $source->url,
                $data['content'],
                $data['title'] ?? $source->title,
                $source->primaryCollection(),
                600,
                $source->allCollections(),
            );
            $source->title = $data['title'] ?? $source->title;
            $source->raw_text = $data['content'];
            $source->chunks_indexed = (int) ($result['chunks_indexed'] ?? $source->chunks_indexed);
            $source->status = 'ok';
            $source->error_message = null;
            $source->save();
            return response()->json([
                'status' => 'ok',
                'message' => '内容を保存し、再インデックスしました。',
                'chunks_indexed' => $source->chunks_indexed,
            ]);
        } catch (\Throwable $e) {
            Log::error('knowledge.updateContent failed', ['source_id' => $source->id, 'error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => '保存に失敗しました: ' . $this->humanizeError($e),
            ], 500);
        }
    }

    /**
     * 既存ソースのコレクション (タグ) を変更する。複数指定可。
     * - 入力は配列 OR カンマ/全角カンマ/改行区切り
     * - 各トークン: 1-64 文字、空白 / \ # ? & を含まないこと
     * - 不正トークンは静かに除去 (個別エラーにはしない)、結果空なら ['default']
     */
    public function updateCollection(Request $request, ScrapedUrl $source, NeuronService $neuron): JsonResponse
    {
        $request->validate([
            'collection'  => 'nullable',
            'collections' => 'nullable',
        ]);
        $cols = ScrapedUrl::normalizeCollections(
            $request->input('collections') ?? $request->input('collection')
        );
        $source->collections = $cols;
        $source->collection  = $cols[0]; // 互換: 主コレクション
        $source->save();

        // Python ベクター DB 側の metadata も同期 (失敗しても 200 を返す)
        try {
            $neuron->updateSourceCollections($source->url, $cols);
        } catch (\Throwable $e) {
            Log::warning('updateCollection: rag-api sync failed', [
                'source_id' => $source->url,
                'error'     => $e->getMessage(),
            ]);
        }

        return response()->json([
            'status'      => 'ok',
            'collection'  => $source->collection,  // 互換 (単一)
            'collections' => $source->collections, // 配列
        ]);
    }

    /**
     * 複数ソースのコレクションを一括更新する。複数コレクション対応。
     * 動作モード:
     *  - mode = 'replace' (デフォルト) … 既存タグを全置換
     *  - mode = 'append'              … 既存タグに追加 (重複は除去)
     *  - mode = 'remove'              … 指定タグを既存から削除
     */
    public function bulkUpdateCollection(Request $request, NeuronService $neuron): JsonResponse
    {
        $request->validate([
            'ids'         => 'required|array|min:1',
            'ids.*'       => 'integer|exists:scraped_urls,id',
            'collection'  => 'nullable',
            'collections' => 'nullable',
            'mode'        => 'nullable|in:replace,append,remove',
        ]);
        $mode = $request->input('mode', 'replace');
        $incoming = ScrapedUrl::normalizeCollections(
            $request->input('collections') ?? $request->input('collection')
        );

        $updated = 0;
        foreach (ScrapedUrl::whereIn('id', $request->input('ids'))->get() as $src) {
            $current = $src->allCollections();
            if ($mode === 'append') {
                $merged = array_values(array_unique(array_merge($current, $incoming)));
            } elseif ($mode === 'remove') {
                $merged = array_values(array_diff($current, $incoming));
                if (empty($merged)) $merged = ['default'];
            } else { // replace
                $merged = $incoming;
            }
            $src->collections = $merged;
            $src->collection  = $merged[0];
            $src->save();
            $updated++;

            // Python ベクター DB 側の metadata も同期 (個別失敗は warning に留める)
            try {
                $neuron->updateSourceCollections($src->url, $merged);
            } catch (\Throwable $e) {
                Log::warning('bulkUpdateCollection: rag-api sync failed', [
                    'source_id' => $src->url,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'status'      => 'ok',
            'updated'     => $updated,
            'mode'        => $mode,
            'collections' => $incoming,
        ]);
    }

    /**
     * ソース一覧のステータスを JSON で返す (フロントのポーリング用)。
     */
    public function statuses(): JsonResponse
    {
        $rows = ScrapedUrl::select('id', 'url', 'source_type', 'title', 'status', 'chunks_indexed', 'error_message', 'updated_at', 'collection', 'collections')
            ->orderByDesc('id')
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'url' => $s->url,
                'source_type' => $s->source_type ?: 'url',
                'title' => $s->title,
                'status' => $s->status,
                'chunks_indexed' => (int) ($s->chunks_indexed ?? 0),
                'error_message' => $s->error_message,
                'updated_at' => $s->updated_at?->format('Y-m-d H:i'),
                'collection'  => $s->primaryCollection(),
                'collections' => $s->allCollections(),
            ]);
        return response()->json(['sources' => $rows]);
    }

    /**
     * ファイル (PDF / DOCX / TXT 等) をアップロードしてナレッジに登録する。
     * 同期実行。完了後リダイレクト。
     */
    public function uploadFile(Request $request, NeuronService $neuron)
    {
        $request->validate([
            'file' => 'required|file|max:20480',  // 20MB
            'collection'  => 'nullable',
            'collections' => 'nullable',
            'title' => 'nullable|string|max:255',
        ]);

        $collections = ScrapedUrl::normalizeCollections(
            $request->input('collections') ?? $request->input('collection')
        );
        $uploaded = $request->file('file');
        $originalName = $uploaded->getClientOriginalName();
        $title = $request->input('title') ?: $originalName;
        $mime  = $uploaded->getMimeType() ?: 'application/octet-stream';

        // ファイルを永続保存し、source_id (= url カラム) として file://uploads/{name} を採用
        $stored = $uploaded->store('knowledge_uploads', 'private');
        $sourceId = 'file://' . $stored;

        $source = ScrapedUrl::firstOrNew(['url' => $sourceId]);
        $source->source_type   = ScrapedUrl::SOURCE_FILE;
        $source->title         = $title;
        $source->collection    = $collections[0];  // 互換: 主コレクション
        $source->collections   = $collections;
        $source->status        = 'pending';
        $source->chunks_indexed = 0;
        $source->error_message = null;
        $source->meta = [
            'original_filename' => $originalName,
            'mime_type'         => $mime,
            'size'              => $uploaded->getSize(),
            'storage_path'      => $stored,
            'disk'              => 'private',
            'uploaded_by'       => auth()->id(),
        ];
        $source->save();

        try {
            $absPath = Storage::disk('private')->path($stored);
            // 全コレクションを RAG API に渡す。Python 側で metadata に保存される。
            // 後方互換のため主コレクションも単一値として送信。
            $result = $neuron->uploadDocument(
                $sourceId, $absPath, $originalName, $mime, $title,
                $collections[0],
                900,
                $collections,
            );
            $source->status = 'ok';
            $source->chunks_indexed = (int) ($result['chunks_indexed'] ?? 0);
            $source->error_message = null;
            // rag-api から抽出したテキストを Laravel 側にも保存 (側パネル編集用)
            if (!empty($result['extracted_text'])) {
                $source->raw_text = $result['extracted_text'];
            }
            $source->save();
            return back()->with('success', "ファイルをナレッジに登録しました: {$originalName}");
        } catch (\Throwable $e) {
            Log::error('knowledge.uploadFile failed', ['file' => $originalName, 'error' => $e->getMessage()]);
            $source->status = 'error';
            $source->error_message = mb_substr($this->humanizeError($e), 0, 1000);
            $source->save();
            return back()->with('error', 'ファイル登録に失敗しました: ' . $this->humanizeError($e));
        }
    }

    /**
     * 指定メールの内容を編集可能なテキストとして返す (プレビュー画面用)。
     */
    public function previewEmail(Email $email): JsonResponse
    {
        $thread = $email->thread;
        $context = '';
        if ($thread) {
            $context .= "件名: " . ($email->subject ?: '(件名なし)') . "\n";
            if ($thread->ticket_number) $context .= "チケット: [#{$thread->ticket_number}]\n";
            $context .= "差出人: " . ($email->from_label ?: $email->from_address ?: '不明') . "\n";
            $context .= "宛先: " . ($email->to_address ?: '—') . "\n";
            if ($email->cc) $context .= "Cc: " . $email->cc . "\n";
            $context .= "日時: " . ($email->received_at?->format('Y/m/d H:i') ?: '—') . "\n";
            $context .= "\n----- 本文 -----\n";
        }
        $context .= (string) ($email->plain_body ?? $email->body_text ?? '');

        return response()->json([
            'email_id'  => $email->id,
            'subject'   => $email->subject,
            'default_title' => '[Email] ' . ($email->subject ?: ('email-' . $email->id)),
            'editable_content' => $context,
            'suggested_pii_warning' => '※ 個人情報 (氏名・電話番号・メールアドレス・住所等) が含まれる場合は適切にマスクしてから登録してください。',
        ]);
    }

    /**
     * 編集済みメール本文をナレッジに登録する。
     */
    public function storeFromEmail(Request $request, Email $email, NeuronService $neuron)
    {
        $data = $request->validate([
            'title'   => 'required|string|max:255',
            'content' => 'required|string|max:200000',
            'collection'  => 'nullable',
            'collections' => 'nullable',
        ]);

        $collections = ScrapedUrl::normalizeCollections(
            $request->input('collections') ?? $request->input('collection')
        );
        $sourceId = 'email://' . $email->id;

        $source = ScrapedUrl::firstOrNew(['url' => $sourceId]);
        $source->source_type   = ScrapedUrl::SOURCE_EMAIL;
        $source->title         = $data['title'];
        $source->collection    = $collections[0];   // 互換
        $source->collections   = $collections;
        $source->status        = 'pending';
        $source->chunks_indexed = 0;
        $source->error_message = null;
        $source->meta = [
            'email_id'  => $email->id,
            'thread_id' => $email->thread_id,
            'subject'   => $email->subject,
            'registered_by' => auth()->id(),
        ];
        $source->save();

        try {
            // 全コレクションを RAG API に渡す (互換のため主コレクションも単一値で送信)
            $result = $neuron->indexText(
                $sourceId, $data['content'], $data['title'],
                $collections[0],
                600,
                $collections,
            );
            $source->status = 'ok';
            $source->chunks_indexed = (int) ($result['chunks_indexed'] ?? 0);
            $source->error_message = null;
            $source->raw_text = $data['content'];
            $source->save();
            return response()->json([
                'status'  => 'ok',
                'message' => 'メール内容をナレッジに登録しました',
                'source_id' => $source->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('knowledge.storeFromEmail failed', ['email_id' => $email->id, 'error' => $e->getMessage()]);
            $source->status = 'error';
            $source->error_message = mb_substr($this->humanizeError($e), 0, 1000);
            $source->save();
            return response()->json([
                'status'  => 'error',
                'message' => 'メール登録に失敗しました: ' . $this->humanizeError($e),
            ], 500);
        }
    }

    /**
     * Guzzle/Curl のエラーメッセージを人間向けに翻訳する。
     */
    protected function humanizeError(\Throwable $e): string
    {
        $msg = $e->getMessage();
        if (str_contains($msg, 'Operation timed out') || str_contains($msg, 'cURL error 28')) {
            return 'rag-api がタイムアウトしました。ページ数 (max_pages) を減らすか、しばらく待ってから再試行してください。';
        }
        if (str_contains($msg, 'Could not resolve host') || str_contains($msg, 'Connection refused') || str_contains($msg, 'cURL error 7')) {
            return 'rag-api に接続できません。`docker compose up -d rag-api` でコンテナが起動しているか確認してください。';
        }
        // Anthropic クレジット不足を最優先で検出
        if (str_contains($msg, 'credit balance is too low')) {
            return 'Claude API のクレジット残高が不足しています。Anthropic コンソール（Plans & Billing）でクレジットを購入してください。';
        }
        // 構造化 detail: {"error_code": "...", "message": "..."}
        if (preg_match('/"detail":\s*(\{[^}]*"message":\s*"([^"]+)"[^}]*\})/u', $msg, $m)) {
            return $m[2];
        }
        // 旧来の文字列 detail
        if (preg_match('/\{"detail":"([^"]+)"\}/', $msg, $m)) {
            return $m[1];
        }
        return $msg;
    }

    /**
     * 末尾スラッシュ等を揃えるシンプルな正規化。
     */
    protected function normalizeUrl(string $url): string
    {
        $url = trim($url);
        // フラグメント除去
        if (($hash = strpos($url, '#')) !== false) {
            $url = substr($url, 0, $hash);
        }
        return $url;
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
            if (Schema::hasTable('scraped_urls')) {
                // 新 collections JSON 配列をフラット化して収集
                if (Schema::hasColumn('scraped_urls', 'collections')) {
                    DB::table('scraped_urls')
                        ->whereNotNull('collections')
                        ->select('collections')
                        ->orderBy('id')
                        ->chunk(500, function ($rows) use (&$names) {
                            foreach ($rows as $r) {
                                $arr = json_decode((string) $r->collections, true);
                                if (is_array($arr)) {
                                    foreach ($arr as $n) {
                                        if (is_string($n) && trim($n) !== '') $names->push(trim($n));
                                    }
                                }
                            }
                        });
                }
                // 旧 collection (単一) も拾う
                if (Schema::hasColumn('scraped_urls', 'collection')) {
                    $names = $names->merge(
                        DB::table('scraped_urls')
                            ->whereNotNull('collection')
                            ->where('collection', '!=', '')
                            ->distinct()
                            ->pluck('collection')
                    );
                }
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
