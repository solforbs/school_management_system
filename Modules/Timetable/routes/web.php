<?php

use Illuminate\Support\Facades\Route;
use Modules\Timetable\Http\Controllers\TimetableController;

/**
 * The screens are Livewire components; the controller keeps the write
 * endpoints and the CSV import, which is its own upload flow.
 */
Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::get('timetables/import', [TimetableController::class, 'import'])->name('timetable.import');
    Route::post('timetables/import', [TimetableController::class, 'importStore'])->name('timetable.import.store');
    Route::get('timetables/import/template', [TimetableController::class, 'importTemplate'])->name('timetable.import.template');

    Route::livewire('timetables', 'timetable::index')->name('timetable.index');
    Route::livewire('timetables/create', 'timetable::form')->name('timetable.create');
    // Parameter deliberately not named after the model - implicit binding
    // would hand mount() a model where it expects an id.
    Route::livewire('timetables/{entryId}/edit', 'timetable::form')->name('timetable.edit');

    Route::resource('timetables', TimetableController::class)
        ->names('timetable')
        ->only(['store', 'show', 'update', 'destroy']);
});
