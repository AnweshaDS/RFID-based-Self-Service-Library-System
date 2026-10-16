<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\KioskCartController;

Route::prefix('kiosk')->name('kiosk.')->group(function () {
    Route::get('/', [KioskController::class, 'welcome'])->name('welcome');
    Route::post('/enter', [KioskController::class, 'enterByCardnumber'])->name('enter');
    Route::middleware('kiosk.timeout')->group(function () {
Route::post('/keepalive', fn () => response()->noContent())->name('keepalive');
        Route::get('/dashboard', [KioskController::class, 'dashboard'])->name('dashboard');
        Route::get('/receipt', [KioskController::class, 'showReceipt'])->name('receipt');
Route::get('/success', [KioskController::class, 'showSuccess'])->name('success');
            
            foreach (['borrow', 'return'] as $mode) {
            Route::get("/$mode", [KioskCartController::class, 'show'])->defaults('mode', $mode)->name($mode);
            Route::post("/$mode/lookup", [KioskCartController::class, 'add'])->defaults('mode', $mode)->middleware('throttle:60,1')->name("$mode.lookup");
            Route::post("/$mode/remove", [KioskCartController::class, 'remove'])->defaults('mode', $mode)->name("$mode.remove");
            Route::post("/$mode/clear", [KioskCartController::class, 'clear'])->defaults('mode', $mode)->name("$mode.clear");
            Route::post("/$mode/confirm", [KioskCartController::class, 'confirm'])->defaults('mode', $mode)->name("$mode.confirm.store");
        }
    });

    Route::post('/logout', [KioskController::class, 'logout'])->name('logout');
});

Route::get('/', function () {
    return redirect()->route('kiosk.welcome');
});

Route::get('/dashboard', function () {
    $user = auth()->user()->load('roles.permissions');

    $permissions = $user->roles
        ->flatMap(fn ($role) => $role->permissions)
        ->unique('id')
        ->sortBy('name')
        ->values();

    return view('dashboard', [
        'roles' => $user->roles,
        'permissions' => $permissions,
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'permission:tasks.assign'])->prefix('tasks')->name('tasks.')->group(function () {
    Route::get('/', [\App\Http\Controllers\TaskController::class, 'index'])->name('index');
    Route::get('/create', [\App\Http\Controllers\TaskController::class, 'create'])->name('create');
    Route::post('/', [\App\Http\Controllers\TaskController::class, 'store'])->name('store');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/my-tasks', [\App\Http\Controllers\StaffController::class, 'myTasks'])->name('my-tasks');
    Route::patch('/my-tasks/{task}/status', [\App\Http\Controllers\TaskController::class, 'updateStatus'])->name('tasks.update-status');

});

Route::middleware(['auth', 'role:Admin,Librarian'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('dashboard');
});

Route::prefix('patron')->name('patron.')->group(function () {
    Route::get('/login', [\App\Http\Controllers\PatronController::class, 'showLogin'])->name('login');
    Route::post('/login', [\App\Http\Controllers\PatronController::class, 'login'])->name('login.submit');
});

require __DIR__.'/auth.php';