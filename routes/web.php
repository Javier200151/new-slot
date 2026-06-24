<?php

use App\Http\Controllers\PublicRegisterController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/register', [PublicRegisterController::class, 'show'])
    ->name('public.register');

Route::post('/register', [PublicRegisterController::class, 'store'])
    ->name('public.register.store');