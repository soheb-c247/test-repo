<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AccuCare Pathology Lab core schema.
 *
 * New tables are created alongside the legacy hospital-suite tables, which
 * are left untouched so that no historical data is lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_lab_admin')) {
                $table->boolean('is_lab_admin')->default(false)->index();
            }
            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
        });

        Schema::create('lab_patients', function (Blueprint $table) {
            $table->id();
            $table->string('patient_code', 32)->nullable()->unique();
            $table->string('name', 150);
            $table->unsignedSmallInteger('age_value')->nullable();
            $table->string('age_unit', 10)->nullable(); // years | months | days
            $table->date('date_of_birth')->nullable();
            $table->string('sex', 12)->default('unspecified'); // male | female | other | unspecified
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 255)->nullable();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('phone');
        });

        Schema::create('test_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 150);
            $table->string('category', 80)->index();
            $table->string('description', 500)->nullable();
            $table->string('sample_type', 120)->nullable();
            $table->text('collection_instructions')->nullable();
            $table->text('preparation_instructions')->nullable();
            $table->string('method', 191)->nullable();
            $table->text('reporting_notes')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->unsignedSmallInteger('turnaround_hours')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('schema');
            $table->unsignedInteger('version')->default(1);
            $table->boolean('needs_review')->default(false);
            $table->text('review_notes')->nullable();
            $table->string('source', 40)->nullable(); // e.g. "workbook" for seeded definitions
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('updated_at');
        });

        Schema::create('test_definition_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_definition_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot'); // definition metadata + schema at this version
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['test_definition_id', 'version']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_number', 32)->nullable()->unique();
            $table->uuid('form_token')->nullable()->unique(); // guards against double submission
            $table->foreignId('patient_id')->constrained('lab_patients')->restrictOnDelete();

            // Patient demographics as they were when the report was produced.
            $table->string('patient_name', 150);
            $table->unsignedSmallInteger('patient_age_value')->nullable();
            $table->string('patient_age_unit', 10)->nullable();
            $table->string('patient_sex', 12)->default('unspecified');

            $table->string('referring_doctor', 150)->nullable();
            $table->string('collected_by', 150)->nullable();
            $table->string('sample_id', 60)->nullable();
            $table->dateTime('registered_at')->index();
            $table->dateTime('collected_at')->nullable()->index();
            $table->dateTime('reported_at')->nullable()->index();
            $table->text('clinical_notes')->nullable();

            $table->string('status', 20)->default('draft')->index(); // draft | final
            $table->unsignedInteger('revision')->default(0);
            $table->string('correction_reason', 500)->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->unsignedSmallInteger('abnormal_count')->default(0);

            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index('patient_name');
            $table->index('created_at');
            $table->index(['status', 'created_at']);
        });

        Schema::create('report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_definition_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('definition_version');

            // Immutable snapshot of the definition used for this report.
            $table->string('test_code', 32);
            $table->string('test_name', 150);
            $table->string('category', 80);
            $table->string('sample_type', 120)->nullable();
            $table->string('method', 191)->nullable();
            $table->text('reporting_notes')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->json('schema_snapshot');

            $table->json('results')->nullable();
            $table->json('flags')->nullable();
            $table->unsignedSmallInteger('abnormal_count')->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('test_name');
            $table->index('category');
        });

        Schema::create('report_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->json('snapshot'); // complete report + items as released
            $table->string('reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['report_id', 'revision']);
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60)->index();
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('report_revisions');
        Schema::dropIfExists('report_items');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('test_definition_versions');
        Schema::dropIfExists('test_definitions');
        Schema::dropIfExists('lab_patients');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_lab_admin']);
            $table->dropColumn(['is_lab_admin', 'last_login_at']);
        });
    }
};
