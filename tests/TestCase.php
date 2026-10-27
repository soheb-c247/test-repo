<?php

namespace Tests;

use App\Models\Report;
use App\Models\TestDefinition;
use App\Models\User;
use App\Services\ReportService;
use App\Services\TestDefinitionService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function admin(): User
    {
        return User::factory()->labAdmin()->create();
    }

    protected function actingAsAdmin(): User
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        return $admin;
    }

    /**
     * A small but representative definition: a required numeric parameter
     * with sex-specific intervals, an optional qualitative one, and a
     * calculated one.
     */
    protected function definition(array $overrides = []): TestDefinition
    {
        return app(TestDefinitionService::class)->create(array_merge([
            'code' => 'HB-T',
            'name' => 'Haemoglobin test',
            'category' => 'Haematology',
            'sample_type' => 'Whole blood (EDTA)',
            'is_active' => true,
            'schema' => [
                'schema_version' => 1,
                'sections' => [[
                    'key' => 'main',
                    'title' => 'Hemoglobin',
                    'parameters' => [
                        [
                            'key' => 'hb',
                            'label' => 'Haemoglobin',
                            'type' => 'numeric',
                            'required' => true,
                            'unit' => 'g/dL',
                            'precision' => 1,
                            'reference_ranges' => [
                                ['min' => 13, 'max' => 17, 'sex' => 'male'],
                                ['min' => 12, 'max' => 15.6, 'sex' => 'female'],
                            ],
                        ],
                        [
                            'key' => 'remark',
                            'label' => 'Remark',
                            'type' => 'qualitative',
                            'required' => false,
                            'options' => ['Normal', 'Abnormal'],
                        ],
                        [
                            'key' => 'double_hb',
                            'label' => 'Double Hb',
                            'type' => 'numeric',
                            'formula' => 'hb * 2',
                            'precision' => 1,
                        ],
                    ],
                ]],
            ],
        ], $overrides));
    }

    protected function draftReport(?TestDefinition $definition = null, array $overrides = []): Report
    {
        $definition ??= $this->definition();

        return app(ReportService::class)->createDraft(array_merge([
            'patient_name' => 'Test Patient',
            'age_value' => 40,
            'age_unit' => 'years',
            'sex' => 'male',
            'registered_at' => now()->subHour(),
            'collected_at' => now()->subMinutes(50),
            'test_ids' => [$definition->id],
        ], $overrides), User::query()->where('is_lab_admin', true)->first());
    }
}
