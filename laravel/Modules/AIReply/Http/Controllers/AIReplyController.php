<?php

namespace Modules\AIReply\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EmailThread;
use Illuminate\Http\Request;
use Modules\AIReply\Services\AIReplyService;

class AIReplyController extends Controller
{
    public function generate(Request $request, EmailThread $thread, AIReplyService $aiService)
    {
        $request->validate([
            'provider' => 'nullable|string|in:ollama,claude,gemini',
            'model'    => 'nullable|string|max:128',
        ]);

        $result = $aiService->generate(
            $thread,
            provider: $request->input('provider'),
            model: $request->input('model'),
        );

        if (!$result) {
            return response()->json(['error' => 'Could not generate reply draft.'], 422);
        }

        return response()->json([
            'draft'   => $result['answer'],
            'sources' => $result['sources'] ?? [],
            'ai_log_id' => $result['ai_log_id'] ?? null,
            'provider'  => $result['provider'] ?? null,
            'model'     => $result['model']    ?? null,
        ]);
    }
}
