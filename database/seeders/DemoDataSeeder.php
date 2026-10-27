<?php

namespace Database\Seeders;

use App\Models\Report;
use App\Models\TestDefinition;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Synthetic demonstration data. Every patient and report created here is
 * flagged is_demo = true and uses invented names — no real patient data from
 * the reference PDF/workbook is ever seeded. Safe to run repeatedly: it does
 * nothing if demo reports already exist.
 */
class DemoDataSeeder extends Seeder
{
    private const PATIENTS = [
        ['Demo · Asha Verma', 34, 'years', 'female'],
        ['Demo · Rohan Mehta', 41, 'years', 'male'],
        ['Demo · Kavya Nair', 27, 'years', 'female'],
        ['Demo · Imran Shaikh', 58, 'years', 'male'],
        ['Demo · Meera Joshi', 63, 'years', 'female'],
        ['Demo · Arjun Patel', 8, 'years', 'male'],
        ['Demo · Baby of Sunita', 4, 'days', 'male'],
        ['Demo · Farah Khan', 45, 'years', 'female'],
        ['Demo · Vikram Rao', 36, 'years', 'male'],
        ['Demo · Neha Gupta', 22, 'years', 'female'],
    ];

    private const PANELS = [
        ['CBC', 'CRP'],
        ['GLU-FPP'],
        ['LFT'],
        ['RFT', 'ELECTRO'],
        ['CBC', 'WIDAL', 'MP-AG'],
        ['URINE-RM'],
        ['HBA1C'],
        ['BIL'],
        ['CBC', 'BGRP', 'GLU-RBS', 'HBSAG', 'HIV', 'VDRL'],
        ['DENGUE', 'PLT'],
        ['UA', 'CA', 'RA'],
        ['TROP-I'],
    ];

    public function run(ReportService $reports): void
    {
        if (Report::query()->where('is_demo', true)->exists()) {
            $this->command?->info('Demo reports already present — skipping.');

            return;
        }

        $admin = User::query()->where('is_lab_admin', true)->first();
        $definitions = TestDefinition::query()->active()->get()->keyBy('code');
        mt_srand(2610);

        $count = 0;
        $patientIds = [];
        for ($i = 0; $i < 28; $i++) {
            [$name, $age, $unit, $sex] = self::PATIENTS[$i % count(self::PATIENTS)];
            $codes = array_values(array_filter(self::PANELS[$i % count(self::PANELS)], fn ($code) => $definitions->has($code)));
            if (! $codes) {
                continue;
            }

            $registered = Carbon::now()->startOfDay()->subDays(mt_rand(0, 13))->addHours(mt_rand(8, 18))->addMinutes(mt_rand(0, 59));
            if ($registered->isFuture()) {
                $registered = Carbon::now()->subMinutes(mt_rand(5, 90));
            }

            $report = $reports->createDraft([
                // Reuse the same demo patient for repeat visits.
                'patient_id' => $patientIds[$name] ?? null,
                'patient_name' => $name,
                'age_value' => $age,
                'age_unit' => $unit,
                'sex' => $sex,
                'referring_doctor' => 'Demo Referrer',
                'collected_by' => 'Front desk',
                'registered_at' => $registered,
                'collected_at' => $registered->copy(),
                'test_ids' => collect($codes)->map(fn ($code) => $definitions[$code]->id)->all(),
            ], $admin, isDemo: true);

            $patientIds[$name] = $report->patient_id;

            $results = [];
            foreach ($report->items as $item) {
                $results[$item->id] = $this->fakeResults($item->schema_snapshot);
            }

            $report = $reports->updateDraft($report, [
                'patient_name' => $name,
                'age_value' => $age,
                'age_unit' => $unit,
                'sex' => $sex,
                'lock_version' => $report->lock_version,
            ], $results, $admin);

            // Leave roughly a quarter of the reports as drafts.
            if ($i % 4 !== 1) {
                $report = $reports->finalize($report, $admin, $report->lock_version);
                $released = $registered->copy()->addHours(mt_rand(2, 6));
                if ($released->isFuture()) {
                    $released = Carbon::now();
                }
                $report->forceFill(['reported_at' => $released, 'finalized_at' => $released])->save();
            }

            $count++;
        }

        $this->command?->info("Demo data: {$count} synthetic reports created.");
    }

    private function fakeResults(array $schema): array
    {
        $values = [];

        foreach ($schema['sections'] ?? [] as $section) {
            foreach ($section['parameters'] ?? [] as $p) {
                if (! empty($p['formula'])) {
                    continue;
                }

                $values[$p['key']] = match ($p['type']) {
                    'numeric', 'decimal', 'integer' => $this->number($p),
                    'qualitative', 'select' => $p['options'][0] ?? null,
                    'multiselect' => [$p['options'][0] ?? null],
                    'boolean' => 'no',
                    'textarea' => 'Within normal limits (demo data).',
                    'table' => $this->table($p),
                    default => $this->example($p),
                };
            }
        }

        return $values;
    }

    private function number(array $p): string
    {
        $range = $p['reference_ranges'][0] ?? null;
        $precision = $p['precision'] ?? 1;

        if ($range && $range['min'] !== null && $range['max'] !== null) {
            $span = $range['max'] - $range['min'];
            // Mostly normal, occasionally just outside the interval.
            $low = $range['min'] - ($span * 0.15);
            $high = $range['max'] + ($span * 0.2);
            $value = $low + (mt_rand() / mt_getrandmax()) * ($high - $low);
            $value = max(0, $value);
        } elseif ($range && $range['max'] !== null) {
            $value = (mt_rand() / mt_getrandmax()) * $range['max'] * 1.1;
        } elseif (($p['unit'] ?? null) === '%') {
            $value = mt_rand(45, 95) / 10;
        } else {
            $value = mt_rand(10, 300) / 10;
        }

        return number_format($value, $precision, '.', '');
    }

    private function example(array $p): ?string
    {
        if (! empty($p['help']) && preg_match('/e\.g\.\s*(.+)$/i', $p['help'], $m)) {
            return trim(explode(' or ', $m[1])[0], ' .');
        }

        return 'Nil';
    }

    private function table(array $p): array
    {
        $rows = [];
        foreach (array_slice($p['rows'] ?? [], 0, 6) as $row) {
            $values = [];
            foreach ($p['columns'] ?? [] as $column) {
                $values[$column['key']] = $column['type'] === 'select' ? ($column['options'][0] ?? '') : '';
            }
            $rows[$row['key']] = $values;
        }

        return ['rows' => $rows];
    }
}
