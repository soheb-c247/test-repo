@php
    $currency = config('lab.currency');
@endphp
<x-app-layout title="New bill" :breadcrumbs="['Billing' => route('billing.index'), 'New bill' => null]"
              subtitle="Choose the report to bill. Reports that already have a bill are not listed.">
    <div class="card">
        <form method="GET" action="{{ route('billing.create') }}" role="search" x-data="liveFilters" data-target="#unbilled-results"
              class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-4">
            <div class="w-full min-w-[14rem] sm:flex-1">
                <label for="u-q" class="label">Search</label>
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input id="u-q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" class="input input-sm pl-9 pr-24"
                           placeholder="Report no., patient name, patient ID or phone" maxlength="100" autocomplete="off" autofocus
                           @input="queue()" @keydown.enter.prevent="search()">
                    <span x-cloak x-show="loading" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">Searching…</span>
                </div>
            </div>
        </form>

        <div id="unbilled-results">
            @if ($reports->isEmpty())
                <x-empty-state icon="receipt" title="{{ filled($filters['q'] ?? null) ? 'No unbilled report matches' : 'Every report is billed' }}"
                               message="Bills are created from reports. Create a report first, or open an existing bill from Billing." />
            @else
                <div class="relative overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">Report no.</th>
                                <th scope="col">Patient</th>
                                <th scope="col" class="hidden md:table-cell">Tests</th>
                                <th scope="col" class="hidden lg:table-cell">Registered</th>
                                <th scope="col" class="text-right">Listed price</th>
                                <th scope="col" class="text-right"><span class="sr-only">Action</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($reports as $report)
                                @php $names = $report->items->pluck('test_name'); @endphp
                                <tr>
                                    <td class="whitespace-nowrap font-mono text-xs font-semibold text-navy-800">{{ $report->report_number }}</td>
                                    <td class="min-w-[9rem]">
                                        <p class="font-medium text-slate-900">{{ $report->patient_name }}</p>
                                        <p class="text-xs text-slate-500">{{ $report->patient_age_label }} · {{ $report->patient_sex_label }}</p>
                                    </td>
                                    <td class="hidden max-w-[16rem] md:table-cell">
                                        <p class="truncate text-slate-700" title="{{ $names->implode(', ') }}">{{ $names->first() }}</p>
                                        @if ($names->count() > 1)<p class="text-xs text-slate-500">+{{ $names->count() - 1 }} more</p>@endif
                                    </td>
                                    <td class="hidden whitespace-nowrap text-xs text-slate-600 lg:table-cell">{{ $report->registered_at?->format('d M Y, H:i') }}</td>
                                    <td class="whitespace-nowrap text-right tabular-nums">{{ $currency }}{{ number_format((float) $report->items->sum('price'), 2) }}</td>
                                    <td class="whitespace-nowrap text-right">
                                        <a href="{{ route('billing.create', ['report' => $report->id]) }}" class="btn btn-sm btn-primary">Bill this report</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $reports->links() }}
            @endif
        </div>
    </div>
</x-app-layout>
