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
            'by_status'   => $this->getStatsByStatus($from, $to),
            'rooms'       => $this->getRoomReports($from, $to),
        ];
    }

    /**
     * 共有ルーム毎のレポート (本文) と関連スレッド数/期間内メール件数
     */
    protected function getRoomReports(Carbon $from, Carbon $to): array
    {
        // DB 側の order は name で安定させておく. 期間内メール件数による並びは下で再ソート.
        $rooms = \App\Models\ChatRoom::where('is_private', false)
            ->orderBy('name')
            ->get();

        $rows = $rooms->map(function ($r) use ($from, $to) {
            // 紐付けられたスレッド数
            $threadIds = $r->bundledThreads()->pluck('email_threads.id')->all();
            $threadsCount = count($threadIds);
            // 期間内に受信されたメールに限定してカウント
            $emailsCount = $threadsCount > 0
                ? \App\Models\Email::whereIn('thread_id', $threadIds)
                    ->whereBetween('received_at', [$from, $to])
                    ->count()
                : 0;

            $report = (string) ($r->report_content ?? '');
            $hasReport = trim($report) !== '';
            // 行数 + 先頭400字プレビュー
            $preview = \Illuminate\Support\Str::limit($report, 400);

            return [
                'id'            => $r->id,
                'name'          => $r->name,
                'has_report'    => $hasReport,
                'preview'       => $preview,
                'updated_at'    => $r->report_updated_at?->format('Y/m/d H:i'),
                'threads_count' => $threadsCount,
                'emails_count'  => $emailsCount,
            ];
        })->all();

        // 期間内メール件数の降順で並べ替え (= 多い順, ユーザ要望: 件数順に表示).
        // 同件数は name 昇順で安定ソート.
        usort($rows, function ($a, $b) {
            if ($a['emails_count'] !== $b['emails_count']) {
                return $b['emails_count'] <=> $a['emails_count'];
            }
            return strcmp((string) $a['name'], (string) $b['name']);
        });

        return $rows;
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
            // 「未対応 (受信)」は inbox だけでなく hold / pending (承認待ち) も含めて数える。
            // さらにメール一覧・サイドバーバッチと完全に同じ可視条件を適用する:
            //   - マージ source は一覧で非表示なので除外
            //   - 添付アップロード由来の合成スレッドは除外
            //   - emails 行を 1 通も持たない孤児スレッドは除外
            // 旧実装は素の COUNT() だったため、孤児/マージ source が含まれて
            //  「レポートには 2 件、画面にはどこにもない」事象が発生していた。
            'open_threads'       => $this->visibleThreadQuery()
                ->whereIn('status', [
                    EmailThread::STATUS_INBOX,
                    EmailThread::STATUS_HOLD,
                    EmailThread::STATUS_AWAITING_APPROVAL,
                ])->count(),
        ];
    }

    /**
     * メール一覧 / サイドバーバッチと一致する「ユーザに見えるスレッド」の
     * 共通クエリビルダ。集計のズレを防ぐためレポートでも同じ条件を使う。
     */
    protected function visibleThreadQuery()
    {
        return EmailThread::query()
            ->whereNotIn('id', \App\Models\ThreadMerge::select('source_thread_id_original'))
            ->where(function ($q) {
                $q->where('is_manual_upload', false)
                  ->orWhereNull('is_manual_upload');
            })
            ->has('emails');
    }

    /**
     * 担当者別: 受持スレッド数 + ステータス別内訳 + 期間内メール数
     */
    protected function getStatsByAssignee(Carbon $from, Carbon $to): array
    {
        // ベース: 担当者ごとのスレッド総数とステータス内訳
        // メール一覧の可視条件と同じフィルタを掛けないと、孤児/マージ source が紛れて
        // 画面と数字が食い違うので visibleThreadQuery() を使う。
        $rows = $this->visibleThreadQuery()
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
        // ユーザ要望: 最新の日付を上部に. 上から「直近の活動 → 古い活動」の順で読みたい.
        $rows = array_reverse($rows);

        return [
            'rows'        => $rows,
            'max_emails'  => $maxEmails,
            'max_threads' => $maxThreads,
        ];
    }

    /**
     * ステータス別 (期間に依存せず全体)
     */
    protected function getStatsByStatus(Carbon $from, Carbon $to): array
    {
        // メール一覧の可視条件と同じ。レポート側に「画面では見えないスレッド」が
        // 紛れていると、ユーザが追いかけられない「幽霊」件数になるので除外。
        $rows = $this->visibleThreadQuery()
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
