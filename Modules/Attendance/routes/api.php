<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\Http\Controllers\AttendanceController;

Route::prefix('v1')->group(function () {
    // No apiResource here: the module is a log viewer, not a CRUD resource -
    // scans are written by the biometric devices, never by this API.

    // Fetched via same-origin browser JS from the Attendance dashboard page,
    // so it needs to participate in the session (the "api" middleware group
    // has no session handling) in order to see the logged-in user at all.
    Route::middleware('web')
        ->get('institutions/{institution}/attendance', [AttendanceController::class, 'institutionAttendance'])
        ->name('attendance.institution');
});
