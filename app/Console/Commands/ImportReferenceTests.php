<?php

namespace App\Console\Commands;

use App\Services\ReferenceDataImportService;
use Illuminate\Console\Command;

class ImportReferenceTests extends Command
{
    protected $signature = 'lab:import-reference-tests
                            {--refresh : Update unmodified workbook definitions to the latest catalog (creates new versions)}';

    protected $description = 'Import the reference test catalog derived from the supplied Excel workbook';

    public function handle(ReferenceDataImportService $importer): int
    {
        $summary = $importer->import((bool) $this->option('refresh'));

        $this->info(sprintf(
            'Reference tests — created: %d, updated: %d, unchanged: %d',
            count($summary['created']),
            count($summary['updated']),
            count($summary['skipped'])
        ));

        if ($summary['created']) {
            $this->line('Created: '.implode(', ', $summary['created']));
        }
        if ($summary['updated']) {
            $this->line('Updated: '.implode(', ', $summary['updated']));
        }

        return self::SUCCESS;
    }
}
