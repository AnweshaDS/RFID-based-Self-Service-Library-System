<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KioskController;

Route::post('/kiosk/scan', [KioskController::class, 'scan'])->middleware('kiosk.token');
Route::get('/kiosk/poll', [KioskController::class, 'pollScan'])->middleware('kiosk.device');
Route::post('/kiosk/reader-status', [KioskController::class, 'readerStatus'])->middleware('kiosk.token');
Route::get('/kiosk/reader-status', [KioskController::class, 'readerOnline']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');