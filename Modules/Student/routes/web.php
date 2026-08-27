<?php

use Illuminate\Support\Facades\Route;
use Modules\Student\Http\Controllers\StudentController;

/**
 * The screens are Livewire components; the controller keeps the write
 * endpoints for non-Livewire clients and the test suite. Both run the same
 * SaveStudent action underneath.
 */
Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::livewire('students', 'student::index')->name('student.index');
    Route::livewire('students/create', 'student::form')->name('student.create');
    // Parameter deliberately not named after the model - implicit binding
    // would hand mount() a model where it expects an id.
    Route::livewire('students/{studentId}/edit', 'student::form')->name('student.edit');

    Route::resource('students', StudentController::class)
        ->names('student')
        ->only(['store', 'show', 'update', 'destroy']);
});
