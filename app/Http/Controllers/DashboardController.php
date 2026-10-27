<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\ReportQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard, ReportQuery $reports): View
    {
        $period = $dashboard->normalizePeriod($request->query('period'));
        $filters = $request->only(['q', 'status', 'from', 'to', 'sort', 'dir']);

        return view('dashboard.index', [
            'kpis' => $dashboard->kpis(),
            'days' => $dashboard->lastSevenDays(),
            'period' => $period,
            'periods' => DashboardService::PERIODS,
            'categories' => $dashboard->categoryDistribution($period),
            'topTests' => $dashboard->topTests($period),
            'statuses' => $dashboard->statusDistribution($period),
            'reports' => $reports->paginate($filters, 8)->fragment('recent'),
            'filters' => $filters,
            'statusFilters' => ReportQuery::STATUS_FILTERS,
        ]);
    }
}
