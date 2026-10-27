<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientRequest;
use App\Models\Patient;
use App\Models\Report;
use App\Services\PatientService;
use App\Services\ReportQuery;
use App\Support\Age;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatientController extends Controller
{
    private const SORTABLE = ['name' => 'name', 'code' => 'patient_code', 'created' => 'created_at'];

    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'sort', 'dir']);
        $sort = self::SORTABLE[$filters['sort'] ?? ''] ?? 'created_at';
        $direction = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $patients = Patient::query()
            ->search($filters['q'] ?? null)
            ->withCount('reports')
            ->withMax('reports', 'registered_at')
            ->orderBy($sort, $direction)
            ->orderBy('id', $direction)
            ->paginate(15)
            ->withQueryString();

        return view('patients.index', compact('patients', 'filters'));
    }

    /**
     * Lightweight lookup used by the report form's patient picker.
     */
    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));
        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $patients = Patient::query()
            ->select(['id', 'patient_code', 'name', 'age_value', 'age_unit', 'sex', 'phone', 'date_of_birth'])
            ->addSelect(['last_report' => Report::query()
                ->select('report_number')
                ->whereColumn('reports.patient_id', 'lab_patients.id')
                ->orderByDesc('registered_at')
                ->limit(1),
            ])
            ->search($term)
            ->orderBy('name')
            ->limit(8)
            ->get();

        return response()->json([
            'data' => $patients->map(fn (Patient $p) => [
                'id' => $p->id,
                'code' => $p->patient_code,
                'name' => $p->name,
                'age_value' => $p->age_value,
                'age_unit' => $p->age_unit,
                'sex' => $p->sex,
                'phone' => $p->phone,
                'summary' => trim($p->age_label.' · '.$p->sex_label.($p->phone ? ' · '.$p->phone : '')),
                'last_report' => $p->last_report,
            ]),
        ]);
    }

    public function show(Request $request, Patient $patient, ReportQuery $query): View
    {
        return view('patients.show', [
            'patient' => $patient,
            'reports' => $query->paginate(['patient' => $patient->id] + $request->only(['sort', 'dir']), 10),
        ]);
    }

    public function edit(Patient $patient): View
    {
        return view('patients.edit', [
            'patient' => $patient,
            'sexes' => Patient::SEXES,
            'ageUnits' => Age::UNITS,
        ]);
    }

    public function update(PatientRequest $request, Patient $patient, PatientService $service): RedirectResponse
    {
        $service->update($patient, $request->validated());

        return redirect()->route('patients.show', $patient)
            ->with('success', 'Patient details updated. Existing reports keep the demographics they were issued with.');
    }
}
