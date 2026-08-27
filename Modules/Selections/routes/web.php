<?php

use Illuminate\Support\Facades\Route;
use Modules\Selections\Http\Controllers\SelectionsController;

/**
 * The picker is a Livewire component; the controller keeps the write
 * endpoint for non-Livewire clients and the test suite.
 */
Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::livewire('selections', 'selections::index')->name('selections.index');
    Route::post('selections', [SelectionsController::class, 'store'])->name('selections.store');
});
