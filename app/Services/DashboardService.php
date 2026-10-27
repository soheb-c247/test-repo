<?php

namespace App\Services;

use App\Models\Report;
use App\Models\TestDefinition;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Database-backed dashboard metrics. All counts are aggregate queries; no
 * report rows are loaded into memory.
 *
 * Definitions (application timezone, config('app.timezone')):
 *  - "today"         registered_at between 00:00:00 and 23:59:59 today
 *  - "last 7 days"   today plus the six previous calendar days
 *  - "draft"         status = draft (includes reports under correction)
 *  - "finalized"     status = final
 *  - "flagged"       finalized reports with ≥1 numeric result outside its
 *                    applicable reference interval
 * Soft-deleted (discarded) drafts are excluded everywhere.
 */
class DashboardService
{
    public const PERIODS = [
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        '90d' => 'Last 90 days',
        'all' => 'All time',
    ];

    public function kpis(?Carbon $now = null): array
    {
        $now = $now ?? now();
        $today = $now->copy()->startOfDay();
        $weekStart = $today->copy()->subDays(6);
        $prevWeekStart = $weekStart->copy()->subDays(7);
        $yesterday = $today->copy()->subDay();

        $row = Report::query()->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN registered_at >= ? AND registered_at <= ? THEN 1 ELSE 0 END) as today', [$today, $now->copy()->endOfDay()])
            ->selectRaw('SUM(CASE WHEN registered_at >= ? AND registered_at < ? THEN 1 ELSE 0 END) as yesterday', [$yesterday, $today])
            ->selectRaw('SUM(CASE WHEN registered_at >= ? AND registered_at <= ? THEN 1 ELSE 0 END) as week', [$weekStart, $now->copy()->endOfDay()])
            ->selectRaw('SUM(CASE WHEN registered_at >= ? AND registered_at < ? THEN 1 ELSE 0 END) as prev_week', [$prevWeekStart, $weekStart])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as drafts', [Report::STATUS_DRAFT])
            ->selectRaw('SUM(CASE WHEN status = ? AND revision > 0 THEN 1 ELSE 0 END) as corrections', [Report::STATUS_DRAFT])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as finalized', [Report::STATUS_FINAL])
            ->selectRaw('SUM(CASE WHEN status = ? AND abnormal_count > 0 THEN 1 ELSE 0 END) as flagged', [Report::STATUS_FINAL])
            ->whereNull('deleted_at')
            ->first();

        return [
            'total' => (int) $row->total,
            'today' => (int) $row->today,
            'yesterday' => (int) $row->yesterday,
            'week' => (int) $row->week,
            'prev_week' => (int) $row->prev_week,
            'drafts' => (int) $row->drafts,
            'corrections' => (int) $row->corrections,
            'finalized' => (int) $row->finalized,
            'flagged' => (int) $row->flagged,
            'active_tests' => TestDefinition::query()->active()->count(),
            'tests_needing_review' => TestDefinition::query()->where('needs_review', true)->count(),
        ];
    }

    /**
     * Reports registered per calendar day for the last seven days (oldest first).
     *
     * @return list<array{date:string,label:string,total:int,final:int}>
     */
    public function lastSevenDays(?Carbon $now = null): array
    {
        $now = $now ?? now();
        $start = $now->copy()->startOfDay()->subDays(6);

        $rows = Report::query()->toBase()
            ->selectRaw('DATE(registered_at) as day, COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as final', [Report::STATUS_FINAL])
            ->whereNull('deleted_at')
            ->where('registered_at', '>=', $start)
            ->where('registered_at', '<=', $now->copy()->endOfDay())
            ->groupBy(DB::raw('DATE(registered_at)'))
            ->get()
            ->keyBy(fn ($row) => (string) $row->day);

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $row = $rows->get($day->toDateString());
            $days[] = [
                'date' => $day->toDateString(),
                'label' => $day->isSameDay($now) ? 'Today' : $day->format('D d'),
                'total' => (int) ($row->total ?? 0),
                'final' => (int) ($row->final ?? 0),
            ];
        }

        return $days;
    }

    /**
     * @return list<array{label:string,count:int}>
     */
    public function categoryDistribution(string $period, ?Carbon $now = null): array
    {
        return $this->itemsInPeriod($period, $now)
            ->selectRaw('report_items.category as label, COUNT(*) as count')
            ->groupBy('report_items.category')
            ->orderByDesc('count')
            ->limit(8)
            ->get()
            ->map(fn ($row) => ['label' => (string) $row->label, 'count' => (int) $row->count])
            ->all();
    }

    /**
     * @return list<array{label:string,code:string,count:int}>
     */
    public function topTests(string $period, ?Carbon $now = null): array
    {
        return $this->itemsInPeriod($period, $now)
            ->selectRaw('report_items.test_name as label, report_items.test_code as code, COUNT(*) as count')
            ->groupBy('report_items.test_name', 'report_items.test_code')
            ->orderByDesc('count')
            ->limit(6)
            ->get()
            ->map(fn ($row) => ['label' => (string) $row->label, 'code' => (string) $row->code, 'count' => (int) $row->count])
            ->all();
    }

    /**
     * @return array<string,int> draft / correction / final counts
     */
    public function statusDistribution(string $period, ?Carbon $now = null): array
    {
        $query = Report::query()->toBase()->whereNull('deleted_at');
        $this->applyPeriod($query, 'registered_at', $period, $now);

        $row = $query
            ->selectRaw('SUM(CASE WHEN status = ? AND revision = 0 THEN 1 ELSE 0 END) as draft', [Report::STATUS_DRAFT])
            ->selectRaw('SUM(CASE WHEN status = ? AND revision > 0 THEN 1 ELSE 0 END) as correction', [Report::STATUS_DRAFT])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as final', [Report::STATUS_FINAL])
            ->first();

        return [
            'draft' => (int) ($row->draft ?? 0),
            'correction' => (int) ($row->correction ?? 0),
            'final' => (int) ($row->final ?? 0),
        ];
    }

    public function normalizePeriod(?string $period): string
    {
        return array_key_exists((string) $period, self::PERIODS) ? $period : '30d';
    }

    private function itemsInPeriod(string $period, ?Carbon $now)
    {
        $query = DB::table('report_items')
            ->join('reports', 'reports.id', '=', 'report_items.report_id')
            ->whereNull('reports.deleted_at');

        $this->applyPeriod($query, 'reports.registered_at', $period, $now);

        return $query;
    }

    private function applyPeriod($query, string $column, string $period, ?Carbon $now): void
    {
        $now = $now ?? now();
        $days = match ($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => null,
        };

        if ($days !== null) {
            $query->where($column, '>=', $now->copy()->startOfDay()->subDays($days - 1));
        }
    }
}
