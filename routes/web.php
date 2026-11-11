<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Portal\LoginController;
use App\Http\Controllers\Portal\SetupController;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('portal')->name('portal.')->group(function () {
    Route::middleware('guest:applicant')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('login.store');
        Route::get('setup', [SetupController::class, 'create'])->name('setup');
        Route::post('setup', [SetupController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('setup.store');
    });

    Route::middleware('auth:applicant')->group(function () {
        Route::view('/', 'portal.placeholder')->name('home');
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    });
});

