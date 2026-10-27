<?php

use App\Http\Controllers\BillController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TestDefinitionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

require __DIR__.'/auth.php';

Route::middleware(['auth', 'lab.admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Test definitions
    Route::controller(TestDefinitionController::class)->prefix('tests')->name('tests.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{test}', 'show')->withTrashed()->name('show');
        Route::get('/{test}/edit', 'edit')->name('edit');
        Route::put('/{test}', 'update')->name('update');
        Route::post('/{test}/duplicate', 'duplicate')->withTrashed()->name('duplicate');
        Route::patch('/{test}/status', 'toggleStatus')->name('status');
        Route::patch('/{test}/reviewed', 'markReviewed')->name('reviewed');
        Route::delete('/{test}', 'destroy')->name('destroy');
        Route::post('/{test}/restore', 'restore')->withTrashed()->name('restore');
    });

    // Reports
    Route::controller(ReportController::class)->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{report}', 'show')->name('show');
        Route::get('/{report}/edit', 'edit')->name('edit');
        Route::put('/{report}', 'update')->name('update');
        Route::delete('/{report}', 'destroy')->name('destroy');
        Route::post('/{report}/finalize', 'finalize')->name('finalize');
        Route::post('/{report}/correction', 'startCorrection')->name('correction');
        Route::post('/{report}/repeat', 'repeat')->name('repeat');
        Route::get('/{report}/preview', 'preview')->name('preview');
        Route::get('/{report}/pdf', 'pdf')->name('pdf');
        Route::get('/{report}/revisions/{revision}', 'revision')->scopeBindings()->name('revision');
    });

    // Billing
    Route::controller(BillController::class)->prefix('billing')->name('billing.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{bill}', 'show')->name('show');
        Route::get('/{bill}/edit', 'edit')->name('edit');
        Route::put('/{bill}', 'update')->name('update');
        Route::post('/{bill}/cancel', 'cancel')->name('cancel');
        Route::get('/{bill}/receipt', 'receipt')->name('receipt');
        Route::post('/{bill}/payments', 'storePayment')->name('payments.store');
        Route::delete('/{bill}/payments/{payment}', 'destroyPayment')->scopeBindings()->name('payments.destroy');
    });

    // Patients
    Route::controller(PatientController::class)->prefix('patients')->name('patients.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/search', 'search')->name('search');
        Route::get('/{patient}', 'show')->name('show');
        Route::get('/{patient}/edit', 'edit')->name('edit');
        Route::put('/{patient}', 'update')->name('update');
    });

    // Administrator profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
});
