<?php

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Models\Bill;
use App\Models\Patient;
use App\Models\Report;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Permanently removes the synthetic demo patients and reports (is_demo = 1).
 * Genuine operational records are never touched.
 */
class PurgeDemoData extends Command
{
    protected $signature = 'lab:purge-demo-data {--force : Do not ask for confirmation}';

    protected $description = 'Permanently delete synthetic demo patients and reports';

    public function handle(): int
    {
        $reports = Report::withTrashed()->where('is_demo', true)->count();
        $patients = Patient::withTrashed()->where('is_demo', true)->count();

        if ($reports === 0 && $patients === 0) {
            $this->info('No demo data found.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Delete {$reports} demo report(s) and {$patients} demo patient(s)?")) {
            return self::FAILURE;
        }

        DB::transaction(function () {
            $reportIds = Report::withTrashed()->where('is_demo', true)->pluck('id');
            $patientIds = Patient::withTrashed()->where('is_demo', true)->pluck('id');

            AuditEvent::query()->where('subject_type', 'Report')->whereIn('subject_id', $reportIds)->delete();
            AuditEvent::query()->where('subject_type', 'Patient')->whereIn('subject_id', $patientIds)->delete();
            AuditEvent::query()->where('subject_type', 'Bill')
                ->whereIn('subject_id', Bill::query()->whereIn('report_id', $reportIds)->pluck('id'))->delete();

            // Items, revisions and bills cascade with their report.
            Report::withTrashed()->whereIn('id', $reportIds)->forceDelete();

            Patient::withTrashed()
                ->whereIn('id', $patientIds)
                ->whereDoesntHave('reports', fn ($q) => $q->withTrashed())
                ->forceDelete();
        });

        $this->info("Removed {$reports} demo report(s) and {$patients} demo patient(s).");

        return self::SUCCESS;
    }
}
