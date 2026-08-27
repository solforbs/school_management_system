<?php

use Illuminate\Support\Facades\Route;
use Modules\Expenditure\Http\Controllers\ExpenditureCategoryController;
use Modules\Expenditure\Http\Controllers\ExpenditureController;

/**
 * The screens are Livewire components, so browsing and editing happen
 * without a page reload. The controller keeps the write endpoints: they're
 * what a non-Livewire client (and the test suite) posts to, and both sides
 * run the same SaveExpenditure action underneath.
 */
Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    // Declared before the resource so "expenditures/categories" and
    // "expenditures/create" aren't swallowed by the {expenditure} show route.
    Route::livewire('expenditures/categories', 'expenditure::categories')->name('expenditure.categories.index');
    Route::post('expenditures/categories', [ExpenditureCategoryController::class, 'store'])->name('expenditure.categories.store');
    Route::post('expenditures/categories/defaults', [ExpenditureCategoryController::class, 'loadDefaults'])->name('expenditure.categories.defaults');
    Route::put('expenditures/categories/{category}', [ExpenditureCategoryController::class, 'update'])->name('expenditure.categories.update');
    Route::delete('expenditures/categories/{category}', [ExpenditureCategoryController::class, 'destroy'])->name('expenditure.categories.destroy');

    Route::livewire('expenditures', 'expenditure::index')->name('expenditure.index');
    Route::livewire('expenditures/create', 'expenditure::form')->name('expenditure.create');
    // Parameter deliberately not named {expenditure}: that name triggers
    // implicit route-model binding, which hands mount() a model where it
    // expects an id (and bypasses the institution scoping done there).
    Route::livewire('expenditures/{expenditureId}/edit', 'expenditure::form')->name('expenditure.edit');

    Route::resource('expenditures', ExpenditureController::class)
        ->names('expenditure')
        ->only(['store', 'show', 'update', 'destroy']);
});
