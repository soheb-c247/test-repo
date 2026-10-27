@php $fragment = $fragment ?? null; @endphp
@if ($reports->isEmpty())
    @if (collect(request()->only(['q', 'status', 'from', 'to', 'category']))->filter()->isNotEmpty())
        <x-empty-state icon="search" title="No reports match these filters" message="Try a different search term or widen the date range." />
    @else
        <x-empty-state icon="report" title="No reports yet" message="Create the first report to start recording results.">
            <a href="{{ route('reports.create') }}" class="btn btn-accent"><x-icon name="plus" /> Create report</a>
        </x-empty-state>
    @endif
@else
    <div class="relative overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col"><x-sort-link column="report_number" label="Report no." default="created" :fragment="$fragment" /></th>
                    <th scope="col"><x-sort-link column="patient" label="Patient" default="created" :fragment="$fragment" /></th>
                    <th scope="col">Tests</th>
                    <th scope="col" class="hidden md:table-cell"><x-sort-link column="registered" label="Registered" default="created" :fragment="$fragment" /></th>
                    <th scope="col" class="hidden xl:table-cell"><x-sort-link column="collected" label="Collected" default="created" :fragment="$fragment" /></th>
                    <th scope="col" class="hidden lg:table-cell"><x-sort-link column="reported" label="Reported" default="created" :fragment="$fragment" /></th>
                    <th scope="col"><x-sort-link column="status" label="Status" default="created" :fragment="$fragment" /></th>
                    <th scope="col" class="text-right"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($reports as $report)
                    <tr>
                        <td class="whitespace-nowrap">
                            <a href="{{ route('reports.show', $report) }}" class="font-mono text-xs font-semibold text-navy-800 hover:text-brand-700 hover:underline">{{ $report->report_number }}</a>
                            @if ($report->is_demo)<span class="badge badge-neutral ml-1">Demo</span>@endif
                        </td>
                        <td class="min-w-[10rem]">
                            <p class="font-medium text-slate-900">{{ $report->patient_name }}</p>
                            <p class="text-xs text-slate-500">{{ $report->patient_age_label }} · {{ $report->patient_sex_label }}</p>
                        </td>
                        <td class="max-w-[16rem]">
                            @php $names = $report->items->pluck('test_name'); @endphp
                            <p class="truncate text-slate-700" title="{{ $names->implode(', ') }}">{{ $names->first() }}</p>
                            @if ($names->count() > 1)
                                <p class="text-xs text-slate-500">+{{ $names->count() - 1 }} more</p>
                            @endif
                        </td>
                        <td class="hidden whitespace-nowrap text-xs text-slate-600 md:table-cell">{{ $report->registered_at?->format('d M Y, H:i') }}</td>
                        <td class="hidden whitespace-nowrap text-xs text-slate-600 xl:table-cell">{{ $report->collected_at?->format('d M Y, H:i') ?? '—' }}</td>
                        <td class="hidden whitespace-nowrap text-xs text-slate-600 lg:table-cell">{{ $report->isFinal() ? $report->reported_at?->format('d M Y, H:i') : '—' }}</td>
                        <td class="whitespace-nowrap">
                            <x-report-status :report="$report" />
                            @if ($report->abnormal_count > 0)
                                <span class="badge badge-danger ml-1" title="{{ $report->abnormal_count }} result(s) outside the reference interval"><x-icon name="flag" class="h-3 w-3" />{{ $report->abnormal_count }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-right">
                            <div class="inline-flex items-center gap-0.5">
                                <a href="{{ route('reports.show', $report) }}" class="icon-btn" title="View" aria-label="View {{ $report->report_number }}"><x-icon name="eye" /></a>
                                @if ($report->isDraft())
                                    <a href="{{ route('reports.edit', $report) }}" class="icon-btn" title="Edit draft" aria-label="Edit {{ $report->report_number }}"><x-icon name="edit" /></a>
                                @endif
                                <a href="{{ route('reports.pdf', $report) }}" class="icon-btn" title="Download PDF" aria-label="Download PDF of {{ $report->report_number }}"><x-icon name="download" /></a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $reports->links() }}
@endif
