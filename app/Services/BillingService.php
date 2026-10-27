<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bills, discounts and payments. All arithmetic is done in integer paise so
 * totals never drift through floating-point rounding.
 */
class BillingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * The bill currently in force for a report (cancelled bills are history).
     */
    public function activeBillFor(Report $report): ?Bill
    {
        return Bill::query()
            ->where('report_id', $report->id)
            ->where('status', '!=', Bill::STATUS_CANCELLED)
            ->latest('id')
            ->first();
    }

    /**
     * @param  array{form_token?:string,items?:array<int|string,mixed>,discount_type?:string,discount_value?:mixed,notes?:?string,issued_at?:mixed,payment_amount?:mixed,payment_method?:?string,payment_reference?:?string}  $data
     *
     * @throws ValidationException
     */
    public function create(Report $report, array $data, ?User $user): Bill
    {
        $token = $data['form_token'] ?? null;
        if ($token && ($existing = Bill::query()->where('form_token', $token)->first())) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($report, $data, $user, $token) {
                $report = Report::query()->lockForUpdate()->findOrFail($report->id);
                $report->load('items');

                if ($this->activeBillFor($report)) {
                    throw ValidationException::withMessages(['report' => "Report {$report->report_number} already has a bill. Edit that bill instead."]);
                }
                if ($report->items->isEmpty()) {
                    throw ValidationException::withMessages(['report' => 'Add at least one test to the report before billing it.']);
                }

                $bill = new Bill([
                    'notes' => $data['notes'] ?? null,
                    'issued_at' => $data['issued_at'] ?? now(),
                ]);
                $bill->forceFill([
                    'form_token' => $token,
                    'report_id' => $report->id,
                    'patient_id' => $report->patient_id,
                    'patient_name' => $report->patient_name,
                    'status' => Bill::STATUS_UNPAID,
                    'lock_version' => 1,
                    'created_by' => $user?->id,
                ]);

                $lines = $this->lines($report, $data['items'] ?? []);
                $this->applyTotals($bill, $lines, $data, 0);
                $bill->save();
                $bill->forceFill(['bill_number' => $this->billNumber($bill)])->save();
                $bill->items()->createMany($lines);

                $paid = $this->cents($data['payment_amount'] ?? null);
                if ($paid > 0) {
                    if ($paid > $this->cents($bill->total)) {
                        throw ValidationException::withMessages(['payment_amount' => 'The amount received cannot be more than the bill total.']);
                    }
                    $bill->payments()->forceCreate([
                        'amount' => $this->money($paid),
                        'method' => $data['payment_method'] ?? 'cash',
                        'reference' => $data['payment_reference'] ?? null,
                        'paid_at' => $bill->issued_at,
                        'received_by' => $user?->id,
                    ]);
                }

                $this->refreshPaid($bill);

                $this->audit->record('bill.created', $bill, [
                    'bill_number' => $bill->bill_number,
                    'report_id' => $report->id,
                    'total' => $bill->total,
                ]);

                return $bill;
            });
        } catch (QueryException $e) {
            // A concurrent duplicate submission won the race on form_token.
            if ($token && ($existing = Bill::query()->where('form_token', $token)->first())) {
                return $existing;
            }
            throw $e;
        }
    }

    /**
     * Re-price the bill. Lines always follow the report's current tests.
     *
     * @throws ValidationException
     */
    public function update(Bill $bill, array $data, ?User $user): Bill
    {
        return DB::transaction(function () use ($bill, $data) {
            $bill = $this->lock($bill, (int) ($data['lock_version'] ?? 0));
            $this->assertOpen($bill);

            $report = $bill->report()->with('items')->firstOrFail();
            if ($report->items->isEmpty()) {
                throw ValidationException::withMessages(['report' => 'The report has no tests left to bill.']);
            }

            $bill->fill(collect($data)->only(['notes', 'issued_at'])->all());
            $lines = $this->lines($report, $data['items'] ?? []);
            $this->applyTotals($bill, $lines, $data, $this->cents($bill->amount_paid));

            $bill->items()->delete();
            $bill->items()->createMany($lines);
            $bill->lock_version++;
            $bill->patient_name = $report->patient_name;
            $bill->save();
            $this->refreshPaid($bill);

            $this->audit->record('bill.updated', $bill, ['bill_number' => $bill->bill_number, 'total' => $bill->total]);

            return $bill;
        });
    }

    /**
     * @throws ValidationException
     */
    public function recordPayment(Bill $bill, array $data, ?User $user): BillPayment
    {
        $token = $data['form_token'] ?? null;
        if ($token && ($existing = BillPayment::query()->where('form_token', $token)->first())) {
            return $existing;
        }

        return DB::transaction(function () use ($bill, $data, $user, $token) {
            $bill = Bill::query()->lockForUpdate()->findOrFail($bill->id);
            $this->assertOpen($bill);

            $amount = $this->cents($data['amount'] ?? null);
            $balance = $this->cents($bill->balance);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Enter an amount greater than zero.']);
            }
            if ($amount > $balance) {
                throw ValidationException::withMessages(['amount' => 'The amount is more than the balance due ('.config('lab.currency').$this->money($balance).').']);
            }

            $payment = new BillPayment([
                'amount' => $this->money($amount),
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(),
                'form_token' => $token,
            ]);
            $payment->forceFill(['received_by' => $user?->id]);
            $bill->payments()->save($payment);

            $this->refreshPaid($bill);
            $this->audit->record('bill.payment_recorded', $bill, [
                'bill_number' => $bill->bill_number,
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
                'method' => $payment->method,
            ]);

            return $payment;
        });
    }

    public function deletePayment(Bill $bill, BillPayment $payment): void
    {
        DB::transaction(function () use ($bill, $payment) {
            $bill = Bill::query()->lockForUpdate()->findOrFail($bill->id);
            $this->assertOpen($bill);

            $payment->delete();
            $this->refreshPaid($bill);

            $this->audit->record('bill.payment_removed', $bill, [
                'bill_number' => $bill->bill_number,
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
            ]);
        });
    }

    /**
     * @throws ValidationException
     */
    public function cancel(Bill $bill, string $reason): void
    {
        DB::transaction(function () use ($bill, $reason) {
            $bill = Bill::query()->lockForUpdate()->findOrFail($bill->id);
            $this->assertOpen($bill);

            if ($this->cents($bill->amount_paid) > 0) {
                throw ValidationException::withMessages(['reason' => 'Remove the recorded payments (after refunding them) before cancelling this bill.']);
            }

            $bill->forceFill([
                'status' => Bill::STATUS_CANCELLED,
                'cancel_reason' => $reason,
                'cancelled_at' => now(),
                'balance' => 0,
            ])->save();

            $this->audit->record('bill.cancelled', $bill, ['bill_number' => $bill->bill_number]);
        });
    }

    /**
     * Headline figures for the billing page.
     *
     * @return array{collected_today:string,collected_month:string,billed_month:string,outstanding:string,outstanding_count:int}
     */
    public function summary(): array
    {
        $today = Carbon::today();
        $month = $today->copy()->startOfMonth();

        $collected = fn (Carbon $from) => (string) BillPayment::query()
            ->where('paid_at', '>=', $from)
            ->whereHas('bill', fn ($q) => $q->where('status', '!=', Bill::STATUS_CANCELLED))
            ->sum('amount');

        $open = Bill::query()->whereIn('status', [Bill::STATUS_UNPAID, Bill::STATUS_PARTIAL]);

        return [
            'collected_today' => $collected($today),
            'collected_month' => $collected($month),
            'billed_month' => (string) Bill::query()->where('status', '!=', Bill::STATUS_CANCELLED)->where('issued_at', '>=', $month)->sum('total'),
            'outstanding' => (string) (clone $open)->sum('balance'),
            'outstanding_count' => (clone $open)->count(),
        ];
    }

    /**
     * One line per report test, priced from the submitted amounts or, when
     * none is given, the price snapshotted on the report.
     *
     * @return list<array<string, mixed>>
     */
    private function lines(Report $report, array $amounts): array
    {
        $lines = [];
        foreach ($report->items->values() as $position => $item) {
            $given = $amounts[$item->id] ?? null;
            $cents = $given === null || $given === '' ? $this->cents($item->price) : $this->cents($given);
            if ($cents < 0) {
                throw ValidationException::withMessages(["items.{$item->id}" => "The price of {$item->test_name} cannot be negative."]);
            }
            $lines[] = [
                'report_item_id' => $item->id,
                'test_code' => $item->test_code,
                'description' => $item->test_name,
                'amount' => $this->money($cents),
                'position' => $position,
            ];
        }

        return $lines;
    }

    /**
     * @throws ValidationException
     */
    private function applyTotals(Bill $bill, array $lines, array $data, int $paidCents): void
    {
        $subtotal = array_sum(array_map(fn ($line) => $this->cents($line['amount']), $lines));
        $type = ($data['discount_type'] ?? 'amount') === 'percent' ? 'percent' : 'amount';
        $value = $this->cents($data['discount_value'] ?? null);

        if ($value < 0) {
            throw ValidationException::withMessages(['discount_value' => 'The discount cannot be negative.']);
        }
        if ($type === 'percent' && $value > 10000) {
            throw ValidationException::withMessages(['discount_value' => 'A percentage discount cannot be more than 100%.']);
        }

        $discount = $type === 'percent' ? intdiv($subtotal * $value + 5000, 10000) : $value;
        if ($discount > $subtotal) {
            throw ValidationException::withMessages(['discount_value' => 'The discount cannot be more than the bill subtotal.']);
        }

        $total = $subtotal - $discount;
        if ($total < $paidCents) {
            throw ValidationException::withMessages(['discount_value' => 'The new total ('.config('lab.currency').$this->money($total).') is less than the amount already paid. Remove a payment first.']);
        }

        $bill->forceFill([
            'subtotal' => $this->money($subtotal),
            'discount_type' => $type,
            'discount_value' => $this->money($value),
            'discount_amount' => $this->money($discount),
            'total' => $this->money($total),
        ]);
    }

    private function refreshPaid(Bill $bill): void
    {
        $paid = $this->cents((string) $bill->payments()->sum('amount'));
        $total = $this->cents($bill->total);
        $balance = max(0, $total - $paid);

        $bill->forceFill([
            'amount_paid' => $this->money($paid),
            'balance' => $this->money($balance),
            'status' => match (true) {
                $bill->isCancelled() => Bill::STATUS_CANCELLED,
                $balance === 0 => Bill::STATUS_PAID,
                $paid > 0 => Bill::STATUS_PARTIAL,
                default => Bill::STATUS_UNPAID,
            },
        ])->save();
    }

    /**
     * @throws ValidationException
     */
    private function lock(Bill $bill, int $expectedVersion): Bill
    {
        $locked = Bill::query()->lockForUpdate()->findOrFail($bill->id);
        if ($expectedVersion !== $locked->lock_version) {
            throw ValidationException::withMessages(['lock_version' => 'This bill was changed in another window. Reload it and apply your changes again.']);
        }

        return $locked;
    }

    /**
     * @throws ValidationException
     */
    private function assertOpen(Bill $bill): void
    {
        if ($bill->isCancelled()) {
            throw ValidationException::withMessages(['bill' => 'Cancelled bills cannot be changed.']);
        }
    }

    private function billNumber(Bill $bill): string
    {
        return sprintf('%s-%s-%05d', config('lab.bill_prefix', 'BILL'), $bill->issued_at->format('ym'), $bill->id);
    }

    public function cents(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (int) round(((float) $value) * 100);
    }

    public function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
