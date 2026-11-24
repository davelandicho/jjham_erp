<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Portal\LoginController;
use App\Http\Controllers\Portal\SetupController;
use App\Http\Controllers\Portal\OtpController;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('portal')->name('portal.')->group(function () {
    Route::middleware('guest:applicant')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])
            ->middleware('throttle:5,1,portal-login')
            ->name('login.store');
        Route::get('login/verify', [OtpController::class, 'create'])->name('otp');
        Route::post('login/verify', [OtpController::class, 'store'])
            ->middleware('throttle:10,1,portal-otp')
            ->name('otp.store');
        Route::post('login/verify/resend', [OtpController::class, 'resend'])
            ->middleware('throttle:3,10,portal-resend')
            ->name('otp.resend');
        Route::get('setup', [SetupController::class, 'create'])->name('setup');
        Route::post('setup', [SetupController::class, 'store'])
            ->middleware('throttle:5,1,portal-setup')
            ->name('setup.store');
    });

    Route::middleware('auth:applicant')->group(function () {
        Route::view('/', 'portal.placeholder')->name('home');
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
    });
});

