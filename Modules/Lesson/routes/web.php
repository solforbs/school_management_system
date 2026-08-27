<?php

use Illuminate\Support\Facades\Route;
use Modules\Lesson\Http\Controllers\LessonController;
use Modules\Lesson\Http\Controllers\LessonReportController;

Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    // The day grid is a Livewire component - picking a class or date, and
    // saving the day's marks, never reloads the page.
    Route::livewire('lessons', 'lesson::index')->name('lesson.index');
    Route::post('lessons', [LessonController::class, 'store'])->name('lesson.store');
    Route::get('lessons/{lesson}', [LessonController::class, 'show'])->name('lesson.show');
    Route::get('lessons/{lesson}/edit', [LessonController::class, 'edit'])->name('lesson.edit');
    Route::put('lessons/{lesson}', [LessonController::class, 'update'])->name('lesson.update');
    Route::delete('lessons/{lesson}', [LessonController::class, 'destroy'])->name('lesson.destroy');

    Route::livewire('lesson-reports', 'lesson::reports.index')->name('lesson.reports.index');
    Route::post('lesson-reports/generate', [LessonReportController::class, 'generate'])->name('lesson.reports.generate');
    Route::get('lesson-reports/{lessonReport}', [LessonReportController::class, 'show'])->name('lesson.reports.show');
    Route::get('lesson-reports/{lessonReport}/download', [LessonReportController::class, 'download'])->name('lesson.reports.download');
});
