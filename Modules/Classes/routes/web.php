<?php

use Illuminate\Support\Facades\Route;
use Modules\Classes\Http\Controllers\ClassesController;

/**
 * The screens are Livewire components; the controller keeps the write
 * endpoints for non-Livewire clients and the test suite.
 */
Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::livewire('classes', 'classes::index')->name('classes.index');
    Route::livewire('classes/create', 'classes::form')->name('classes.create');
    // Parameter deliberately not named after the model: that triggers
    // implicit route-model binding, which hands mount() a model where it
    // expects an id (and bypasses the scoping done there).
    Route::livewire('classes/{classId}/edit', 'classes::form')->name('classes.edit');

    Route::resource('classes', ClassesController::class)
        ->names('classes')
        ->only(['store', 'show', 'update', 'destroy']);
});
