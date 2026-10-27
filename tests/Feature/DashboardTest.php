<?php

namespace Tests\Feature;

use App\Services\DashboardService;
use App\Services\ReferenceDataImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_renders_with_an_empty_database(): void
    {
        $this->actingAsAdmin();

        $this->get('/dashboard')->assertOk()
            ->assertSee('Registered today')
            ->assertSee('No reports yet');
    }

    public function test_kpis_respect_calendar_day_boundaries(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-24 10:00:00'));
        $this->admin();
        $test = $this->definition();

        $this->draftReport($test, ['registered_at' => Carbon::parse('2026-10-24 00:00:00')]); // today, first second
        $this->draftReport($test, ['registered_at' => Carbon::parse('2026-10-23 23:59:59')]); // yesterday
        $this->draftReport($test, ['registered_at' => Carbon::parse('2026-10-18 00:00:01')]); // 7th day back → in last 7 days
        $this->draftReport($test, ['registered_at' => Carbon::parse('2026-10-17 23:59:59')]); // outside last 7 days
        $discarded = $this->draftReport($test, ['registered_at' => Carbon::parse('2026-10-24 09:00:00')]);
        $discarded->delete();

        $kpis = app(DashboardService::class)->kpis();

        $this->assertSame(4, $kpis['total']);
        $this->assertSame(1, $kpis['today']);
        $this->assertSame(1, $kpis['yesterday']);
        $this->assertSame(3, $kpis['week']);
        $this->assertSame(1, $kpis['prev_week']);
        $this->assertSame(4, $kpis['drafts']);
        $this->assertSame(0, $kpis['finalized']);

        $days = app(DashboardService::class)->lastSevenDays();
        $this->assertCount(7, $days);
        $this->assertSame('2026-10-18', $days[0]['date']);
        $this->assertSame('Today', $days[6]['label']);
        $this->assertSame([1, 0, 0, 0, 0, 1, 1], array_column($days, 'total'));
    }

    public function test_distribution_charts_and_recent_reports_use_real_data(): void
    {
        $this->actingAsAdmin();
        app(ReferenceDataImportService::class)->import();
        $test = $this->definition();
        $this->draftReport($test, ['patient_name' => 'Chart Patient']);

        $dashboard = app(DashboardService::class);
        $this->assertSame([['label' => 'Haematology', 'count' => 1]], $dashboard->categoryDistribution('7d'));
        $this->assertSame('HB-T', $dashboard->topTests('30d')[0]['code']);
        $this->assertSame(['draft' => 1, 'correction' => 0, 'final' => 0], $dashboard->statusDistribution('all'));

        $this->get('/dashboard?period=7d')->assertOk()
            ->assertSee('Chart Patient')
            ->assertSee('Tests by category')
            ->assertSee('flagged for review');

        $this->get('/dashboard?q=nobody&status=final')->assertOk()->assertSee('No reports match these filters');
        $this->get('/dashboard?period=bogus')->assertOk();
    }
}
