<?php

use App\Http\Controllers\Test\DateTimeHelperTestController;
use App\Http\Controllers\Test\MainHelperTestController;
use Illuminate\Support\Facades\Route;

// Test Routes
Route::get('/test/date-time-helpers', [DateTimeHelperTestController::class, 'index'])->name('test.date-time-helpers');
Route::get('/api/test/date-time-helpers', [DateTimeHelperTestController::class, 'api'])->name('api.test.date-time-helpers');
Route::post('/api/test/daily-worklog-summary', [DateTimeHelperTestController::class, 'testDailyWorklogSummary'])->name('api.test.daily-worklog-summary');
Route::post('/api/test/nad-data', [DateTimeHelperTestController::class, 'testNADData'])->name('api.test.nad-data');
Route::post('/test/export-data', [DateTimeHelperTestController::class, 'testExportData'])->name('test.export-data');
Route::get('/test/main-helpers', [MainHelperTestController::class, 'index'])->name('test.main-helpers');

Route::get('{any?}', function () {
    return view('application');
})->where('any', '.*');
