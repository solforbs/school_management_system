<?php

use Illuminate\Support\Facades\Route;
use Modules\Subject\Http\Controllers\SubjectController;
use Modules\Subject\Http\Controllers\SubjectTeacherController;

/**
 * The screens are Livewire components; the controllers keep the write
 * endpoints for non-Livewire clients and the test suite.
 */
Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    // Declared before the resource so "subjects/teachers" and
    // "subjects/create" aren't swallowed by the {subject} show route.
    Route::livewire('subjects/teachers', 'subject::teachers')->name('subject.teachers.index');
    Route::post('subjects/teachers', [SubjectTeacherController::class, 'store'])->name('subject.teachers.store');
    Route::delete('subjects/teachers/{assignment}', [SubjectTeacherController::class, 'destroy'])->name('subject.teachers.destroy');

    Route::livewire('subjects', 'subject::index')->name('subject.index');
    Route::livewire('subjects/create', 'subject::form')->name('subject.create');
    // Parameter deliberately not named {subject}: that name triggers
    // implicit route-model binding, which hands mount() a model where it
    // expects an id (and bypasses the scoping done there).
    Route::livewire('subjects/{subjectId}/edit', 'subject::form')->name('subject.edit');

    Route::resource('subjects', SubjectController::class)
        ->names('subject')
        ->only(['store', 'show', 'update', 'destroy']);
});
