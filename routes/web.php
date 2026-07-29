<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicLoginController;
use App\Http\Controllers\PublicRegisterController;
use App\Models\Metopa;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Route;

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
*/

Route::get('/register', function () {
    return redirect()->route('home', [
        'modal' => 'register',
    ]);
})->name('public.register');

Route::post(
    '/register',
    [PublicRegisterController::class, 'store'],
)->name('public.register.store');

/*
|--------------------------------------------------------------------------
| Inicio de sesión
|--------------------------------------------------------------------------
*/

Route::get('/login', function () {
    return redirect()->route('home', [
        'modal' => 'login',
    ]);
})->name('login');

Route::post(
    '/login',
    [PublicLoginController::class, 'login'],
)->name('public.login');

Route::post(
    '/logout',
    [PublicLoginController::class, 'logout'],
)->name('logout');

/*
|--------------------------------------------------------------------------
| Perfil y verificación de correo
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/perfil',
        [ProfileController::class, 'show'],
    )->name('profile.show');

    Route::patch(
        '/perfil',
        [ProfileController::class, 'update'],
    )->name('profile.update');

    Route::put(
        '/perfil/password',
        [ProfileController::class, 'updatePassword'],
    )->name('profile.password.update');

    Route::delete(
        '/perfil/image',
        [ProfileController::class, 'deleteImage'],
    )->name('profile.image.delete');

    Route::get('/email/verify', function () {
        return redirect()
            ->route('profile.show')
            ->with(
                'warning',
                'Debes verificar tu correo electrónico.',
            );
    })->name('verification.notice');

    Route::get(
        '/email/verify/{id}/{hash}',
        function (EmailVerificationRequest $request) {
            $request->fulfill();

            return redirect()
                ->route('profile.show')
                ->with('status', 'email-verified');
        },
    )
        ->middleware('signed')
        ->name('verification.verify');

    Route::post(
        '/email/verification-notification',
        [ProfileController::class, 'sendVerification'],
    )
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

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
        ->with([
            'promo',
            'metopas',
        ])
        ->firstOrFail();

    return view('firmas.show', compact('user'));
})->name('firmas.show');