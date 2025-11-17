<?php

use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\FetchController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\OfficeController;
use App\Http\Controllers\Api\ScannerController;
use App\Http\Controllers\Api\SignerController;
use App\Http\Controllers\Api\TimesheetController;
use App\Http\Middleware\ForceAcceptJson;
use App\Http\Middleware\NoRemoteConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware(['auth:sanctum', ForceAcceptJson::class])->group(function () {
    Route::get('user', fn (Request $request) => $request->user());

    Route::get('timesheet', TimesheetController::class);

    Route::get('holiday', HolidayController::class);

    Route::post('signer', SignerController::class);

    Route::apiResource('scanners', ScannerController::class)->only(['index', 'show']);
    Route::apiResource('scanners.employees', EmployeeController::class)->only(['index', 'show'])->scoped();

    Route::apiResource('offices', OfficeController::class)->only(['index', 'show']);
    Route::apiResource('offices.employees', EmployeeController::class)->only(['index', 'show'])->scoped();

    Route::apiResource('employees', EmployeeController::class)->only(['index', 'show']);
    Route::apiResource('employees.scanners', ScannerController::class)->only(['index', 'show'])->scoped();
    Route::apiResource('employees.offices', OfficeController::class)->only(['index', 'show'])->scoped();

    Route::controller(FetchController::class)
        ->prefix('fetch')
        ->group(function () {
            Route::post('send', 'send')->middleware([NoRemoteConnection::class]);
            Route::post('receive', 'receive');
        });
});
