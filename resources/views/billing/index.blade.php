@php
    $currency = config('lab.currency');
    $money = fn ($v) => $currency.number_format((float) $v, 2);
    $active = collect($filters)->only(['q', 'status', 'from', 'to'])->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty()
        || ! empty($filters['sort']);
@endphp
<x-app-layout title="Billing" :breadcrumbs="['Dashboard' => route('dashboard'), 'Billing' => null]"
              subtitle="Bills for reports, discounts and payments received.">
    <x-slot:actions>
        <a href="{{ route('billing.create') }}" class="btn btn-accent"><x-icon name="plus" /> New bill</a>
    </x-slot:actions>

    <section aria-label="Billing figures" class="mb-6 grid grid-cols-2 gap-4 xl:grid-cols-4">
        <div class="card p-5">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Collected today</p>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 text-brand-700"><x-icon name="wallet" /></span>
            </div>
            <p class="mt-3 text-2xl font-bold tracking-tight text-navy-900 tabular-nums">{{ $money($summary['collected_today']) }}</p>
            <p class="mt-1 text-xs text-slate-500">Payments received since midnight</p>
        </div>
        <div class="card p-5">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Collected this month</p>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700"><x-icon name="calendar" /></span>
            </div>
            <p class="mt-3 text-2xl font-bold tracking-tight text-navy-900 tabular-nums">{{ $money($summary['collected_month']) }}</p>
            <p class="mt-1 text-xs text-slate-500">Since {{ now()->startOfMonth()->format('j M') }}</p>
        </div>
        <div class="card p-5">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Billed this month</p>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-navy-50 text-navy-700"><x-icon name="receipt" /></span>
            </div>
            <p class="mt-3 text-2xl font-bold tracking-tight text-navy-900 tabular-nums">{{ $money($summary['billed_month']) }}</p>
            <p class="mt-1 text-xs text-slate-500">Bill totals after discount</p>
        </div>
        <a href="{{ route('billing.index', ['status' => 'due']) }}" class="card p-5 transition hover:border-red-300">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-500">Balance due</p>
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-red-700"><x-icon name="alert" /></span>
            </div>
            <p class="mt-3 text-2xl font-bold tracking-tight text-navy-900 tabular-nums">{{ $money($summary['outstanding']) }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $summary['outstanding_count'] }} {{ Str::plural('bill', $summary['outstanding_count']) }} not fully paid</p>
        </a>
    </section>

    <div class="card">
        <form method="GET" action="{{ route('billing.index') }}" role="search" x-data="liveFilters" data-target="#bill-results"
              class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-4">
            <div class="w-full min-w-[14rem] sm:w-auto sm:flex-1">
                <label for="b-q" class="label">Search</label>
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input id="b-q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" class="input input-sm pl-9 pr-24"
                           placeholder="Bill no., report no., patient or phone" maxlength="100" autocomplete="off"
                           @input="queue()" @keydown.enter.prevent="search()">
                    <span x-cloak x-show="loading" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">Searching…</span>
                </div>
            </div>
            <div class="contents">
                <div class="w-full sm:w-40">
                    <label for="b-status" class="label">Status</label>
                    <select id="b-status" name="status" class="input input-sm">
                        <option value="">All bills</option>
                        @foreach ($statusFilters as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-full sm:w-36">
                    <label for="b-from" class="label">Billed from</label>
                    <input id="b-from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="input input-sm">
                </div>
                <div class="w-full sm:w-36">
                    <label for="b-to" class="label">Billed to</label>
                    <input id="b-to" name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="input input-sm">
                </div>
                <x-sort-controls :options="\App\Services\BillQuery::SORT_LABELS" default="issued" default-dir="desc" id-prefix="b-sort" class="w-full sm:w-72" />
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm h-[38px] px-4"><x-icon name="filter" /> Apply</button>
                    @if ($active)
                        <a href="{{ route('billing.index') }}" class="btn btn-ghost btn-sm h-[38px]">Reset</a>
                    @endif
                </div>
            </div>
        </form>
        <div id="bill-results">@include('billing.partials.table', ['bills' => $bills])</div>
    </div>
</x-app-layout>
