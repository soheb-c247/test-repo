@php
    $currency = config('lab.currency');
    $money = fn ($v) => $currency.number_format((float) $v, 2);
@endphp
@if ($bills->isEmpty())
    @if (collect(request()->only(['q', 'status', 'from', 'to', 'patient']))->filter()->isNotEmpty())
        <x-empty-state icon="search" title="No bills match these filters" message="Try a different search term or widen the date range." />
    @else
        <x-empty-state icon="receipt" title="No bills yet" message="Create a bill from a report to start recording payments.">
            <a href="{{ route('billing.create') }}" class="btn btn-accent"><x-icon name="plus" /> New bill</a>
        </x-empty-state>
    @endif
@else
    <div class="relative overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col"><x-sort-link column="bill_number" label="Bill no." default="issued" /></th>
                    <th scope="col"><x-sort-link column="patient" label="Patient" default="issued" /></th>
                    <th scope="col" class="hidden md:table-cell">Report</th>
                    <th scope="col" class="hidden lg:table-cell"><x-sort-link column="issued" label="Date" default="issued" /></th>
                    <th scope="col" class="text-right"><x-sort-link column="total" label="Total" default="issued" /></th>
                    <th scope="col" class="hidden text-right sm:table-cell">Paid</th>
                    <th scope="col" class="text-right"><x-sort-link column="balance" label="Balance" default="issued" /></th>
                    <th scope="col"><x-sort-link column="status" label="Status" default="issued" /></th>
                    <th scope="col" class="text-right"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($bills as $bill)
                    <tr>
                        <td class="whitespace-nowrap">
                            <a href="{{ route('billing.show', $bill) }}" class="font-mono text-xs font-semibold text-navy-800 hover:text-brand-700 hover:underline">{{ $bill->bill_number }}</a>
                        </td>
                        <td class="min-w-[9rem] font-medium text-slate-900">{{ $bill->patient_name }}</td>
                        <td class="hidden whitespace-nowrap md:table-cell">
                            @if ($bill->report)
                                <a href="{{ route('reports.show', $bill->report) }}" class="font-mono text-xs text-slate-600 hover:underline">{{ $bill->report->report_number }}</a>
                            @else — @endif
                        </td>
                        <td class="hidden whitespace-nowrap text-xs text-slate-600 lg:table-cell">{{ $bill->issued_at?->format('d M Y, H:i') }}</td>
                        <td class="whitespace-nowrap text-right tabular-nums">{{ $money($bill->total) }}</td>
                        <td class="hidden whitespace-nowrap text-right tabular-nums text-slate-600 sm:table-cell">{{ $money($bill->amount_paid) }}</td>
                        <td @class(['whitespace-nowrap text-right tabular-nums', 'font-semibold text-red-700' => (float) $bill->balance > 0])>{{ $money($bill->balance) }}</td>
                        <td class="whitespace-nowrap"><x-bill-status :bill="$bill" /></td>
                        <td class="whitespace-nowrap text-right">
                            <div class="inline-flex items-center gap-0.5">
                                <a href="{{ route('billing.show', $bill) }}" class="icon-btn" title="View" aria-label="View {{ $bill->bill_number }}"><x-icon name="eye" /></a>
                                <a href="{{ route('billing.receipt', [$bill, 'format' => 'pdf']) }}" class="icon-btn" title="Download receipt" aria-label="Download receipt for {{ $bill->bill_number }}"><x-icon name="download" /></a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $bills->links() }}
@endif
