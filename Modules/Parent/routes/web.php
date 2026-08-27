<?php

use Illuminate\Support\Facades\Route;
use Modules\Parent\Http\Controllers\ParentController;

/**
 * The screens are Livewire components; the controller keeps the write
 * endpoints for non-Livewire clients and the test suite. Both run the same
 * SaveParent action underneath.
 */
Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::livewire('parents', 'parent::index')->name('parent.index');
    Route::livewire('parents/create', 'parent::form')->name('parent.create');
    // Parameter deliberately not named after the model - implicit binding
    // would hand mount() a model where it expects an id.
    Route::livewire('parents/{parentId}/edit', 'parent::form')->name('parent.edit');

    Route::resource('parents', ParentController::class)
        ->names('parent')
        ->only(['store', 'show', 'update', 'destroy']);
});
