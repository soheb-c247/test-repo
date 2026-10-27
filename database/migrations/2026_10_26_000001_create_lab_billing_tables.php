<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billing: one active bill per report (a cancelled bill may be replaced),
 * its priced lines and the payments received against it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number', 32)->nullable()->unique();
            $table->uuid('form_token')->nullable()->unique(); // guards against double submission
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('lab_patients')->restrictOnDelete();
            $table->string('patient_name', 150); // as billed

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->string('discount_type', 10)->default('amount'); // amount | percent
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('balance', 10, 2)->default(0);

            $table->string('status', 20)->default('unpaid')->index(); // unpaid | partial | paid | cancelled
            $table->string('notes', 500)->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->unsignedInteger('lock_version')->default(1);

            $table->dateTime('issued_at')->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('patient_name');
            $table->index(['report_id', 'status']);
        });

        Schema::create('lab_bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained('lab_bills')->cascadeOnDelete();
            $table->foreignId('report_item_id')->nullable()->constrained('report_items')->nullOnDelete();
            $table->string('test_code', 32)->nullable();
            $table->string('description', 150);
            $table->decimal('amount', 10, 2)->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('lab_bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained('lab_bills')->cascadeOnDelete();
            $table->uuid('form_token')->nullable()->unique();
            $table->decimal('amount', 10, 2);
            $table->string('method', 20); // cash | upi | card | bank | other
            $table->string('reference', 100)->nullable();
            $table->dateTime('paid_at')->index();
            $table->string('note', 255)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_bill_payments');
        Schema::dropIfExists('lab_bill_items');
        Schema::dropIfExists('lab_bills');
    }
};
