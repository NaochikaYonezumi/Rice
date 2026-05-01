<?php

namespace Modules\AIReply\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EmailThread;
use Illuminate\Http\Request;
use Modules\AIReply\Services\AIReplyService;

class AIReplyController extends Controller
{
    public function generate(EmailThread $thread, AIReplyService $aiService)
    {
        $result = $aiService->generate($thread);

        if (!$result) {
            return response()->json(['error' => 'Could not generate reply draft.'], 422);
        }

        return response()->json([
            'draft' => $result['answer'],
            'sources' => $result['sources'] ?? []
        ]);
    }
}
