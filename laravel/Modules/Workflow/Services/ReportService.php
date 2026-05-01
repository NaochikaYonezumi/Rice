<?php

namespace Modules\Workflow\Services;

use App\Models\EmailThread;
use App\Models\Email;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Get basic statistics for the dashboard.
     */
    public function getSummary()
    {
        return [
            'total_threads' => EmailThread::count(),
            'open_threads'  => EmailThread::where('status', 'inbox')->count(),
            'agent_replies' => $this->getAgentReplyStats(),
            'status_stats'  => $this->getStatusStats(),
            'tag_stats'     => $this->getTagStats(),
        ];
    }

    protected function getAgentReplyStats()
    {
        // エージェント（User）ごとの送信メール数をカウント
        return DB::table('emails')
            ->join('users', 'emails.from_address', '=', 'users.email')
            ->select('users.name', DB::raw('count(emails.id) as reply_count'))
            ->groupBy('users.name')
            ->get();
    }

    protected function getStatusStats()
    {
        return EmailThread::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();
    }

    protected function getTagStats()
    {
        // JSONカラムである tags の集計 (MySQL 8.0+想定)
        // 注意: データベースの実装に依存するため簡易的な集計
        $threads = EmailThread::whereNotNull('tags')->get();
        $tagsCount = [];
        foreach ($threads as $thread) {
            foreach ($thread->tags as $tag) {
                $tagsCount[$tag] = ($tagsCount[$tag] ?? 0) + 1;
            }
        }
        return $tagsCount;
    }
}
