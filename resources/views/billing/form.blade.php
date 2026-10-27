@php
    $editing = $bill->exists;
    $currency = config('lab.currency');
    $dt = fn ($date) => $date?->format('Y-m-d\TH:i');
    $oldItems = old('items', []);
    $priceState = collect($prices)->map(fn ($price, $id) => (string) ($oldItems[$id] ?? ($price === null ? '' : number_format((float) $price, 2, '.', ''))))->all();
    $paidCents = (int) round(((float) $bill->amount_paid) * 100);
@endphp
<x-app-layout :title="$editing ? 'Edit '.$bill->bill_number : 'New bill'"
              :breadcrumbs="$editing ? ['Billing' => route('billing.index'), $bill->bill_number => route('billing.show', $bill), 'Edit' => null] : ['Billing' => route('billing.index'), 'New bill' => route('billing.create'), $report->report_number => null]">
    <form method="POST" action="{{ $editing ? route('billing.update', $bill) : route('billing.store') }}"
          x-data="billForm(@js($priceState), @js(old('discount_type', $bill->discount_type ?? 'amount')), @js((string) old('discount_value', $bill->discount_value ? (float) $bill->discount_value : '')), {{ $paidCents }})"
          class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]" novalidate>
        @csrf
        @if ($editing)
            @method('PUT')
            <input type="hidden" name="lock_version" value="{{ $bill->lock_version }}">
        @else
            <input type="hidden" name="form_token" value="{{ old('form_token', $formToken) }}">
            <input type="hidden" name="report_id" value="{{ $report->id }}">
        @endif

        <div class="space-y-6">
            <section class="card" aria-labelledby="lines-title">
                <div class="card-header">
                    <div>
                        <h2 id="lines-title" class="card-title">Tests</h2>
                        <p class="text-xs text-slate-500">Prices start from each test's listed price. Change a price here to charge a different amount on this bill only.</p>
                    </div>
                </div>
                <div class="relative overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">Test</th>
                                <th scope="col" class="hidden sm:table-cell">Listed price</th>
                                <th scope="col" class="w-44 text-right">Amount ({{ $currency }})</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($report->items as $item)
                                @php $key = 'items.'.$item->id; @endphp
                                <tr>
                                    <td>
                                        <label for="price-{{ $item->id }}" class="font-medium text-slate-900">{{ $item->test_name }}</label>
                                        <span class="block font-mono text-[11px] text-slate-400">{{ $item->test_code }}</span>
                                    </td>
                                    <td class="hidden whitespace-nowrap text-slate-500 tabular-nums sm:table-cell">{{ $item->price !== null ? $currency.number_format((float) $item->price, 2) : 'Not set' }}</td>
                                    <td class="text-right">
                                        <input id="price-{{ $item->id }}" name="items[{{ $item->id }}]" type="text" inputmode="decimal" autocomplete="off"
                                               x-model="prices['{{ $item->id }}']" placeholder="0.00"
                                               @class(['input input-sm text-right font-mono tabular-nums', 'input-error' => $errors->has($key)])
                                               @if ($errors->has($key)) aria-invalid="true" @endif>
                                        @error($key)<p class="field-error text-left">{{ $message }}</p>@enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card" aria-labelledby="details-title">
                <div class="card-header"><h2 id="details-title" class="card-title">Bill details</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="issued_at" class="label">Bill date <span class="text-red-500">*</span></label>
                        <input id="issued_at" name="issued_at" type="datetime-local" value="{{ old('issued_at', $dt($bill->issued_at)) }}" required @class(['input', 'input-error' => $errors->has('issued_at')])>
                        @error('issued_at')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <span class="label">Discount</span>
                        <div class="flex gap-2">
                            <label for="discount_type" class="sr-only">Discount type</label>
                            <select id="discount_type" name="discount_type" x-model="discountType" class="input w-36">
                                <option value="amount">{{ $currency }} Amount</option>
                                <option value="percent">% Percent</option>
                            </select>
                            <label for="discount_value" class="sr-only">Discount value</label>
                            <input id="discount_value" name="discount_value" type="text" inputmode="decimal" autocomplete="off" x-model="discountValue" placeholder="0"
                                   :class="discountError && 'input-error'"
                                   @class(['input min-w-0 flex-1 text-right font-mono tabular-nums', 'input-error' => $errors->has('discount_value')])>
                        </div>
                        <p class="field-error" x-cloak x-show="discountError" x-text="discountError"></p>
                        @error('discount_value')<p class="field-error" x-show="!discountError">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="notes" class="label">Notes</label>
                        <input id="notes" name="notes" type="text" maxlength="500" value="{{ old('notes', $bill->notes) }}" placeholder="Optional, printed on the receipt" @class(['input', 'input-error' => $errors->has('notes')])>
                        @error('notes')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            @unless ($editing)
                <section class="card" aria-labelledby="payment-title">
                    <div class="card-header">
                        <div>
                            <h2 id="payment-title" class="card-title">Payment received now</h2>
                            <p class="text-xs text-slate-500">Optional. Leave empty if the patient will pay later; you can record payments on the bill at any time.</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary" @click="received = fmt(total)" :disabled="total <= 0">Paid in full</button>
                    </div>
                    <div class="card-body grid gap-4 sm:grid-cols-3">
                        <div>
                            <label for="payment_amount" class="label">Amount ({{ $currency }})</label>
                            <input id="payment_amount" name="payment_amount" type="text" inputmode="decimal" autocomplete="off" x-model="received" placeholder="0.00"
                                   :class="receivedError && 'input-error'"
                                   @class(['input text-right font-mono tabular-nums', 'input-error' => $errors->has('payment_amount')])>
                            <p class="field-error" x-cloak x-show="receivedError" x-text="receivedError"></p>
                            @error('payment_amount')<p class="field-error" x-show="!receivedError">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="payment_method" class="label">Method</label>
                            <select id="payment_method" name="payment_method" @class(['input', 'input-error' => $errors->has('payment_method')])>
                                @foreach (\App\Models\BillPayment::METHODS as $value => $label)
                                    <option value="{{ $value }}" @selected(old('payment_method', 'cash') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('payment_method')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="payment_reference" class="label">Reference</label>
                            <input id="payment_reference" name="payment_reference" type="text" maxlength="100" value="{{ old('payment_reference') }}" placeholder="UPI / card txn no." class="input">
                        </div>
                    </div>
                </section>
            @endunless
        </div>

        <aside class="space-y-6">
            <section class="card xl:sticky xl:top-24" aria-labelledby="total-title">
                <div class="card-header"><h2 id="total-title" class="card-title">Total</h2></div>
                <dl class="card-body space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Patient</dt><dd class="font-medium text-slate-900">{{ $report->patient_name }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Report</dt><dd class="font-mono text-xs">{{ $report->report_number }}</dd></div>
                    <div class="flex justify-between border-t border-slate-100 pt-2"><dt class="text-slate-500">Subtotal</dt><dd class="tabular-nums">{{ $currency }}<span x-text="fmt(subtotal)"></span></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Discount</dt><dd class="tabular-nums">− {{ $currency }}<span x-text="fmt(discount)"></span></dd></div>
                    <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-bold text-navy-900"><dt>Total</dt><dd class="tabular-nums">{{ $currency }}<span x-text="fmt(total)"></span></dd></div>
                    @if ($editing)
                        <div class="flex justify-between"><dt class="text-slate-500">Already paid</dt><dd class="tabular-nums">{{ $currency }}<span x-text="fmt(paid)"></span></dd></div>
                    @endif
                    <div class="flex justify-between font-semibold"><dt>Balance due</dt><dd class="tabular-nums" :class="balance > 0 ? 'text-red-700' : 'text-brand-700'">{{ $currency }}<span x-text="fmt(balance)"></span></dd></div>
                </dl>
                <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 px-5 py-4">
                    <a href="{{ $editing ? route('billing.show', $bill) : route('billing.create') }}" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-accent" :disabled="!!discountError || !!receivedError"><x-icon name="check" /> {{ $editing ? 'Save bill' : 'Create bill' }}</button>
                </div>
            </section>
        </aside>
    </form>
</x-app-layout>
