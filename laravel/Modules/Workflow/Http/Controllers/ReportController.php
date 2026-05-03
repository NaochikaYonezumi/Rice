<?php

namespace Modules\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
}
