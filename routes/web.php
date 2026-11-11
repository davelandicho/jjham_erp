<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('portal')->name('portal.')->group(function() {
    Route::view('/', 'portal.placeholder')->name('home');
});
