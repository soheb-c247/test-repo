<?php

namespace Tests\Feature;

use App\Models\TestDefinition;
use App\Services\ReferenceDataImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestDefinitionTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'crp-q',
            'name' => 'C-Reactive Protein',
            'category' => 'Serology & Immunology',
            'sample_type' => 'Serum',
            'is_active' => '1',
            'price' => '250',
            'schema' => json_encode(['schema_version' => 1, 'sections' => [[
                'title' => 'CRP',
                'parameters' => [['key' => 'crp', 'label' => 'CRP', 'type' => 'numeric', 'unit' => 'mg/L', 'required' => true,
                    'reference_ranges' => [['min' => 0, 'max' => 6, 'sex' => 'any']]]],
            ]]], JSON_UNESCAPED_SLASHES),
        ], $overrides);
    }

    public function test_admin_can_create_a_test_definition(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/tests', $this->payload());

        $test = TestDefinition::firstOrFail();
        $response->assertRedirect(route('tests.show', $test));
        $this->assertSame('CRP-Q', $test->code);
        $this->assertSame(1, $test->version);
        $this->assertSame('mg/L', $test->schema['sections'][0]['parameters'][0]['unit']);
        $this->assertDatabaseHas('test_definition_versions', ['test_definition_id' => $test->id, 'version' => 1]);
        $this->get(route('tests.show', $test))->assertOk()->assertSee('C-Reactive Protein');
    }

    public function test_duplicate_codes_are_rejected(): void
    {
        $this->actingAsAdmin();
        $this->post('/tests', $this->payload());

        $this->post('/tests', $this->payload(['name' => 'Other']))->assertSessionHasErrors('code');
        $this->assertSame(1, TestDefinition::count());
    }

    public function test_invalid_schema_is_rejected_with_messages(): void
    {
        $this->actingAsAdmin();

        $this->post('/tests', $this->payload(['schema' => '{not json']))->assertSessionHasErrors('schema');
        $this->post('/tests', $this->payload(['schema' => json_encode(['sections' => [['title' => 'A', 'parameters' => [['label' => 'X', 'type' => 'nope']]]]])]))
            ->assertSessionHasErrors('schema');
        $this->assertSame(0, TestDefinition::count());
    }

    public function test_editing_reportable_content_creates_a_new_version(): void
    {
        $this->actingAsAdmin();
        $this->post('/tests', $this->payload());
        $test = TestDefinition::firstOrFail();

        // Only the active flag changes → same version.
        $this->put(route('tests.update', $test), $this->payload(['is_active' => '0']))->assertRedirect();
        $this->assertSame(1, $test->fresh()->version);

        // Unit change → new version.
        $this->put(route('tests.update', $test), $this->payload(['schema' => str_replace('mg/L', 'mg/dL', $this->payload()['schema'])]))->assertRedirect();
        $this->assertSame(2, $test->fresh()->version);
        $this->assertSame(2, $test->versions()->count());
    }

    public function test_edit_page_and_create_page_render_the_builder(): void
    {
        $this->actingAsAdmin();
        $test = $this->definition();

        $this->get('/tests/create')->assertOk()->assertSee('schemaBuilder', false);
        $this->get(route('tests.edit', $test))->assertOk()->assertSee('Haemoglobin test');
    }

    public function test_listing_supports_search_filters_sorting_and_pagination(): void
    {
        $this->actingAsAdmin();
        app(ReferenceDataImportService::class)->import();

        $this->get('/tests?q=bilirubin')->assertOk()->assertSee('Serum Bilirubin')->assertDontSee('Widal Test');
        $this->get('/tests?category=Microbiology')->assertOk()->assertSee('Aerobic Culture')->assertDontSee('Serum Bilirubin');
        $this->get('/tests?status=review')->assertOk()->assertSee('Review');
        $this->get('/tests?sort=code&dir=desc')->assertOk();
        $this->get('/tests?sort=drop%20table&dir=sideways')->assertOk(); // unknown sort keys are ignored
        $this->get('/tests?page=3')->assertOk()->assertSee('Showing');
    }

    public function test_deactivate_duplicate_archive_and_restore(): void
    {
        $this->actingAsAdmin();
        $test = $this->definition();

        $this->patch(route('tests.status', $test))->assertRedirect();
        $this->assertFalse($test->fresh()->is_active);

        $this->post(route('tests.duplicate', $test))->assertRedirect();
        $copy = TestDefinition::where('code', 'HB-T-COPY')->firstOrFail();
        $this->assertFalse($copy->is_active);
        $this->assertEquals($test->schema, $copy->schema);

        $this->delete(route('tests.destroy', $test))->assertRedirect(route('tests.index'));
        $this->assertSoftDeleted($test);
        $this->get(route('tests.show', $test))->assertOk()->assertSee('Archived');

        $this->post(route('tests.restore', $test))->assertRedirect(route('tests.show', $test));
        $this->assertNotSoftDeleted($test);
    }

    public function test_archiving_a_referenced_test_keeps_reports_intact(): void
    {
        $this->actingAsAdmin();
        $test = $this->definition();
        $report = $this->draftReport($test);

        $this->delete(route('tests.destroy', $test))->assertRedirect();

        $this->assertDatabaseHas('test_definitions', ['id' => $test->id]); // never hard-deleted
        $this->get(route('reports.show', $report))->assertOk()->assertSee('Haemoglobin test');
    }

    public function test_reference_catalog_import_is_repeatable(): void
    {
        $importer = app(ReferenceDataImportService::class);

        $first = $importer->import();
        $second = $importer->import();

        $this->assertCount(count($importer->catalog()), $first['created']);
        $this->assertSame([], $second['created']);
        $this->assertSame(TestDefinition::count(), count($second['skipped']));
        $this->assertSame('workbook', TestDefinition::where('code', 'CBC')->value('source'));
        $this->assertTrue((bool) TestDefinition::where('code', 'CBC')->value('needs_review'));
    }
}
