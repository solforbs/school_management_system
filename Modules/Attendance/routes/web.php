<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\Http\Controllers\AttendanceController;

/**
 * The log viewer is a Livewire component - filtering, paging and deleting
 * never reload the page (deletion is a Livewire method on the component
 * itself, not an HTTP route, since Director-initiated deletes never need to
 * be reachable outside a logged-in session view). Scans still only ever
 * arrive by device push - there is no "create" here - but a Director may
 * now remove a record after the fact. The JSON endpoint lives in api.php.
 */
Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::get('attendances/export', [AttendanceController::class, 'export'])->name('attendance.export');
    Route::livewire('attendances', 'attendance::index')->name('attendance.index');
});
