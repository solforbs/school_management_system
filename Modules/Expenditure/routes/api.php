<?php

use Illuminate\Support\Facades\Route;
use Modules\Expenditure\Http\Controllers\ExpenditureController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('expenditures', ExpenditureController::class)->names('expenditure');
});
