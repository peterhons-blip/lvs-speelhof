<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LeerlingenController;
use App\Http\Controllers\MeldingController;
use App\Http\Controllers\Auth\SmartschoolController;
use App\Http\Middleware\EnsureSmartschoolAuthenticated;

// ===== Publiek / SSO =====
Route::view('/', 'home')->name('home');   // <— publieke landing page

Route::get('/login', [SmartschoolController::class, 'redirect'])->name('login');
Route::get('/auth/smartschool/callback', [SmartschoolController::class, 'callback'])->name('smartschool.callback');
Route::get('/logout', [SmartschoolController::class, 'logout'])->name('logout');

// ===== Protected (na SSO) =====
Route::middleware([EnsureSmartschoolAuthenticated::class])->group(function () {
    Route::prefix('leerlingen')->name('leerlingen.')->group(function () {
        Route::get('/', [LeerlingenController::class, 'index'])->name('index');
        Route::get('/verjaardagen', [LeerlingenController::class, 'verjaardagen'])->name('verjaardagen');
        Route::get('/{id}', [LeerlingenController::class, 'show'])->whereNumber('id')->name('show');
    });

    Route::prefix('meldingen')->name('meldingen.')->group(function () {
        Route::get('/add/{id}', [MeldingController::class, 'create'])->whereNumber('id')->name('create');
        Route::post('/add/{id}', [MeldingController::class, 'store'])->whereNumber('id')->name('store');
    });
});
