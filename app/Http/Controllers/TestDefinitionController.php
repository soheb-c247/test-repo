<?php

namespace App\Http\Controllers;

use App\Http\Requests\TestDefinitionRequest;
use App\Models\TestDefinition;
use App\Services\ResultEvaluator;
use App\Services\TestDefinitionService;
use App\Support\ParameterTypes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TestDefinitionController extends Controller
{
    private const SORTABLE = [
        'name' => 'name',
        'code' => 'code',
        'category' => 'category',
        'updated' => 'updated_at',
    ];

    public function __construct(private readonly TestDefinitionService $service) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'category', 'status', 'sort', 'dir']);
        $sort = self::SORTABLE[$filters['sort'] ?? ''] ?? 'name';
        $direction = ($filters['dir'] ?? ($sort === 'updated_at' ? 'desc' : 'asc')) === 'desc' ? 'desc' : 'asc';

        $query = TestDefinition::query()->search($filters['q'] ?? null);

        match ($filters['status'] ?? null) {
            'active' => $query->where('is_active', true),
            'inactive' => $query->where('is_active', false),
            'review' => $query->where('needs_review', true),
            'archived' => $query->onlyTrashed(),
            default => null,
        };

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        $tests = $query
            ->withCount('reportItems')
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('tests.index', [
            'tests' => $tests,
            'filters' => $filters,
            'categories' => $this->categories(),
            'counts' => [
                'all' => TestDefinition::count(),
                'active' => TestDefinition::where('is_active', true)->count(),
                'review' => TestDefinition::where('needs_review', true)->count(),
                'archived' => TestDefinition::onlyTrashed()->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('tests.form', [
            'test' => new TestDefinition(['is_active' => true, 'schema' => ['schema_version' => 1, 'sections' => []]]),
            'categories' => $this->categories(),
            'types' => ParameterTypes::TYPES,
            'columnTypes' => ParameterTypes::TABLE_COLUMN_TYPES,
        ]);
    }

    public function store(TestDefinitionRequest $request): RedirectResponse
    {
        $test = $this->service->create($request->validated());

        return redirect()->route('tests.show', $test)->with('success', "Test “{$test->name}” created.");
    }

    public function show(TestDefinition $test): View
    {
        return view('tests.show', [
            'test' => $test,
            'versions' => $test->versions()->with('author:id,name')->limit(15)->get(),
            'usage' => $test->reportItems()->count(),
            'types' => ParameterTypes::TYPES,
            'evaluator' => app(ResultEvaluator::class),
        ]);
    }

    public function edit(TestDefinition $test): View
    {
        return view('tests.form', [
            'test' => $test,
            'categories' => $this->categories(),
            'types' => ParameterTypes::TYPES,
            'columnTypes' => ParameterTypes::TABLE_COLUMN_TYPES,
            'usage' => $test->reportItems()->count(),
        ]);
    }

    public function update(TestDefinitionRequest $request, TestDefinition $test): RedirectResponse
    {
        $before = $test->version;
        $test = $this->service->update($test, $request->validated());

        $message = $test->version > $before
            ? "Saved as version {$test->version}. Existing reports keep the version they were created with."
            : 'Saved. No reportable content changed, so the version is unchanged.';

        return redirect()->route('tests.show', $test)->with('success', $message);
    }

    public function duplicate(TestDefinition $test): RedirectResponse
    {
        $copy = $this->service->duplicate($test);

        return redirect()->route('tests.edit', $copy)
            ->with('success', "Duplicated as {$copy->code}. The copy is inactive until you activate it.");
    }

    public function toggleStatus(TestDefinition $test): RedirectResponse
    {
        $this->service->setActive($test, ! $test->is_active);

        return back()->with('success', $test->is_active ? "{$test->code} activated." : "{$test->code} deactivated — it can no longer be added to new reports.");
    }

    public function markReviewed(TestDefinition $test): RedirectResponse
    {
        $this->service->markReviewed($test);

        return back()->with('success', "{$test->code} marked as reviewed.");
    }

    public function destroy(TestDefinition $test): RedirectResponse
    {
        $this->service->archive($test);

        return redirect()->route('tests.index')
            ->with('success', "{$test->code} archived. Reports that used it are unaffected.");
    }

    public function restore(TestDefinition $test): RedirectResponse
    {
        abort_unless($test->trashed(), 404);
        $this->service->restore($test);

        return redirect()->route('tests.show', $test)->with('success', "{$test->code} restored (inactive). Activate it to use it in new reports.");
    }

    private function categories()
    {
        return TestDefinition::withTrashed()->distinct()->orderBy('category')->pluck('category');
    }
}
