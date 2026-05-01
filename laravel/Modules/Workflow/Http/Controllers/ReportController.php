<?php

namespace Modules\Workflow\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Workflow\Services\ReportService;

class ReportController extends Controller
{
    public function index(ReportService $reportService)
    {
        $stats = $reportService->getSummary();
        return view('workflow::dashboard', compact('stats'));
    }
}
