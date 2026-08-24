<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\Http\Controllers\AttendanceController;

Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::get('attendances/export', [AttendanceController::class, 'export'])->name('attendance.export');
    Route::resource('attendances', AttendanceController::class)->names('attendance');
});
