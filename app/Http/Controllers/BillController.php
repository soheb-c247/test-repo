<?php

namespace App\Http\Controllers;

use App\Http\Requests\BillPaymentRequest;
use App\Http\Requests\BillRequest;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Report;
use App\Services\BillingService;
use App\Services\BillPdfService;
use App\Services\BillQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BillController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    public function index(Request $request, BillQuery $query): View
    {
        $filters = $request->only(['q', 'status', 'from', 'to', 'sort', 'dir']);

        return view('billing.index', [
            'bills' => $query->paginate($filters, 15),
            'filters' => $filters,
            'statusFilters' => BillQuery::STATUS_FILTERS,
            'summary' => $this->billing->summary(),
        ]);
    }

    /**
     * Without a report: choose an unbilled report. With one: the bill form.
     */
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->filled('report')) {
            return view('billing.pick-report', [
                'reports' => $this->unbilledReports((string) $request->query('q', '')),
                'filters' => $request->only(['q']),
            ]);
        }

        $report = Report::query()->with('items')->findOrFail($request->integer('report'));
        if ($bill = $this->billing->activeBillFor($report)) {
            return redirect()->route('billing.show', $bill)->with('error', "Report {$report->report_number} is already billed.");
        }

        return view('billing.form', [
            'bill' => new Bill(['issued_at' => now(), 'discount_type' => 'amount', 'discount_value' => 0]),
            'report' => $report,
            'prices' => $report->items->mapWithKeys(fn ($item) => [$item->id => $item->price])->all(),
            'formToken' => (string) Str::uuid(),
        ]);
    }

    public function store(BillRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $report = Report::query()->findOrFail($data['report_id']);
        $bill = $this->billing->create($report, $data, $request->user());

        return redirect()->route('billing.show', $bill)->with('success', "Bill {$bill->bill_number} created.");
    }

    public function show(Bill $bill): View
    {
        $bill->load(['items', 'payments.receiver:id,name', 'report.items', 'creator:id,name']);

        // Tests added to or removed from the report after billing.
        $billed = $bill->items->pluck('report_item_id')->filter()->sort()->values()->all();
        $current = $bill->report?->items->pluck('id')->sort()->values()->all() ?? [];

        return view('billing.show', [
            'bill' => $bill,
            'outOfSync' => ! $bill->isCancelled() && $billed !== $current,
            'methods' => BillPayment::METHODS,
            'paymentToken' => (string) Str::uuid(),
        ]);
    }

    public function edit(Bill $bill): View|RedirectResponse
    {
        if ($bill->isCancelled()) {
            return redirect()->route('billing.show', $bill)->with('error', 'Cancelled bills cannot be edited.');
        }

        $bill->load('items');
        $report = $bill->report()->with('items')->firstOrFail();
        $billed = $bill->items->keyBy('report_item_id');

        return view('billing.form', [
            'bill' => $bill,
            'report' => $report,
            'prices' => $report->items->mapWithKeys(fn ($item) => [$item->id => $billed[$item->id]->amount ?? $item->price])->all(),
        ]);
    }

    public function update(BillRequest $request, Bill $bill): RedirectResponse
    {
        $this->billing->update($bill, $request->validated(), $request->user());

        return redirect()->route('billing.show', $bill)->with('success', 'Bill updated.');
    }

    public function cancel(Request $request, Bill $bill): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $this->billing->cancel($bill, $validated['reason']);

        return redirect()->route('billing.show', $bill)->with('success', "Bill {$bill->bill_number} cancelled.");
    }

    public function storePayment(BillPaymentRequest $request, Bill $bill): RedirectResponse
    {
        $payment = $this->billing->recordPayment($bill, $request->validated(), $request->user());

        return redirect()->route('billing.show', $bill)
            ->with('success', config('lab.currency').number_format((float) $payment->amount, 2).' received.');
    }

    public function destroyPayment(Bill $bill, BillPayment $payment): RedirectResponse
    {
        $this->billing->deletePayment($bill, $payment);

        return redirect()->route('billing.show', $bill)->with('success', 'Payment removed.');
    }

    public function receipt(Request $request, Bill $bill, BillPdfService $pdf): Response
    {
        if ($request->query('format') === 'pdf') {
            return response($pdf->pdf($bill), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => ($request->boolean('inline') ? 'inline' : 'attachment').'; filename="'.$pdf->filename($bill).'"',
                'Cache-Control' => 'no-store, private',
            ]);
        }

        return response($pdf->html($bill), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function unbilledReports(string $term)
    {
        $term = trim($term);

        return Report::query()
            ->with('items:id,report_id,test_name,price,position')
            ->whereDoesntHave('bills', fn ($q) => $q->where('status', '!=', Bill::STATUS_CANCELLED))
            ->has('items')
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($q) => $q->where('report_number', 'like', $like)
                    ->orWhere('patient_name', 'like', $like)
                    ->orWhereHas('patient', fn ($p) => $p->where('phone', 'like', $like)->orWhere('patient_code', 'like', $like)));
            })
            ->latest('registered_at')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();
    }
}
