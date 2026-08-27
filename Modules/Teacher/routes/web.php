<?php

use Illuminate\Support\Facades\Route;
use Modules\Teacher\Http\Controllers\TeacherController;

/**
 * The screens are Livewire components; the controller keeps the write
 * endpoints for non-Livewire clients and the test suite. Both run the same
 * SaveTeacher action underneath.
 */
Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::livewire('teachers', 'teacher::index')->name('teacher.index');
    Route::livewire('teachers/create', 'teacher::form')->name('teacher.create');
    // Parameter deliberately not named after the model - implicit binding
    // would hand mount() a model where it expects an id.
    Route::livewire('teachers/{teacherId}/edit', 'teacher::form')->name('teacher.edit');

    Route::resource('teachers', TeacherController::class)
        ->names('teacher')
        ->only(['store', 'show', 'update', 'destroy']);
});
