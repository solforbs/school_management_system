<?php

use Illuminate\Support\Facades\Route;
use Modules\Result\Http\Controllers\BandEntryController;
use Modules\Result\Http\Controllers\ResultController;
use Modules\Result\Http\Controllers\ResultEntryController;

/**
 * The screens are Livewire components, so marking a class never reloads the
 * page. The controllers keep the write endpoints: they're what a
 * non-Livewire client (and the test suite) posts to, and both sides run the
 * same SaveResult action underneath.
 */
Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    // Declared before the resource so "results/enter" and "results/create"
    // aren't swallowed by the {result} show route.
    //
    // The teacher records marks in three steps: pick an exam sitting
    // (result.entry.exams), pick one of their classes in it
    // (result.entry.classes), then pick the subject and fill the sheet
    // (result.entry.create). The sheet also still opens directly from an
    // examination_id for a Director-pinned paper.
    Route::livewire('results/enter', 'result::entry-exams')->name('result.entry.exams');
    Route::livewire('results/enter/classes', 'result::entry-classes')->name('result.entry.classes');
    Route::livewire('results/enter/sheet', 'result::entry')->name('result.entry.create');
    Route::post('results/enter/sheet', [ResultEntryController::class, 'store'])->name('result.entry.store');

    Route::get('results/export', [ResultController::class, 'export'])->name('result.export');

    Route::livewire('results', 'result::index')->name('result.index');
    Route::livewire('results/create', 'result::form')->name('result.create');
    Route::livewire('results/class/{classId}', 'result::class-results')->name('result.class');
    // Parameter deliberately not named {result}: that name triggers implicit
    // route-model binding, which hands mount() a model where it expects an
    // id (and bypasses the scoping done there).
    Route::livewire('results/{resultId}/edit', 'result::form')->name('result.edit');

    Route::resource('results', ResultController::class)
        ->names('result')
        ->only(['store', 'show', 'update', 'destroy']);

    // CBC formative / SBA band entry.
    // Redirected to from FormativeAssessmentController after creating a sitting.
    Route::livewire('assessments/band-entry', 'result::band-entry')->name('assessment.band.entry');
    Route::post('assessments/band-entry', [BandEntryController::class, 'store'])->name('assessment.band.entry.store');
});
