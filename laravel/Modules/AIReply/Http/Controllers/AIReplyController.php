<?php

namespace Modules\AIReply\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AiLog;
use App\Models\EmailThread;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\AIReply\Services\AIReplyService;

class AIReplyController extends Controller
{
    public function generate(EmailThread $thread, AIReplyService $aiService)
    {
        $result = $aiService->generate($thread);

        if (!$result) {
            return response()->json(['error' => 'Could not generate reply draft.'], 422);
        }

        // Phase 6-3: 直近に作成された ext_ai_logs の id をフロントへ返す
        // (採用率算定で pending_email.ai_log_id に紐付けるため)
        $aiLogId = null;
        try {
            if (Schema::hasTable('ext_ai_logs')) {
                $aiLogId = AiLog::where('email_thread_id', $thread->id)
                    ->where('user_id', auth()->id())
                    ->orderByDesc('id')
                    ->value('id');
            }
        } catch (\Throwable $e) { /* noop */ }

        return response()->json([
            'draft'     => $result['answer'],
            'sources'   => $result['sources'] ?? [],
            'ai_log_id' => $aiLogId,
        ]);
    }
}
