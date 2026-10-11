<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KioskController;

Route::post('/kiosk/scan', [KioskController::class, 'scan']);
Route::get('/kiosk/poll', [KioskController::class, 'pollScan']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
