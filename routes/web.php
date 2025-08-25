<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Test\DateTimeHelperTestController;
use App\Http\Controllers\Test\MainHelperTestController;

// Test Routes
Route::get('/test/date-time-helpers', [DateTimeHelperTestController::class, 'index'])->name('test.date-time-helpers');
Route::get('/api/test/date-time-helpers', [DateTimeHelperTestController::class, 'api'])->name('api.test.date-time-helpers');
Route::post('/api/test/daily-worklog-summary', [DateTimeHelperTestController::class, 'testDailyWorklogSummary'])->name('api.test.daily-worklog-summary');
Route::get('/test/main-helpers', [MainHelperTestController::class, 'index'])->name('test.main-helpers');

Route::get('{any?}', function() {
    return view('application');
})->where('any', '.*');