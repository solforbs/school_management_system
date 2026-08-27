<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\Http\Controllers\AttendanceController;

/**
 * The log viewer is a Livewire component - filtering and paging never
 * reload the page. There is no CRUD here: attendance scans are written by
 * the biometric devices, and the JSON endpoint lives in api.php.
 */
Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::get('attendances/export', [AttendanceController::class, 'export'])->name('attendance.export');
    Route::livewire('attendances', 'attendance::index')->name('attendance.index');
});
