@php
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $firstName = explode(' ', trim(auth()->user()->name))[0];

    $delta = function (int $current, int $previous) {
        if ($previous === 0) {
            return null;
        }
        return (int) round((($current - $previous) / $previous) * 100);
    };
    $todayDelta = $kpis['today'] - $kpis['yesterday'];
    $weekDelta = $delta($kpis['week'], $kpis['prev_week']);

    $dayMax = max(1, collect($days)->max('total'));
    $step = max(1, (int) ceil($dayMax / 4));
    $axisMax = $step * 4;

    $statusTotal = array_sum($statuses);
    $statusRows = [
        ['key' => 'draft', 'label' => 'Draft', 'icon' => 'clock', 'color' => '#eb6834', 'count' => $statuses['draft']],
        ['key' => 'correction', 'label' => 'Under correction', 'icon' => 'edit', 'color' => '#7c3aed', 'count' => $statuses['correction']],
        ['key' => 'final', 'label' => 'Finalized', 'icon' => 'check', 'color' => '#13a397', 'count' => $statuses['final']],
    ];
    $categoryMax = max(1, collect($categories)->max('count') ?? 1);
    $testMax = max(1, collect($topTests)->max('count') ?? 1);
@endphp

<x-app-layout title="Dashboard">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-slate-500">{{ now()->format('l, j F Y') }}</p>
            <h1 class="mt-0.5 text-2xl font-bold tracking-tight text-navy-900">{{ $greeting }}, {{ $firstName }}</h1>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('tests.index') }}" class="btn btn-secondary"><x-icon name="flask" /> Manage tests</a>
            <a href="{{ route('reports.create') }}" class="btn btn-accent"><x-icon name="plus" /> Create new report</a>
        </div>
    </div>

    @if ($kpis['tests_needing_review'] > 0)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="flex items-center gap-2">
                <x-icon name="flag" class="h-4 w-4 shrink-0" />
                <span><strong>{{ $kpis['tests_needing_review'] }}</strong> test {{ Str::plural('definition', $kpis['tests_needing_review']) }} imported from the reference workbook {{ $kpis['tests_needing_review'] === 1 ? 'is' : 'are' }} flagged for review (ambiguous or missing reference intervals).</span>
            </p>
            <a href="{{ route('tests.index', ['status' => 'review']) }}" class="btn btn-sm btn-secondary">Review tests</a>
        </div>
    @endif

    {{-- KPI cards --}}
    <section aria-label="Key figures" class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <div class="card p-5">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Registered today</p>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700"><x-icon name="calendar" /></span>
            </div>
            <p class="mt-3 text-3xl font-bold tracking-tight text-navy-900">{{ number_format($kpis['today']) }}</p>
            <p class="mt-1 text-xs text-slate-500">
                @if ($todayDelta > 0)<span class="font-semibold text-brand-700">+{{ $todayDelta }}</span>@elseif ($todayDelta < 0)<span class="font-semibold text-slate-700">{{ $todayDelta }}</span>@else<span class="font-semibold text-slate-700">±0</span>@endif
                vs yesterday ({{ $kpis['yesterday'] }})
            </p>
        </div>
        <div class="card p-5">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Last 7 days</p>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 text-brand-700"><x-icon name="activity" /></span>
            </div>
            <p class="mt-3 text-3xl font-bold tracking-tight text-navy-900">{{ number_format($kpis['week']) }}</p>
            <p class="mt-1 text-xs text-slate-500">
                @if ($weekDelta === null)
                    No reports in the previous 7 days
                @else
                    <span class="font-semibold {{ $weekDelta >= 0 ? 'text-brand-700' : 'text-slate-700' }}">{{ $weekDelta >= 0 ? '+' : '' }}{{ $weekDelta }}%</span> vs previous 7 days ({{ $kpis['prev_week'] }})
                @endif
            </p>
        </div>
        <a href="{{ route('reports.index', ['status' => 'draft']) }}" class="card p-5 transition hover:border-orange-300">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Awaiting release</p>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-orange-50 text-orange-600"><x-icon name="clock" /></span>
            </div>
            <p class="mt-3 text-3xl font-bold tracking-tight text-navy-900">{{ number_format($kpis['drafts']) }}</p>
            <p class="mt-1 text-xs text-slate-500">Draft reports{{ $kpis['corrections'] ? ', incl. '.$kpis['corrections'].' under correction' : '' }}</p>
        </a>
        <a href="{{ route('reports.index', ['status' => 'final']) }}" class="card p-5 transition hover:border-brand-300">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Finalized</p>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 text-brand-700"><x-icon name="check-circle" /></span>
            </div>
            <p class="mt-3 text-3xl font-bold tracking-tight text-navy-900">{{ number_format($kpis['finalized']) }}</p>
            <p class="mt-1 text-xs text-slate-500">Released reports, all time</p>
        </a>
    </section>

    <section aria-label="Secondary figures" class="mt-4 grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-slate-200 bg-slate-200 shadow-card md:grid-cols-4">
        <div class="bg-white px-5 py-3">
            <p class="text-xs font-medium text-slate-500">Total reports</p>
            <p class="text-lg font-bold text-navy-900">{{ number_format($kpis['total']) }}</p>
        </div>
        <a href="{{ route('tests.index', ['status' => 'active']) }}" class="bg-white px-5 py-3 hover:bg-slate-50">
            <p class="text-xs font-medium text-slate-500">Active test definitions</p>
            <p class="text-lg font-bold text-navy-900">{{ number_format($kpis['active_tests']) }}</p>
        </a>
        <a href="{{ route('reports.index', ['status' => 'flagged']) }}" class="bg-white px-5 py-3 hover:bg-slate-50" title="Finalized reports with at least one numeric result outside its applicable reference interval">
            <p class="text-xs font-medium text-slate-500">Finalized with flagged results</p>
            <p class="text-lg font-bold text-navy-900">{{ number_format($kpis['flagged']) }}</p>
        </a>
        <a href="{{ route('reports.index', ['status' => 'correction']) }}" class="bg-white px-5 py-3 hover:bg-slate-50">
            <p class="text-xs font-medium text-slate-500">Under correction</p>
            <p class="text-lg font-bold text-navy-900">{{ number_format($kpis['corrections']) }}</p>
        </a>
    </section>

    {{-- Charts --}}
    <section class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Reports registered — last 7 days</h2>
                    <p class="text-xs text-slate-500">By registration date, {{ config('app.timezone') }}</p>
                </div>
                <div class="flex items-center gap-4 text-xs text-slate-600" aria-hidden="true">
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background:#13a397"></span>Finalized</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background:#eb6834"></span>Not yet finalized</span>
                </div>
            </div>
            <div class="card-body">
                @include('dashboard.partials.daily-chart', ['days' => $days, 'step' => $step, 'axisMax' => $axisMax])

                <details class="mt-4 text-xs text-slate-500">
                    <summary class="cursor-pointer select-none font-medium hover:text-slate-700">Show as table</summary>
                    <table class="mt-2 w-full text-left">
                        <thead><tr class="text-slate-500"><th class="py-1 font-medium">Day</th><th class="py-1 text-right font-medium">Finalized</th><th class="py-1 text-right font-medium">Not finalized</th><th class="py-1 text-right font-medium">Total</th></tr></thead>
                        <tbody class="tabular-nums text-slate-700">
                            @foreach ($days as $day)
                                <tr class="border-t border-slate-100"><td class="py-1">{{ \Illuminate\Support\Carbon::parse($day['date'])->format('D, j M') }}</td><td class="py-1 text-right">{{ $day['final'] }}</td><td class="py-1 text-right">{{ $day['total'] - $day['final'] }}</td><td class="py-1 text-right font-semibold">{{ $day['total'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </details>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Report status</h2>
                    <p class="text-xs text-slate-500">{{ $periods[$period] }} · {{ $statusTotal }} {{ Str::plural('report', $statusTotal) }}</p>
                </div>
            </div>
            <div class="card-body space-y-4">
                @if ($statusTotal === 0)
                    <x-empty-state icon="report" title="No reports in this period" class="py-6" />
                @else
                    @foreach ($statusRows as $row)
                        @php $pct = $statusTotal ? round($row['count'] / $statusTotal * 100) : 0; @endphp
                        <div>
                            <div class="flex items-center justify-between text-sm">
                                <span class="flex items-center gap-2 font-medium text-slate-700"><x-icon :name="$row['icon']" class="h-4 w-4 text-slate-400" />{{ $row['label'] }}</span>
                                <span class="tabular-nums text-slate-900"><strong>{{ $row['count'] }}</strong> <span class="text-slate-400">· {{ $pct }}%</span></span>
                            </div>
                            <div class="mt-1.5 h-2 w-full rounded-full bg-slate-100" title="{{ $row['label'] }}: {{ $row['count'] }} ({{ $pct }}%)">
                                <div class="h-2 rounded-full" style="width: {{ $pct }}%; background: {{ $row['color'] }}"></div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </section>

    {{-- Test mix --}}
    <section class="mt-6">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-navy-900">Test mix</h2>
            <nav class="flex rounded-lg border border-slate-200 bg-white p-0.5 text-xs font-semibold" aria-label="Period">
                @foreach ($periods as $key => $label)
                    <a href="{{ request()->fullUrlWithQuery(['period' => $key]) }}"
                       @class(['rounded-md px-3 py-1.5 transition', 'bg-navy-900 text-white' => $period === $key, 'text-slate-600 hover:bg-slate-100' => $period !== $key])
                       @if ($period === $key) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
            </nav>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Tests by category</h3><span class="text-xs text-slate-500">Tests performed</span></div>
                <div class="card-body space-y-3">
                    @forelse ($categories as $row)
                        <div class="grid grid-cols-[minmax(0,10rem)_1fr_auto] items-center gap-3 text-sm" title="{{ $row['label'] }}: {{ $row['count'] }}">
                            <span class="truncate text-slate-600">{{ $row['label'] }}</span>
                            <div class="h-3 rounded-r bg-slate-50"><div class="h-3 rounded-r-[4px] bg-brand-500" style="width: {{ $row['count'] / $categoryMax * 100 }}%"></div></div>
                            <span class="w-8 text-right font-semibold tabular-nums text-slate-900">{{ $row['count'] }}</span>
                        </div>
                    @empty
                        <x-empty-state icon="layers" title="No tests performed in this period" class="py-6" />
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Most requested tests</h3><span class="text-xs text-slate-500">Top {{ count($topTests) ?: 6 }}</span></div>
                <div class="card-body space-y-3">
                    @forelse ($topTests as $row)
                        <div class="grid grid-cols-[minmax(0,12rem)_1fr_auto] items-center gap-3 text-sm" title="{{ $row['label'] }}: {{ $row['count'] }}">
                            <span class="truncate text-slate-600"><span class="font-mono text-[11px] text-slate-400">{{ $row['code'] }}</span> {{ $row['label'] }}</span>
                            <div class="h-3 rounded-r bg-slate-50"><div class="h-3 rounded-r-[4px] bg-navy-500" style="width: {{ $row['count'] / $testMax * 100 }}%"></div></div>
                            <span class="w-8 text-right font-semibold tabular-nums text-slate-900">{{ $row['count'] }}</span>
                        </div>
                    @empty
                        <x-empty-state icon="flask" title="No tests performed in this period" class="py-6" />
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    {{-- Recent reports --}}
    <section id="recent" class="card mt-6 scroll-mt-20">
        <div class="card-header">
            <div>
                <h2 class="card-title">Recent reports</h2>
                <p class="text-xs text-slate-500">Most recently created first</p>
            </div>
            <a href="{{ route('reports.index') }}" class="btn btn-sm btn-ghost">View all reports <x-icon name="chevron-right" /></a>
        </div>
        @include('reports.partials.filters', ['action' => route('dashboard'), 'filters' => $filters, 'showCategory' => false, 'fragment' => 'recent', 'keep' => ['period' => $period], 'target' => '#recent-results'])
        <div id="recent-results">@include('reports.partials.table', ['reports' => $reports, 'fragment' => 'recent'])</div>
    </section>
</x-app-layout>
