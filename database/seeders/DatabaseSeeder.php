<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Provision the administrator and the workbook-derived test catalog.
     * Demo patients/reports are only created when SEED_DEMO_DATA=true or
     * when DemoDataSeeder is run explicitly.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            TestCatalogSeeder::class,
        ]);

        if (config('lab.seed_demo_data')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
