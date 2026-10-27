<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Models\Patient;
use App\Models\Report;
use App\Models\ReportRevision;
use App\Models\TestDefinition;
use App\Services\BillingService;
use App\Services\ReportPdfService;
use App\Services\ReportQuery;
use App\Services\ReportRevisionService;
use App\Services\ReportService;
use App\Services\ResultEvaluator;
use App\Support\Age;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request, ReportQuery $query): View
    {
        $filters = $request->only(['q', 'status', 'from', 'to', 'category', 'sort', 'dir']);

        return view('reports.index', [
            'reports' => $query->paginate($filters, 15),
            'filters' => $filters,
            'statusFilters' => ReportQuery::STATUS_FILTERS,
            'categories' => TestDefinition::withTrashed()->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function create(Request $request): View
    {
        $patient = $request->filled('patient') ? Patient::query()->find($request->integer('patient')) : null;

        return view('reports.form', [
            'report' => new Report([
                'registered_at' => now(),
                'collected_at' => now(),
            ]),
            'patient' => $patient,
            'selectedTests' => [],
            ...$this->formData(),
            'formToken' => (string) Str::uuid(),
        ]);
    }

    public function store(ReportRequest $request): RedirectResponse
    {
        $report = $this->reports->createDraft($request->validated(), $request->user());

        return redirect()
            ->route('reports.edit', $report)
            ->withFragment('results')
            ->with('success', "Draft {$report->report_number} created. Enter the results below.");
    }

    public function show(Report $report, ResultEvaluator $evaluator, BillingService $billing): View
    {
        $report->load(['items', 'revisions.author:id,name', 'finalizer:id,name', 'patient']);

        $missing = [];
        if ($report->isDraft()) {
            foreach ($report->items as $item) {
                foreach ($this->reports->missingRequired($item) as $label) {
                    $missing[] = "{$item->test_name}: {$label}";
                }
            }
        }

        return view('reports.show', [
            'report' => $report,
            'missing' => $missing,
            'evaluator' => $evaluator,
            'bill' => $billing->activeBillFor($report),
        ]);
    }

    public function edit(Report $report): View|RedirectResponse
    {
        if (! $report->isDraft()) {
            return redirect()->route('reports.show', $report)
                ->with('error', 'Finalized reports are read-only. Use “Correct report” to issue a revision.');
        }

        $report->load(['items', 'patient']);

        return view('reports.form', [
            'report' => $report,
            'patient' => $report->patient,
            'selectedTests' => $report->items->pluck('test_definition_id')->all(),
            'evaluator' => app(ResultEvaluator::class),
            'ageYears' => Age::inYears($report->patient_age_value, $report->patient_age_unit),
            ...$this->formData($report),
        ]);
    }

    public function update(ReportRequest $request, Report $report): RedirectResponse
    {
        $data = $request->validated();
        $this->reports->updateDraft($report, $data, (array) $request->input('results', []), $request->user());

        if (($data['intent'] ?? 'save') === 'review') {
            return redirect()->route('reports.show', $report)->with('success', 'Draft saved. Review the report before finalizing.');
        }

        $redirect = redirect()->route('reports.edit', $report);
        if (($data['intent'] ?? null) === 'continue') {
            $redirect->withFragment('results');
        }

        return $redirect->with('success', 'Draft saved.');
    }

    public function finalize(Request $request, Report $report): RedirectResponse
    {
        $request->validate(['lock_version' => ['required', 'integer']]);

        try {
            $this->reports->finalize($report, $request->user(), $request->integer('lock_version'));
        } catch (ValidationException $e) {
            return redirect()->route($report->isDraft() ? 'reports.edit' : 'reports.show', $report)
                ->withErrors($e->errors())
                ->with('error', 'The report cannot be finalized yet — complete the highlighted fields.');
        }

        return redirect()->route('reports.show', $report)
            ->with('success', "Report {$report->report_number} finalized and released.");
    }

    public function startCorrection(Request $request, Report $report): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        $this->reports->startCorrection($report, $validated['reason'], $request->user());

        return redirect()->route('reports.edit', $report)
            ->with('success', 'Correction started. The released version has been preserved in the revision history.');
    }

    public function repeat(Request $request, Report $report): RedirectResponse
    {
        $copy = $this->reports->repeat($report, $request->user());

        return redirect()->route('reports.edit', $copy)
            ->with('success', "New draft {$copy->report_number} created for the same patient and tests (results are not copied).");
    }

    public function destroy(Report $report): RedirectResponse
    {
        $this->reports->deleteDraft($report);

        return redirect()->route('reports.index')->with('success', "Draft {$report->report_number} discarded.");
    }

    public function preview(Report $report, ReportPdfService $pdf): Response
    {
        return response($pdf->html($report), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function pdf(Request $request, Report $report, ReportPdfService $pdf): Response
    {
        $disposition = $request->boolean('inline') ? 'inline' : 'attachment';

        return response($pdf->pdf($report), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$pdf->filename($report).'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function revision(Request $request, Report $report, ReportRevision $revision, ReportRevisionService $revisions, ReportPdfService $pdf): Response
    {
        $snapshot = $revisions->hydrate($revision);

        if ($request->query('format') === 'pdf') {
            return response($pdf->pdf($snapshot), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.Str::upper(Str::slug($report->report_number)).'-rev'.$revision->revision.'-superseded.pdf"',
                'Cache-Control' => 'no-store, private',
            ]);
        }

        return response($pdf->html($snapshot), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function formData(?Report $report = null): array
    {
        $selected = $report ? $report->items->pluck('test_definition_id')->filter()->all() : [];

        // Active tests plus any inactive ones already on this draft.
        $tests = TestDefinition::query()
            ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $selected))
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'category', 'sample_type', 'price', 'is_active', 'collection_instructions', 'preparation_instructions']);

        return [
            'tests' => $tests,
            'sexes' => Patient::SEXES,
            'ageUnits' => Age::UNITS,
        ];
    }
}
