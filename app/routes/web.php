<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:60,1', 'secure.headers'])->group(function () {
    Route::get('/', function () {
        return view('welcome');
    });
});