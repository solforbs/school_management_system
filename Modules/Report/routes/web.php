<?php

use Illuminate\Support\Facades\Route;
use Modules\Report\Http\Controllers\ReportController;

Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    // The dashboard is a Livewire page; the export stays an ordinary link
    // because it streams a file.
    Route::livewire('reports', 'report::index')->name('report.index');
    Route::get('reports/export', [ReportController::class, 'export'])->name('report.export');
});
