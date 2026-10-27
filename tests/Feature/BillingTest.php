<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private function billableReport(): Report
    {
        $this->actingAsAdmin();

        return $this->draftReport($this->definition(['price' => 500]))->load('items');
    }

    private function createBill(Report $report, array $overrides = []): Bill
    {
        $this->post(route('billing.store'), array_merge([
            'form_token' => (string) Str::uuid(),
            'report_id' => $report->id,
            'issued_at' => now()->format('Y-m-d\TH:i'),
            'discount_type' => 'amount',
            'discount_value' => '',
        ], $overrides))->assertRedirect()->assertSessionHasNoErrors();

        return Bill::query()->latest('id')->firstOrFail();
    }

    public function test_billing_pages_require_the_administrator(): void
    {
        $this->get(route('billing.index'))->assertRedirect(route('login'));
    }

    public function test_bill_is_priced_from_the_report_and_discounted(): void
    {
        $report = $this->billableReport();
        $item = $report->items->first();

        $bill = $this->createBill($report, [
            'items' => [$item->id => '450.50'],
            'discount_type' => 'percent',
            'discount_value' => '10',
            'payment_amount' => '100',
            'payment_method' => 'upi',
        ]);

        $this->assertSame('450.50', $bill->subtotal);
        $this->assertSame('45.05', $bill->discount_amount);
        $this->assertSame('405.45', $bill->total);
        $this->assertSame('100.00', $bill->amount_paid);
        $this->assertSame('305.45', $bill->balance);
        $this->assertSame(Bill::STATUS_PARTIAL, $bill->status);
        $this->assertMatchesRegularExpression('/^BILL-\d{4}-\d{5}$/', $bill->bill_number);

        $this->get(route('billing.index'))->assertOk()->assertSee($bill->bill_number);
        $this->get(route('billing.show', $bill))->assertOk()->assertSee('Record payment');
        $this->get(route('reports.show', $report))->assertOk()->assertSee($bill->bill_number);
    }

    public function test_listed_price_is_used_when_no_amount_is_given(): void
    {
        $bill = $this->createBill($this->billableReport());

        $this->assertSame('500.00', $bill->total);
        $this->assertSame(Bill::STATUS_UNPAID, $bill->status);
    }

    public function test_payments_settle_the_bill_and_cannot_exceed_the_balance(): void
    {
        $bill = $this->createBill($this->billableReport());

        $this->post(route('billing.payments.store', $bill), ['amount' => '600', 'method' => 'cash', 'paid_at' => now()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors('amount');

        $this->post(route('billing.payments.store', $bill), ['amount' => '500', 'method' => 'cash', 'paid_at' => now()->format('Y-m-d\TH:i')])
            ->assertSessionHasNoErrors();

        $bill->refresh();
        $this->assertSame(Bill::STATUS_PAID, $bill->status);
        $this->assertSame('0.00', $bill->balance);

        $payment = $bill->payments()->first();
        $this->delete(route('billing.payments.destroy', [$bill, $payment]))->assertSessionHasNoErrors();
        $this->assertSame(Bill::STATUS_UNPAID, $bill->refresh()->status);
    }

    public function test_one_active_bill_per_report_and_duplicate_submissions_are_ignored(): void
    {
        $report = $this->billableReport();
        $token = (string) Str::uuid();

        $this->createBill($report, ['form_token' => $token]);
        $this->createBill($report, ['form_token' => $token]);
        $this->assertSame(1, Bill::query()->count());

        $this->post(route('billing.store'), [
            'form_token' => (string) Str::uuid(),
            'report_id' => $report->id,
            'issued_at' => now()->format('Y-m-d\TH:i'),
            'discount_type' => 'amount',
        ])->assertSessionHasErrors('report');
    }

    public function test_discount_cannot_exceed_subtotal_and_total_cannot_drop_below_paid(): void
    {
        $report = $this->billableReport();

        $this->post(route('billing.store'), [
            'report_id' => $report->id,
            'issued_at' => now()->format('Y-m-d\TH:i'),
            'discount_type' => 'amount',
            'discount_value' => '501',
        ])->assertSessionHasErrors('discount_value');

        $bill = $this->createBill($report, ['payment_amount' => '400', 'payment_method' => 'cash']);

        $this->put(route('billing.update', $bill), [
            'lock_version' => $bill->lock_version,
            'issued_at' => now()->format('Y-m-d\TH:i'),
            'discount_type' => 'amount',
            'discount_value' => '200',
        ])->assertSessionHasErrors('discount_value');

        $this->assertSame('500.00', $bill->refresh()->total);
    }

    public function test_cancelling_requires_no_payments_and_frees_the_report(): void
    {
        $report = $this->billableReport();
        $bill = $this->createBill($report, ['payment_amount' => '50', 'payment_method' => 'cash']);

        $this->post(route('billing.cancel', $bill), ['reason' => 'Entered twice'])->assertSessionHasErrors('reason');

        $bill->payments()->first()->delete();
        $bill->forceFill(['amount_paid' => 0, 'balance' => $bill->total, 'status' => Bill::STATUS_UNPAID])->save();

        $this->post(route('billing.cancel', $bill), ['reason' => 'Entered twice'])->assertSessionHasNoErrors();
        $this->assertSame(Bill::STATUS_CANCELLED, $bill->refresh()->status);

        $this->createBill($report);
        $this->assertSame(2, Bill::query()->where('report_id', $report->id)->count());
    }

    public function test_billed_draft_cannot_be_discarded(): void
    {
        $report = $this->billableReport();
        $this->createBill($report);

        $this->delete(route('reports.destroy', $report))->assertSessionHasErrors('report');
        $this->assertNotSoftDeleted($report);
    }

    public function test_receipt_renders_as_html_and_pdf_without_results(): void
    {
        $bill = $this->createBill($this->billableReport());

        $this->get(route('billing.receipt', $bill))->assertOk()->assertSee('BILL / RECEIPT')->assertSee($bill->bill_number);

        $response = $this->get(route('billing.receipt', [$bill, 'format' => 'pdf']));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_bill_list_search_and_status_filter(): void
    {
        $bill = $this->createBill($this->billableReport());

        $this->get(route('billing.index', ['q' => $bill->bill_number]))->assertOk()->assertSee($bill->bill_number);
        $this->get(route('billing.index', ['status' => 'paid']))->assertOk()->assertDontSee($bill->bill_number);
        $this->get(route('billing.index', ['status' => 'due']))->assertOk()->assertSee($bill->bill_number);
        $this->get(route('billing.create'))->assertOk();
    }
}
