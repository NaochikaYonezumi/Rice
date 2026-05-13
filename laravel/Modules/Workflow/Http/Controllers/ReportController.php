<?php

namespace Modules\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Workflow\Services\ReportService;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reportService)
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $stats = $reportService->getSummary($validated);

        return view('workflow::dashboard', compact('stats'));
    }

    /**
     * Phase 6-3: AI 利用統計 JSON API
     *
     * クエリ:
     *   ?from=YYYY-MM-DD
     *   ?to=YYYY-MM-DD
     *   ?group_by=user|collection|day
     */
    public function aiUsage(Request $request, ReportService $reportService): JsonResponse
    {
        $validated = $request->validate([
            'from'     => 'nullable|date',
            'to'       => 'nullable|date|after_or_equal:from',
            'group_by' => 'nullable|string|in:user,collection,day',
        ]);

        $from = !empty($validated['from']) ? Carbon::parse($validated['from']) : null;
        $to   = !empty($validated['to'])   ? Carbon::parse($validated['to'])   : null;

        $payload = [
            'summary'   => $reportService->aiUsageStats($from, $to),
            'by_day'    => $reportService->aiUsageByDay($from, $to),
            'histogram' => $reportService->aiConfidenceHistogram($from, $to),
        ];

        // group_by 指定があればその切り口だけ返す。なければ両方返す。
        $groupBy = $validated['group_by'] ?? null;
        if (!$groupBy || $groupBy === 'user') {
            $payload['by_user'] = $reportService->aiUsageByUser($from, $to);
        }
        if (!$groupBy || $groupBy === 'collection') {
            $payload['by_collection'] = $reportService->aiUsageByCollection($from, $to);
        }

        return response()->json($payload);
    }
}
