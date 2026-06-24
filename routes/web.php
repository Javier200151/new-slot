<?php

use App\Http\Controllers\PublicRegisterController;
use Illuminate\Support\Facades\Route;
use App\Models\User;

Route::get('/firmas/{nick}.html', function ($nick) {
    $user = User::where('nick', $nick)
        ->with(['promo', 'metopas'])
        ->firstOrFail();

    return view('firmas.show', compact('user'));
});

Route::get('/', function () {
    return view('home');
});

Route::get('/register', [PublicRegisterController::class, 'show'])
    ->name('public.register');

Route::post('/register', [PublicRegisterController::class, 'store'])
    ->name('public.register.store');