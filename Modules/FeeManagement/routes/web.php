<?php

use Illuminate\Support\Facades\Route;
use Modules\FeeManagement\Http\Controllers\FeeManagementController;
use Modules\FeeManagement\Http\Controllers\FeeReceiptController;

/**
 * The fee screens are Livewire components; the controller keeps the write
 * endpoints for non-Livewire clients and the test suite. Both run the same
 * SaveFee action underneath.
 */
Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::post('feemanagements/send-reminders', [FeeManagementController::class, 'sendReminders'])->name('feemanagement.send-reminders');

    // The receipt scanner is its own wizard - upload, extract, review, save.
    Route::get('feemanagements/receipts/create', [FeeReceiptController::class, 'create'])->name('feemanagement.receipts.create');
    Route::post('feemanagements/receipts/extract', [FeeReceiptController::class, 'extract'])->name('feemanagement.receipts.extract');
    Route::get('feemanagements/receipts/student-fees', [FeeReceiptController::class, 'studentFees'])->name('feemanagement.receipts.student-fees');
    Route::post('feemanagements/receipts', [FeeReceiptController::class, 'store'])->name('feemanagement.receipts.store');

    Route::livewire('feemanagements', 'feemanagement::index')->name('feemanagement.index');
    Route::livewire('feemanagements/create', 'feemanagement::form')->name('feemanagement.create');
    // Parameter deliberately not named after the model - implicit binding
    // would hand mount() a model where it expects an id.
    Route::livewire('feemanagements/{feeId}/edit', 'feemanagement::form')->name('feemanagement.edit');

    Route::resource('feemanagements', FeeManagementController::class)
        ->names('feemanagement')
        ->only(['store', 'show', 'update', 'destroy']);
});
