<?php

use Illuminate\Support\Facades\Route;
use Modules\Institution\Http\Controllers\InstitutionController;

/**
 * The screens are Livewire components; the controller keeps the write
 * endpoints for non-Livewire clients and the test suite. Both run the same
 * SaveInstitution action underneath.
 */
Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::livewire('institutions', 'institution::index')->name('institution.index');
    Route::livewire('institutions/create', 'institution::form')->name('institution.create');
    // Parameter deliberately not named after the model - implicit binding
    // would hand mount() a model where it expects an id.
    Route::livewire('institutions/{institutionId}/edit', 'institution::form')->name('institution.edit');

    Route::resource('institutions', InstitutionController::class)
        ->names('institution')
        ->only(['store', 'show', 'update', 'destroy']);

    Route::post('institutions/{institution}/approve', [InstitutionController::class, 'approve'])->name('institution.approve');
    Route::post('institutions/{institution}/choose', [InstitutionController::class, 'choose'])->name('institution.choose');
});
