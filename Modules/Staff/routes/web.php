<?php

use Illuminate\Support\Facades\Route;
use Modules\Staff\Http\Controllers\StaffController;
use Modules\Staff\Http\Controllers\StaffPaymentController;

/**
 * The screens are Livewire components; the controllers keep the write
 * endpoints for non-Livewire clients and the test suite. Both sides run the
 * same SaveStaff / SaveStaffPayment actions underneath.
 */
Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::livewire('staff-payments', 'staff::payments.index')->name('staff.payments.index');
    Route::livewire('staff-payments/create', 'staff::payments.form')->name('staff.payments.create');
    // Parameters deliberately not named after their models - implicit
    // binding would hand mount() a model where it expects an id.
    Route::livewire('staff-payments/{paymentId}/edit', 'staff::payments.form')->name('staff.payments.edit');

    Route::resource('staff-payments', StaffPaymentController::class)
        ->names('staff.payments')
        ->parameters(['staff-payments' => 'payment'])
        ->only(['store', 'show', 'update', 'destroy']);

    Route::livewire('staff', 'staff::index')->name('staff.index');
    Route::livewire('staff/create', 'staff::form')->name('staff.create');
    Route::livewire('staff/{staffId}/edit', 'staff::form')->name('staff.edit');

    Route::resource('staff', StaffController::class)
        ->names('staff')
        ->only(['store', 'show', 'update', 'destroy']);
});
