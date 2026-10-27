<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Report;
use App\Services\TestDefinitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createPayload(int $testId, array $overrides = []): array
    {
        return array_merge([
            'form_token' => (string) Str::uuid(),
            'patient_name' => 'Asha Verma',
            'age_value' => '34',
            'age_unit' => 'years',
            'sex' => 'female',
            'phone' => '98765 43210',
            'referring_doctor' => 'Dr. Example',
            'registered_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'collected_at' => now()->subMinutes(30)->format('Y-m-d\TH:i'),
            'test_ids' => [$testId],
            'intent' => 'continue',
        ], $overrides);
    }

    private function updatePayload(Report $report, array $results, array $overrides = []): array
    {
        $report->refresh()->load('items');

        return array_merge([
            'lock_version' => $report->lock_version,
            'patient_name' => $report->patient_name,
            'age_value' => $report->patient_age_value,
            'age_unit' => $report->patient_age_unit,
            'sex' => $report->patient_sex,
            'registered_at' => $report->registered_at->format('Y-m-d\TH:i'),
            'collected_at' => $report->collected_at?->format('Y-m-d\TH:i'),
            'test_ids' => $report->items->pluck('test_definition_id')->all(),
            'results' => [$report->items->first()->id => $results],
            'intent' => 'save',
        ], $overrides);
    }

    public function test_full_workflow_create_enter_results_finalize_and_download(): void
    {
        $this->actingAsAdmin();
        $test = $this->definition();

        $this->get('/reports/create')->assertOk()->assertSee('Patient &amp; registration', false);

        $response = $this->post('/reports', $this->createPayload($test->id));
        $report = Report::firstOrFail();
        $response->assertRedirect(route('reports.edit', $report).'#results');

        $this->assertMatchesRegularExpression('/^ACP-\d{4}-\d{5}$/', $report->report_number);
        $this->assertTrue($report->isDraft());
        $this->assertSame('PT-'.str_pad((string) $report->patient_id, 6, '0', STR_PAD_LEFT), $report->patient->patient_code);

        $this->get(route('reports.edit', $report))->assertOk()->assertSee('Haemoglobin');

        $this->put(route('reports.update', $report), $this->updatePayload($report, ['hb' => '11.2', 'remark' => 'Abnormal']))
            ->assertSessionHasNoErrors()->assertRedirect();

        $item = $report->items()->first();
        $this->assertSame('11.2', $item->results['hb']);
        $this->assertSame('22.4', $item->results['double_hb']);
        $this->assertSame('low', $item->flags['hb'], 'female interval 12–15.6 applies');
        $this->assertSame(1, $report->fresh()->abnormal_count);

        $this->get(route('reports.preview', $report))->assertOk()->assertSee('DRAFT')->assertSee('11.2');

        $this->post(route('reports.finalize', $report), ['lock_version' => $report->fresh()->lock_version])
            ->assertRedirect(route('reports.show', $report));

        $report->refresh();
        $this->assertTrue($report->isFinal());
        $this->assertNotNull($report->finalized_at);
        $this->assertNotNull($report->reported_at);

        $pdf = $this->get(route('reports.pdf', $report));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertStringContainsString('attachment; filename="'.$report->report_number.'.pdf"', $pdf->headers->get('Content-Disposition'));

        $this->get(route('reports.show', $report))->assertOk()->assertSee('Finalized');
    }

    public function test_double_submission_does_not_create_duplicate_reports(): void
    {
        $this->actingAsAdmin();
        $payload = $this->createPayload($this->definition()->id);

        $this->post('/reports', $payload);
        $this->post('/reports', $payload);

        $this->assertSame(1, Report::count());
        $this->assertSame(1, Patient::count());
    }

    public function test_report_numbers_are_unique(): void
    {
        $this->admin();
        $test = $this->definition();
        $numbers = collect(range(1, 5))->map(fn () => $this->draftReport($test)->report_number);

        $this->assertCount(5, $numbers->unique());
    }

    public function test_required_fields_are_validated_on_create(): void
    {
        $this->actingAsAdmin();

        $this->post('/reports', ['form_token' => (string) Str::uuid()])
            ->assertSessionHasErrors(['patient_name', 'sex', 'registered_at', 'test_ids']);
        $this->post('/reports', $this->createPayload($this->definition()->id, ['registered_at' => now()->addDay()->format('Y-m-d\TH:i')]))
            ->assertSessionHasErrors('registered_at');

        $this->assertSame(0, Report::count());
    }

    public function test_invalid_result_values_are_rejected_and_nothing_is_saved(): void
    {
        $this->actingAsAdmin();
        $this->admin();
        $report = $this->draftReport();

        $this->put(route('reports.update', $report), $this->updatePayload($report, ['hb' => 'twelve', 'remark' => 'Maybe']))
            ->assertSessionHasErrors(["results.{$report->items->first()->id}.hb", "results.{$report->items->first()->id}.remark"]);

        $this->assertNull($report->items()->first()->results['hb']);
    }

    public function test_cannot_finalize_with_missing_required_results(): void
    {
        $this->actingAsAdmin();
        $report = $this->draftReport();

        $this->post(route('reports.finalize', $report), ['lock_version' => $report->lock_version])
            ->assertRedirect(route('reports.edit', $report))
            ->assertSessionHasErrors();

        $this->assertTrue($report->fresh()->isDraft());
    }

    public function test_stale_edits_are_rejected(): void
    {
        $this->actingAsAdmin();
        $report = $this->draftReport();
        $stale = $this->updatePayload($report, ['hb' => '14']);

        $this->put(route('reports.update', $report), $stale)->assertSessionHasNoErrors();
        $this->put(route('reports.update', $report), array_merge($stale, ['results' => [$report->items->first()->id => ['hb' => '9']]]))
            ->assertSessionHasErrors('lock_version');

        $this->assertSame('14', $report->items()->first()->results['hb']);
    }

    public function test_finalized_reports_cannot_be_silently_edited(): void
    {
        $this->actingAsAdmin();
        $report = $this->finalizedReport('14.5');
        $payload = $this->updatePayload($report, ['hb' => '9.9']);

        $this->get(route('reports.edit', $report))->assertRedirect(route('reports.show', $report));
        $this->put(route('reports.update', $report), $payload)->assertSessionHasErrors('report');
        $this->delete(route('reports.destroy', $report))->assertSessionHasErrors('report');

        $this->assertSame('14.5', $report->items()->first()->results['hb']);
        $this->assertTrue($report->fresh()->isFinal());
    }

    public function test_correction_preserves_the_released_version(): void
    {
        $this->actingAsAdmin();
        $report = $this->finalizedReport('14.5');

        $this->post(route('reports.correction', $report), ['reason' => 'x'])->assertSessionHasErrors('reason');

        $this->post(route('reports.correction', $report), ['reason' => 'Transcription error in Hb'])
            ->assertRedirect(route('reports.edit', $report));

        $report->refresh();
        $this->assertTrue($report->isUnderCorrection());
        $this->assertSame(1, $report->revision);
        $revision = $report->revisions()->firstOrFail();
        $this->assertSame(0, $revision->revision);
        $this->assertSame('14.5', $revision->snapshot['items'][0]['results']['hb']);

        $this->put(route('reports.update', $report), $this->updatePayload($report, ['hb' => '13.9']))->assertSessionHasNoErrors();
        $this->post(route('reports.finalize', $report), ['lock_version' => $report->fresh()->lock_version])->assertRedirect(route('reports.show', $report));

        $this->assertTrue($report->fresh()->isFinal());
        $this->assertSame('13.9', $report->items()->first()->results['hb']);
        $this->assertSame('14.5', $report->revisions()->first()->snapshot['items'][0]['results']['hb']);

        // The superseded version stays viewable and downloadable.
        $this->get(route('reports.revision', [$report, $revision]))->assertOk()->assertSee('14.5');
        $this->assertStringStartsWith('%PDF', $this->get(route('reports.revision', [$report, $revision, 'format' => 'pdf']))->getContent());
        $this->get(route('reports.pdf', [$report, 'inline' => 1]))->assertHeader('Content-Disposition', 'inline; filename="'.$report->report_number.'-rev1.pdf"');
    }

    public function test_reports_keep_their_snapshot_when_the_definition_changes(): void
    {
        $this->actingAsAdmin();
        $test = $this->definition();
        $report = $this->finalizedReport('14.5', $test);

        $schema = $test->schema;
        $schema['sections'][0]['parameters'][0]['unit'] = 'mmol/L';
        $schema['sections'][0]['parameters'][0]['label'] = 'Hemoglobin (renamed)';
        $schema['sections'][0]['parameters'][0]['reference_ranges'] = [['min' => 1, 'max' => 2, 'sex' => 'any']];
        app(TestDefinitionService::class)->update($test, ['code' => $test->code, 'name' => $test->name, 'category' => $test->category, 'schema' => $schema]);
        $this->assertSame(2, $test->fresh()->version);

        $item = $report->items()->first();
        $this->assertSame(1, $item->definition_version);
        $this->assertSame('g/dL', $item->schema_snapshot['sections'][0]['parameters'][0]['unit']);

        $this->get(route('reports.preview', $report))->assertOk()
            ->assertSee('g/dL')->assertSee('13.0 - 17.0')->assertDontSee('renamed');

        // A new report uses the latest version.
        $new = $this->draftReport($test->fresh());
        $this->assertSame(2, $new->items->first()->definition_version);
    }

    public function test_drafts_can_be_discarded_and_repeated(): void
    {
        $this->actingAsAdmin();
        $report = $this->draftReport();

        $this->post(route('reports.repeat', $report))->assertRedirect();
        $this->assertSame(2, Report::count());
        $this->assertSame($report->patient_id, Report::latest('id')->first()->patient_id);

        $this->delete(route('reports.destroy', $report))->assertRedirect(route('reports.index'));
        $this->assertSoftDeleted($report);
    }

    public function test_reports_list_search_filter_sort_and_paginate(): void
    {
        $this->actingAsAdmin();
        $test = $this->definition();
        $this->draftReport($test, ['patient_name' => 'Zara Draft']);
        $this->finalizedReport('20', $test, ['patient_name' => 'Yusuf Final', 'sex' => 'male']);
        foreach (range(1, 16) as $i) {
            $this->draftReport($test, ['patient_name' => "Bulk {$i}", 'registered_at' => now()->subDays(20)]);
        }

        $this->get('/reports?q=Zara')->assertOk()->assertSee('Zara Draft')->assertDontSee('Yusuf Final');
        $this->get('/reports?q=Haemoglobin&sort=patient&dir=desc')->assertOk()->assertSeeInOrder(['Zara Draft', 'Yusuf Final']); // search by test name
        $this->get('/reports?status=final')->assertOk()->assertSee('Yusuf Final')->assertDontSee('Zara Draft');
        $this->get('/reports?status=flagged')->assertOk()->assertSee('Yusuf Final');
        $this->get('/reports?from='.now()->subDays(2)->toDateString())->assertOk()->assertDontSee('Bulk 3');
        $this->get('/reports?category=Haematology&sort=patient&dir=asc')->assertOk();
        $this->get('/reports?sort=1;drop&dir=x')->assertOk();
        $this->get('/reports?page=2')->assertOk()->assertSee('Showing');
        $this->get('/reports?q=nothing-matches')->assertOk()->assertSee('No reports match these filters');
    }

    public function test_patient_lookup_and_update(): void
    {
        $this->actingAsAdmin();
        $report = $this->draftReport(null, ['patient_name' => 'Kavya Nair']);

        $this->getJson('/patients/search?q=kav')->assertOk()->assertJsonPath('data.0.name', 'Kavya Nair');
        $this->getJson('/patients/search?q=k')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/patients/search?q='.$report->report_number)->assertOk()
            ->assertJsonPath('data.0.name', 'Kavya Nair')
            ->assertJsonPath('data.0.last_report', $report->report_number);
        $this->getJson('/patients/search?q='.$report->patient->patient_code)->assertOk()->assertJsonPath('data.0.age_unit', 'years');

        $this->get(route('patients.index', ['q' => 'Kavya']))->assertOk()->assertSee('Kavya Nair');
        $this->get(route('patients.show', $report->patient_id))->assertOk()->assertSee($report->report_number);

        $this->put(route('patients.update', $report->patient_id), ['name' => 'Kavya N.', 'sex' => 'female', 'age_value' => 28, 'age_unit' => 'years'])
            ->assertRedirect(route('patients.show', $report->patient_id));

        $this->assertSame('Kavya N.', Patient::find($report->patient_id)->name);
        $this->assertSame('Kavya Nair', $report->fresh()->patient_name, 'Report keeps the name it was issued with');
    }

    private function finalizedReport(string $hb, $test = null, array $overrides = []): Report
    {
        $report = $this->draftReport($test, $overrides);
        $this->put(route('reports.update', $report), $this->updatePayload($report, ['hb' => $hb]))->assertSessionHasNoErrors();
        $this->post(route('reports.finalize', $report), ['lock_version' => $report->fresh()->lock_version])->assertSessionHasNoErrors();

        return $report->fresh();
    }
}
