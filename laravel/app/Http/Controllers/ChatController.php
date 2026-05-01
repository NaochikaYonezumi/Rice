<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessChatQuery;
use App\Models\ChatQuery;
use App\Services\RagApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private RagApiService $ragApi) {}

    public function index()
    {
        return view('chat.index');
    }

    public function models(): JsonResponse
    {
        try {
            $data = $this->ragApi->getModels();
            try {
                $settings = \App\Models\AiSetting::getSettings();
                $data['has_claude_key'] = !empty($settings->anthropic_api_key);
                $data['has_gemini_key'] = !empty($settings->gemini_api_key);
            } catch (\Throwable) {}
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['ollama' => [], 'claude' => [], 'gemini' => [], 'has_claude_key' => false, 'has_gemini_key' => false]);
        }
    }

    public function query(Request $request): JsonResponse
    {
        $request->validate([
            'question' => 'required|string|max:2000',
            'provider' => 'nullable|string|in:ollama,claude,gemini',
            'model' => 'nullable|string|max:128',
        ]);

        $record = ChatQuery::create([
            'question' => $request->input('question'),
            'provider' => $request->input('provider'),
            'model' => $request->input('model'),
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
