<?php

use Illuminate\Support\Facades\Route;
use Modules\Curriculum\Http\Controllers\CurriculumController;

/**
 * The screens are Livewire components; the controller keeps the write
 * endpoints for non-Livewire clients and the test suite.
 */
Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::livewire('curricula', 'curriculum::index')->name('curriculum.index');
    Route::livewire('curricula/create', 'curriculum::form')->name('curriculum.create');
    // Parameter deliberately not named after the model - see Classes.
    Route::livewire('curricula/{curriculumId}/edit', 'curriculum::form')->name('curriculum.edit');

    Route::resource('curricula', CurriculumController::class)
        ->names('curriculum')
        ->parameters(['curricula' => 'curriculum'])
        ->only(['store', 'show', 'update', 'destroy']);
});
