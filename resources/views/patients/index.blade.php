<x-app-layout title="Patients" :breadcrumbs="['Dashboard' => route('dashboard'), 'Patients' => null]"
              subtitle="Patients are registered automatically when a report is created.">
    <x-slot:actions>
        <a href="{{ route('reports.create') }}" class="btn btn-accent"><x-icon name="plus" /> New report</a>
    </x-slot:actions>

    <div class="card">
        <form method="GET" action="{{ route('patients.index') }}" x-data="liveFilters" data-target="#patient-results"
              class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-5 py-4" role="search">
            <div class="w-full min-w-[14rem] sm:w-auto sm:flex-1">
                <label for="pq" class="label">Search</label>
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input id="pq" name="q" type="search" value="{{ $filters['q'] ?? '' }}" class="input input-sm pl-9 pr-24"
                           placeholder="Name, patient ID, phone or report no." maxlength="100" autocomplete="off"
                           @input="queue()" @keydown.enter.prevent="search()">
                    <span x-cloak x-show="loading" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">Searching…</span>
                </div>
            </div>
            <x-sort-controls :options="['created' => 'Date registered', 'name' => 'Name', 'code' => 'Patient ID']"
                             default="created" default-dir="desc" id-prefix="ps" class="w-full sm:w-72" />
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm h-[38px] px-4"><x-icon name="filter" /> Apply</button>
                @if (! empty($filters['q']) || ! empty($filters['sort']))<a href="{{ route('patients.index') }}" class="btn btn-ghost btn-sm h-[38px]">Reset</a>@endif
            </div>
        </form>

        <div id="patient-results">
        @if ($patients->isEmpty())
            @if (! empty($filters['q']))
                <x-empty-state icon="search" title="No patients match" message="Check the spelling or search by patient ID or phone." />
            @else
                <x-empty-state icon="users" title="No patients yet" message="Patients appear here once their first report is created.">
                    <a href="{{ route('reports.create') }}" class="btn btn-accent"><x-icon name="plus" /> Create report</a>
                </x-empty-state>
            @endif
        @else
            <div class="relative overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th><x-sort-link column="code" label="Patient ID" default="created" /></th>
                            <th><x-sort-link column="name" label="Name" default="created" /></th>
                            <th>Age / Sex</th>
                            <th class="hidden md:table-cell">Phone</th>
                            <th class="text-right">Reports</th>
                            <th class="hidden lg:table-cell">Last visit</th>
                            <th class="text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($patients as $patient)
                            <tr>
                                <td class="whitespace-nowrap font-mono text-xs font-semibold text-navy-800">{{ $patient->patient_code }}</td>
                                <td>
                                    <a href="{{ route('patients.show', $patient) }}" class="font-medium text-slate-900 hover:text-brand-700 hover:underline">{{ $patient->name }}</a>
                                    @if ($patient->is_demo)<span class="badge badge-neutral ml-1">Demo</span>@endif
                                </td>
                                <td class="text-sm text-slate-600">{{ $patient->age_label }} · {{ $patient->sex_label }}</td>
                                <td class="hidden text-sm text-slate-600 md:table-cell">{{ $patient->phone ?: '—' }}</td>
                                <td class="text-right tabular-nums">{{ $patient->reports_count }}</td>
                                <td class="hidden whitespace-nowrap text-xs text-slate-500 lg:table-cell">{{ $patient->reports_max_registered_at ? \Illuminate\Support\Carbon::parse($patient->reports_max_registered_at)->format('d M Y') : '—' }}</td>
                                <td class="whitespace-nowrap text-right">
                                    <a href="{{ route('patients.show', $patient) }}" class="icon-btn" title="View" aria-label="View {{ $patient->name }}"><x-icon name="eye" /></a>
                                    <a href="{{ route('reports.create', ['patient' => $patient->id]) }}" class="icon-btn" title="New report for this patient" aria-label="New report for {{ $patient->name }}"><x-icon name="plus" /></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $patients->links() }}
        @endif
        </div>
    </div>
</x-app-layout>
