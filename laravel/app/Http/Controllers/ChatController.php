<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessChatQuery;
use App\Models\ChatQuery;
use App\Models\Customer;
use App\Services\RagApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function __construct(private RagApiService $ragApi) {}

    public function index()
    {
        return view('chat.index');
    }

    /**
     * ルーム (customer) でスコープされたチャット履歴を返す。
     * - customer_id 指定なし: グローバル (customer_id IS NULL) の自分のチャット
     * - 'none' 指定:         グローバル (customer_id IS NULL) の自分のチャット
     * - 数値 ID 指定:         そのルームで自分が見られる範囲のチャット (共有ルームは全員のチャットを共有)
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $cid  = $request->query('customer_id');

        $q = ChatQuery::query()
            ->orderBy('created_at', 'asc')
            ->limit(200);

        if (! $cid || $cid === 'none') {
            // ルーム未選択: 自分の個人スコープのみ
            $q->whereNull('customer_id')->where('user_id', $user?->id);
        } else {
            $customer = Customer::visibleTo($user)->find($cid);
            if (! $customer) {
                return response()->json([]);
            }
            $q->where('customer_id', $customer->id);
            if ($customer->is_personal) {
                $q->where('user_id', $user?->id);
            }
        }

        return response()->json($q->get()->map(fn($r) => [
            'id'       => $r->id,
            'question' => $r->question,
            'answer'   => $r->answer,
            'sources'  => $r->sources ?? [],
            'status'   => $r->status,
            'provider' => $r->provider,
            'model'    => $r->model,
        ]));
    }

    public function models(): JsonResponse
    {
        try {
            $data = $this->ragApi->getModels();
            try {
                $settings = \App\Models\AiSetting::getSettings();
                $data['has_claude_key'] = !empty($settings->anthropic_api_key);
                $data['has_gemini_key'] = !empty($settings->gemini_api_key);
            } catch (\Throwable $e) {
                Log::warning('ChatController.models: AiSetting lookup failed', ['error' => $e->getMessage()]);
            }
            return response()->json($data);
        } catch (\Exception $e) {
            Log::warning('ChatController.models: RAG models fetch failed', ['error' => $e->getMessage()]);
            return response()->json(['ollama' => [], 'claude' => [], 'gemini' => [], 'has_claude_key' => false, 'has_gemini_key' => false]);
        }
    }

    public function query(Request $request): JsonResponse
    {
        $request->validate([
            'question'    => 'required|string|max:2000',
            'provider'    => 'nullable|string|in:ollama,claude,gemini',
            'model'       => 'nullable|string|max:128',
            'customer_id' => 'nullable|integer|exists:customers,id',
        ]);

        $user = $request->user();
        $cid  = $request->input('customer_id');

        // 指定ルームが本人に見えなければ拒否
        if ($cid) {
            $customer = Customer::visibleTo($user)->find($cid);
            if (! $customer) {
                return response()->json(['status' => 'error', 'message' => 'このルームにはアクセスできません。'], 403);
            }
        }

        $record = ChatQuery::create([
            'question'    => $request->input('question'),
            'provider'    => $request->input('provider'),
            'model'       => $request->input('model'),
            'customer_id' => $cid,
            'user_id'     => $user?->id,
        ]);
        ProcessChatQuery::dispatch($record->id);

        return response()->json(['id' => $record->id, 'status' => 'pending']);
    }

    public function result(string $id): JsonResponse
    {
        $record = ChatQuery::findOrFail($id);

        return response()->json([
            'id' => $record->id,
            'status' => $record->status,
            'answer' => $record->answer,
            'sources' => $record->sources ?? [],
            'error' => $record->error_message,
        ]);
    }
}
