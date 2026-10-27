<?php

namespace Database\Seeders;

use App\Services\ReferenceDataImportService;
use Illuminate\Database\Seeder;

class TestCatalogSeeder extends Seeder
{
    public function run(ReferenceDataImportService $importer): void
    {
        $summary = $importer->import();

        $this->command?->info(sprintf(
            'Test catalog: %d created, %d already present.',
            count($summary['created']),
            count($summary['skipped'])
        ));
    }
}
