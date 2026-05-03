<?php

namespace Modules\Workflow\Services;

use App\Models\Email;
use App\Models\EmailThread;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * フィルタ可能なサマリー統計を返す。
     *
     * @param  array{from?:?string, to?:?string} $filters
     */
    public function getSummary(array $filters = []): array
    {
        $from = !empty($filters['from'])
            ? Carbon::parse($filters['from'])->startOfDay()
            : Carbon::today()->subDays(29)->startOfDay();
        $to = !empty($filters['to'])
            ? Carbon::parse($filters['to'])->endOfDay()
            : Carbon::today()->endOfDay();

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'totals'      => $this->getTotals($from, $to),
            'by_assignee' => $this->getStatsByAssignee($from, $to),
            'by_date'     => $this->getStatsByDate($from, $to),
            'by_tag'      => $this->getStatsByTag($from, $to),
            'by_status'   => $this->getStatsByStatus($from, $to),
        ];
    }

    /**
     * 期間全体のサマリー (4 つの主要数値)
     */
    protected function getTotals(Carbon $from, Carbon $to): array
    {
        $threadIdsInPeriod = EmailThread::where(function ($q) use ($from, $to) {
                $q->whereBetween('last_email_at', [$from, $to])
                  ->orWhereBetween('created_at', [$from, $to]);
            })->pluck('id');

        return [
            'threads_total'      => EmailThread::count(),
            'threads_in_period'  => $threadIdsInPeriod->count(),
            'emails_in_period'   => Email::whereBetween('received_at', [$from, $to])->count(),
            'open_threads'       => EmailThread::where('status', 'inbox')->count(),
        ];
    }

    /**
     * 担当者別: 受持スレッド数 + ステータス別内訳 + 期間内メール数
     */
    protected function getStatsByAssignee(Carbon $from, Carbon $to): array
    {
        // ベース: 担当者ごとのスレッド総数とステータス内訳
        $rows = EmailThread::query()
            ->select(
                'assigned_user_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'inbox'     THEN 1 ELSE 0 END) as inbox_count"),
                DB::raw("SUM(CASE WHEN status = 'hold'      THEN 1 ELSE 0 END) as hold_count"),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count"),
                DB::raw("SUM(CASE WHEN status = 'pending'   THEN 1 ELSE 0 END) as pending_count")
            )
            ->groupBy('assigned_user_id')
            ->get();

        // 担当者名を一括取得
        $userIds = $rows->pluck('assigned_user_id')->filter()->unique();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        // 期間内に追加されたメール (担当者ごと)
        $emailsPerAssignee = Email::query()
            ->select('email_threads.assigned_user_id as uid', DB::raw('COUNT(*) as cnt'))
            ->join('email_threads', 'emails.thread_id', '=', 'email_threads.id')
            ->whereBetween('emails.received_at', [$from, $to])
            ->groupBy('email_threads.assigned_user_id')
            ->pluck('cnt', 'uid');

        $result = [];
        foreach ($rows as $row) {
            $uid = $row->assigned_user_id;
            $name = $uid && $users->has($uid) ? $users[$uid]->name : '未設定';
            $result[] = [
                'user_id'         => $uid,
                'name'            => $name,
                'total'           => (int) $row->total,
                'inbox'           => (int) $row->inbox_count,
                'hold'            => (int) $row->hold_count,
                'completed'       => (int) $row->completed_count,
                'pending'         => (int) $row->pending_count,
                'emails_period'   => (int) ($emailsPerAssignee[$uid] ?? 0),
            ];
        }

        // 多い順
        usort($result, fn($a, $b) => $b['total'] <=> $a['total']);
        return $result;
    }

    /**
     * 日付別: 期間内の日次メール件数 + 期間内日次新規スレッド件数
     */
    protected function getStatsByDate(Carbon $from, Carbon $to): array
    {
        $emailsByDate = Email::query()
            ->select(DB::raw('DATE(received_at) as d'), DB::raw('COUNT(*) as cnt'))
            ->whereBetween('received_at', [$from, $to])
            ->groupBy('d')
            ->pluck('cnt', 'd');

        $threadsByDate = EmailThread::query()
            ->select(DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as cnt'))
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('d')
            ->pluck('cnt', 'd');

        $period = CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay());
        $rows = [];
        $maxEmails = 0;
        $maxThreads = 0;
        foreach ($period as $day) {
            $key = $day->toDateString();
            $emails  = (int) ($emailsByDate[$key]  ?? 0);
            $threads = (int) ($threadsByDate[$key] ?? 0);
            $rows[] = [
                'date'    => $key,
                'label'   => $day->format('m/d'),
                'weekday' => ['日','月','火','水','木','金','土'][$day->dayOfWeek],
                'emails'  => $emails,
                'threads' => $threads,
            ];
            $maxEmails  = max($maxEmails, $emails);
            $maxThreads = max($maxThreads, $threads);
        }

        return [
            'rows'        => $rows,
            'max_emails'  => $maxEmails,
            'max_threads' => $maxThreads,
        ];
    }

    /**
     * タグ別: 件数 + ステータス内訳
     */
    protected function getStatsByTag(Carbon $from, Carbon $to): array
    {
        $threads = EmailThread::query()
            ->whereNotNull('tags')
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('last_email_at', [$from, $to])
                  ->orWhereBetween('created_at', [$from, $to]);
            })
            ->get(['id', 'tags', 'status']);

        $bucket = []; // tag => ['total','inbox','hold','completed','pending']
        foreach ($threads as $t) {
            $tags = $t->tags ?? [];
            foreach ($tags as $tag) {
                if (!is_string($tag) || $tag === '') continue;
                if (!isset($bucket[$tag])) {
                    $bucket[$tag] = ['name' => $tag, 'total' => 0, 'inbox' => 0, 'hold' => 0, 'completed' => 0, 'pending' => 0];
                }
                $bucket[$tag]['total']++;
                if (in_array($t->status, ['inbox', 'hold', 'completed', 'pending'], true)) {
                    $bucket[$tag][$t->status]++;
                }
            }
        }

        $rows = array_values($bucket);
        usort($rows, fn($a, $b) => $b['total'] <=> $a['total']);
        return $rows;
    }

    /**
     * ステータス別 (期間に依存せず全体)
     */
    protected function getStatsByStatus(Carbon $from, Carbon $to): array
    {
        $rows = EmailThread::query()
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return [
            'inbox'     => (int) ($rows['inbox']     ?? 0),
            'hold'      => (int) ($rows['hold']      ?? 0),
            'completed' => (int) ($rows['completed'] ?? 0),
            'pending'   => (int) ($rows['pending']   ?? 0),
        ];
    }
}
