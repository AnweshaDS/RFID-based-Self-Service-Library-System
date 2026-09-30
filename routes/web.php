<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\KioskController;

Route::prefix('kiosk')->name('kiosk.')->group(function () {
    Route::get('/', [KioskController::class, 'welcome'])->name('welcome');
    Route::post('/enter', [KioskController::class, 'enterByCardnumber'])->name('enter');
    Route::middleware('kiosk.timeout')->group(function () {
        Route::get('/dashboard', [KioskController::class, 'dashboard'])->name('dashboard');

        Route::get('/borrow', [KioskController::class, 'showBorrow'])->name('borrow');
        Route::post('/borrow/lookup', [KioskController::class, 'lookupBorrowItem'])->name('borrow.lookup');
        Route::get('/borrow/confirm', [KioskController::class, 'showConfirmBorrow'])->name('borrow.confirm');
        Route::post('/borrow/confirm', [KioskController::class, 'confirmBorrow'])->name('borrow.confirm.store');
        Route::get('/borrow/cancel', [KioskController::class, 'cancelBorrow'])->name('borrow.cancel');

        Route::get('/return', [KioskController::class, 'showReturn'])->name('return');
        Route::post('/return/lookup', [KioskController::class, 'lookupReturnItem'])->name('return.lookup');
        Route::get('/return/confirm', [KioskController::class, 'showConfirmReturn'])->name('return.confirm');
        Route::post('/return/confirm', [KioskController::class, 'confirmReturn'])->name('return.confirm.store');
        Route::get('/return/cancel', [KioskController::class, 'cancelReturn'])->name('return.cancel');
    });

    Route::post('/logout', [KioskController::class, 'logout'])->name('logout');
});

Route::middleware('guest')->group(function () {
    Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
});

Route::get('/', function () {
    return redirect()->route('kiosk.welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'role:Admin,Librarian'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('dashboard');
});

require __DIR__.'/auth.php';