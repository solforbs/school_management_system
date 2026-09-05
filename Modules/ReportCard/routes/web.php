<?php

use Illuminate\Support\Facades\Route;
use Modules\ReportCard\Http\Controllers\ReportCardController;
use Modules\ReportCard\Http\Controllers\ReportSettingsController;

/**
 * The screens are Livewire components; the settings controller keeps its
 * write endpoints for non-Livewire clients and the test suite.
 */

// Parents follow this from an email or SMS, so it can't sit behind auth -
// the unguessable, single-use token is what protects it.
Route::get('report-cards/download/{token}', [ReportCardController::class, 'download'])
    ->name('reportcard.download');

Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::livewire('report-cards/settings', 'reportcard::settings')->name('reportcard.settings');
    Route::put('report-cards/settings', [ReportSettingsController::class, 'updateTemplate'])->name('reportcard.settings.template');
    Route::post('report-cards/settings/grading-bands', [ReportSettingsController::class, 'storeGradingBand'])->name('reportcard.gradingbands.store');
    Route::post('report-cards/settings/grading-bands/defaults', [ReportSettingsController::class, 'loadDefaultGradingBands'])->name('reportcard.gradingbands.defaults');
    Route::delete('report-cards/settings/grading-bands/{gradingBand}', [ReportSettingsController::class, 'destroyGradingBand'])->name('reportcard.gradingbands.destroy');
    // Declared before the {reportcard} show route further down, which would
    // otherwise swallow "report-cards/settings/preview".
    Route::get('report-cards/settings/preview', [ReportSettingsController::class, 'preview'])->name('reportcard.settings.preview');
    Route::delete('report-card/{reportCard}/delete', [ReportCardController::class, 'destroy'])->name('reportcard.destroy');
    Route::post('report-cards/send-ready', [ReportCardController::class, 'sendReady'])->name('reportcard.send-ready');
    Route::get('report-cards/export', [ReportCardController::class, 'export'])->name('reportcard.export');
    Route::livewire('report-cards', 'reportcard::index')->name('reportcard.index');
    Route::get('report-cards/{reportcard}', [ReportCardController::class, 'show'])->name('reportcard.show');
    Route::get('report-cards/{reportcard}/pdf', [ReportCardController::class, 'pdf'])->name('reportcard.pdf');
});
