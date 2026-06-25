<?php

use App\Http\Controllers\PublicRegisterController;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\PublicLoginController;
use App\Models\Metopa;

Route::get('/', function () {
    return view('home');
});

Route::get('/register', [PublicRegisterController::class, 'show'])
    ->name('public.register');

Route::post('/register', [PublicRegisterController::class, 'store'])
    ->name('public.register.store');

Route::get('/login', [PublicLoginController::class, 'show'])
    ->name('login');

Route::post('/login', [PublicLoginController::class, 'login'])
    ->name('public.login');

Route::post('/logout', [PublicLoginController::class, 'logout'])
    ->name('logout');

Route::get('/metopas/{metopa}', function (Metopa $metopa) {
    return view('metopas.show', compact('metopa'));
})->name('metopas.show');

Route::get('/firmas/{nick}.html', function ($nick) {

    $user = User::where('nick', $nick)
        ->with(['promo', 'metopas'])
        ->firstOrFail();

    return view('firmas.show', compact('user'));

})->name('firmas.show');
