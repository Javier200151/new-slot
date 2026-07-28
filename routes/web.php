<?php

use App\Http\Controllers\PublicRegisterController;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\PublicLoginController;
use App\Models\Metopa;

/*
|--------------------------------------------------------------------------
| Página principal
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('home');
})->name('home');

/*
|--------------------------------------------------------------------------
| Registro público
|--------------------------------------------------------------------------
|
| La ruta GET ya no muestra una página independiente.
| Redirige al inicio indicando que debe abrirse el modal de registro.
|
*/

Route::get('/register', function () {
    return redirect()->route('home', [
        'modal' => 'register',
    ]);
})->name('public.register');

Route::post('/register', [PublicRegisterController::class, 'store'])
    ->name('public.register.store');

/*
|--------------------------------------------------------------------------
| Inicio de sesión público
|--------------------------------------------------------------------------
|
| La ruta GET redirige al inicio y abre el modal de login.
| La ruta POST continúa usando el controlador actual.
|
*/

Route::get('/login', function () {
    return redirect()->route('home', [
        'modal' => 'login',
    ]);
})->name('login');

Route::post('/login', [PublicLoginController::class, 'login'])
    ->name('public.login');

Route::post('/logout', [PublicLoginController::class, 'logout'])
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Metopas públicas
|--------------------------------------------------------------------------
*/

Route::get('/metopas/{metopa}', function (Metopa $metopa) {
    return view('metopas.show', compact('metopa'));
})->name('metopas.show');

/*
|--------------------------------------------------------------------------
| Firmas públicas
|--------------------------------------------------------------------------
*/

Route::get('/firmas/{nick}.html', function ($nick) {

    $user = User::where('nick', $nick)
        ->with(['promo', 'metopas'])
        ->firstOrFail();

    return view('firmas.show', compact('user'));

})->name('firmas.show');