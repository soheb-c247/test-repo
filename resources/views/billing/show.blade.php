@php
    $currency = config('lab.currency');
    $money = fn ($v) => $currency.number_format((float) $v, 2);
    $open = ! $bill->isCancelled();
    $due = (float) $bill->balance > 0;
@endphp
<x-app-layout :title="$bill->bill_number" :breadcrumbs="['Billing' => route('billing.index'), $bill->bill_number => null]">
    <x-slot:actions>
        <x-bill-status :bill="$bill" />
        @if ($open)
            <a href="{{ route('billing.edit', $bill) }}" class="btn btn-secondary"><x-icon name="edit" /> Edit</a>
        @endif
        <a href="{{ route('billing.receipt', [$bill, 'format' => 'pdf', 'inline' => 1]) }}" target="_blank" rel="noopener" class="btn btn-secondary"><x-icon name="printer" /> Print</a>
        <a href="{{ route('billing.receipt', [$bill, 'format' => 'pdf']) }}" class="btn btn-secondary"><x-icon name="download" /> Download PDF</a>
    </x-slot:actions>

    @if ($outOfSync)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="flex items-center gap-2"><x-icon name="alert" class="shrink-0" /> The tests on report {{ $bill->report?->report_number }} changed after this bill was made.</p>
            <a href="{{ route('billing.edit', $bill) }}" class="btn btn-sm btn-secondary">Update bill</a>
        </div>
    @endif

    @if ($bill->isCancelled())
        <div class="mb-6 rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm text-slate-700">
            <strong>Cancelled</strong> {{ $bill->cancelled_at?->format('d M Y, H:i') }}{{ $bill->cancel_reason ? ' — '.$bill->cancel_reason : '' }}.
            @if ($bill->report && ! $bill->report->trashed())
                The report can be billed again from <a href="{{ route('billing.create', ['report' => $bill->report_id]) }}" class="font-semibold text-brand-700 hover:underline">New bill</a>.
            @endif
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6">
            <section class="card" aria-labelledby="lines-title">
                <div class="card-header"><h2 id="lines-title" class="card-title">Tests billed</h2></div>
                <div class="relative overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col" class="w-12">#</th>
                                <th scope="col">Test</th>
                                <th scope="col" class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($bill->items as $line)
                                <tr>
                                    <td class="text-slate-500">{{ $loop->iteration }}</td>
                                    <td><span class="font-medium text-slate-900">{{ $line->description }}</span> <span class="font-mono text-[11px] text-slate-400">{{ $line->test_code }}</span></td>
                                    <td class="text-right tabular-nums">{{ $money($line->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <dl class="ml-auto w-full max-w-xs space-y-1.5 border-t border-slate-100 px-5 py-4 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd class="tabular-nums">{{ $money($bill->subtotal) }}</dd></div>
                    @if ((float) $bill->discount_amount > 0)
                        <div class="flex justify-between">
                            <dt class="text-slate-500">Discount{{ $bill->discount_type === 'percent' ? ' ('.rtrim(rtrim(number_format((float) $bill->discount_value, 2), '0'), '.').'%)' : '' }}</dt>
                            <dd class="tabular-nums">− {{ $money($bill->discount_amount) }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between border-t border-slate-100 pt-1.5 text-base font-bold text-navy-900"><dt>Total</dt><dd class="tabular-nums">{{ $money($bill->total) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Paid</dt><dd class="tabular-nums">{{ $money($bill->amount_paid) }}</dd></div>
                    <div class="flex justify-between font-semibold"><dt>Balance due</dt><dd @class(['tabular-nums', 'text-red-700' => $due, 'text-brand-700' => ! $due])>{{ $money($bill->balance) }}</dd></div>
                </dl>
            </section>

            <section class="card" aria-labelledby="payments-title">
                <div class="card-header">
                    <h2 id="payments-title" class="card-title">Payments</h2>
                    <span class="badge badge-neutral">{{ $bill->payments->count() }}</span>
                </div>
                @if ($bill->payments->isEmpty())
                    <p class="card-body text-sm text-slate-500">No payment recorded yet.</p>
                @else
                    <div class="relative overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th scope="col">Date</th>
                                    <th scope="col">Method</th>
                                    <th scope="col" class="hidden sm:table-cell">Reference</th>
                                    <th scope="col" class="hidden md:table-cell">Received by</th>
                                    <th scope="col" class="text-right">Amount</th>
                                    @if ($open)<th scope="col" class="text-right"><span class="sr-only">Actions</span></th>@endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($bill->payments as $payment)
                                    <tr>
                                        <td class="whitespace-nowrap text-xs text-slate-600">{{ $payment->paid_at?->format('d M Y, H:i') }}</td>
                                        <td>{{ $payment->method_label }}</td>
                                        <td class="hidden text-slate-600 sm:table-cell">{{ $payment->reference ?: '—' }}@if ($payment->note)<span class="block text-xs text-slate-400">{{ $payment->note }}</span>@endif</td>
                                        <td class="hidden text-slate-600 md:table-cell">{{ $payment->receiver?->name ?? '—' }}</td>
                                        <td class="whitespace-nowrap text-right font-medium tabular-nums">{{ $money($payment->amount) }}</td>
                                        @if ($open)
                                            <td class="text-right">
                                                <form method="POST" action="{{ route('billing.payments.destroy', [$bill, $payment]) }}"
                                                      data-confirm="Remove the {{ $money($payment->amount) }} payment from {{ $payment->paid_at?->format('d M Y') }}? Use this only for a payment entered by mistake or refunded."
                                                      data-confirm-title="Remove payment" data-confirm-label="Remove" data-confirm-danger="1">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="icon-btn text-slate-400 hover:text-red-600" title="Remove payment" aria-label="Remove payment of {{ $money($payment->amount) }}"><x-icon name="trash" /></button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <aside class="space-y-6">
            @if ($open && $due)
                <section class="card" aria-labelledby="pay-title">
                    <div class="card-header"><h2 id="pay-title" class="card-title">Record payment</h2></div>
                    <form method="POST" action="{{ route('billing.payments.store', $bill) }}" class="card-body space-y-4" novalidate>
                        @csrf
                        <input type="hidden" name="form_token" value="{{ $paymentToken }}">
                        <div>
                            <label for="amount" class="label">Amount ({{ $currency }}) <span class="text-red-500">*</span></label>
                            <input id="amount" name="amount" type="text" inputmode="decimal" autocomplete="off" required
                                   value="{{ old('amount', number_format((float) $bill->balance, 2, '.', '')) }}"
                                   @class(['input text-right font-mono tabular-nums', 'input-error' => $errors->has('amount')])>
                            @error('amount')<p class="field-error">{{ $message }}</p>@else<p class="help">Balance due: {{ $money($bill->balance) }}</p>@enderror
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="method" class="label">Method</label>
                                <select id="method" name="method" @class(['input', 'input-error' => $errors->has('method')])>
                                    @foreach ($methods as $value => $label)
                                        <option value="{{ $value }}" @selected(old('method', 'cash') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="reference" class="label">Reference</label>
                                <input id="reference" name="reference" type="text" maxlength="100" value="{{ old('reference') }}" placeholder="Txn no." class="input">
                            </div>
                        </div>
                        <div>
                            <label for="paid_at" class="label">Received on</label>
                            <input id="paid_at" name="paid_at" type="datetime-local" value="{{ old('paid_at', now()->format('Y-m-d\TH:i')) }}" @class(['input', 'input-error' => $errors->has('paid_at')])>
                            @error('paid_at')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="note" class="label">Note</label>
                            <input id="note" name="note" type="text" maxlength="255" value="{{ old('note') }}" class="input">
                        </div>
                        <button type="submit" class="btn btn-accent w-full justify-center"><x-icon name="wallet" /> Record payment</button>
                    </form>
                </section>
            @endif

            <section class="card" aria-labelledby="summary-title">
                <div class="card-header"><h2 id="summary-title" class="card-title">Summary</h2></div>
                <dl class="card-body grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                    <dt class="text-slate-500">Patient</dt>
                    <dd class="font-medium text-slate-900">
                        @if ($bill->patient)
                            <a href="{{ route('patients.show', $bill->patient) }}" class="hover:underline">{{ $bill->patient_name }}</a>
                        @else
                            {{ $bill->patient_name }}
                        @endif
                    </dd>
                    <dt class="text-slate-500">Report</dt>
                    <dd>
                        @if ($bill->report && ! $bill->report->trashed())
                            <a href="{{ route('reports.show', $bill->report) }}" class="font-mono text-xs hover:underline">{{ $bill->report->report_number }}</a>
                        @else
                            <span class="font-mono text-xs">{{ $bill->report?->report_number ?? '—' }}</span>
                        @endif
                    </dd>
                    <dt class="text-slate-500">Bill date</dt><dd>{{ $bill->issued_at?->format('d M Y, H:i') }}</dd>
                    <dt class="text-slate-500">Created by</dt><dd>{{ $bill->creator?->name ?? '—' }}</dd>
                    @if ($bill->notes)
                        <dt class="text-slate-500">Notes</dt><dd>{{ $bill->notes }}</dd>
                    @endif
                </dl>
            </section>

            @if ($open)
                <section class="card" aria-labelledby="bill-actions-title" x-data="{ cancelling: {{ $errors->has('reason') ? 'true' : 'false' }} }">
                    <div class="card-header"><h2 id="bill-actions-title" class="card-title">More actions</h2></div>
                    <div class="card-body">
                        <button type="button" class="btn btn-danger-ghost w-full justify-start" @click="cancelling = true"><x-icon name="x" /> Cancel bill…</button>
                        @if ((float) $bill->amount_paid > 0)
                            <p class="mt-2 text-xs text-slate-500">A bill with payments can be cancelled only after those payments are removed.</p>
                        @endif
                    </div>

                    <div x-cloak x-show="cancelling" class="fixed inset-0 z-[70] flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="cancel-title" @keydown.escape.window="cancelling = false">
                        <div class="absolute inset-0 bg-navy-950/50" @click="cancelling = false"></div>
                        <form method="POST" action="{{ route('billing.cancel', $bill) }}" class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-pop"
                              x-effect="if (cancelling) $nextTick(() => $refs.reason.focus())">
                            @csrf
                            <h2 id="cancel-title" class="text-base font-semibold text-slate-900">Cancel {{ $bill->bill_number }}</h2>
                            <p class="mt-1 text-sm text-slate-600">The bill stays in the list marked as cancelled, and the report can be billed again.</p>
                            <label for="reason" class="label mt-4">Reason <span class="text-red-500">*</span></label>
                            <textarea id="reason" name="reason" x-ref="reason" rows="3" minlength="3" maxlength="500" required @class(['input', 'input-error' => $errors->has('reason')])>{{ old('reason') }}</textarea>
                            @error('reason')<p class="field-error">{{ $message }}</p>@enderror
                            <div class="mt-5 flex justify-end gap-2">
                                <button type="button" class="btn btn-secondary" @click="cancelling = false">Keep bill</button>
                                <button type="submit" class="btn btn-danger">Cancel bill</button>
                            </div>
                        </form>
                    </div>
                </section>
            @endif
        </aside>
    </div>
</x-app-layout>
