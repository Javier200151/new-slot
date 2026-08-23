<?php

use App\Http\Controllers\MetopaController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicCampaignController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\PublicLoginController;
use App\Http\Controllers\PublicMapController;
use App\Http\Controllers\PublicRegisterController;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PublicUserController;
use App\Http\Controllers\PasswordResetController;

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
| Recuperación de contraseña
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function (): void {

    Route::get('/forgot-password', function () {
        return redirect()->route('home', [
            'modal' => 'forgot-password',
        ]);
    })->name('password.request');

    Route::post(
        '/forgot-password',
        [PasswordResetController::class, 'sendLink'],
    )
        ->middleware('throttle:5,1')
        ->name('password.email');

    Route::get(
        '/reset-password/{token}',
        [PasswordResetController::class, 'show'],
    )->name('password.reset');

    Route::post(
        '/reset-password',
        [PasswordResetController::class, 'reset'],
    )->name('password.update');
});

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

Route::middleware('auth')->group(function (): void {

    Route::post(
        '/notificaciones/leer-todas',
        [NotificationController::class, 'readAll'],
    )->name('notifications.read-all');

    Route::get(
        '/notificaciones/{notification}/abrir',
        [NotificationController::class, 'open'],
    )->name('notifications.open');

});

/*
|--------------------------------------------------------------------------
| Metopas públicas
|--------------------------------------------------------------------------
*/

Route::get('/metopas', [MetopaController::class, 'index'])
    ->name('metopas.index');

Route::get('/metopas/{metopa}', [MetopaController::class, 'show'])
    ->name('metopas.show');

/*
|--------------------------------------------------------------------------
| Eventos públicos
|--------------------------------------------------------------------------
*/

Route::get('/eventos', [PublicEventController::class, 'index'])
    ->name('events.index');

Route::get('/eventos/{event}', [PublicEventController::class, 'show'])
    ->name('events.show');

/*
|--------------------------------------------------------------------------
| Acciones de eventos para usuarios verificados
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'verified',
])->group(function (): void {

    Route::post(
        '/eventos/{event}/slots/{slotKey}',
        [PublicEventController::class, 'registerSlot']
    )->name('events.slots.register');

    Route::delete(
        '/eventos/{event}/slots/{slotKey}',
        [PublicEventController::class, 'unregisterSlot']
    )->name('events.slots.unregister');

    Route::patch(
        '/eventos/{event}/slots/{slotKey}/manage',
        [PublicEventController::class, 'manageSlot']
    )->name('events.slots.manage');

    Route::post(
        '/eventos/{event}/comentarios',
        [PublicEventController::class, 'storeComment']
    )->name('events.comments.store');

    Route::patch(
        '/eventos/{event}/comentarios/{eventComment}',
        [PublicEventController::class, 'updateComment']
    )->name('events.comments.update');
});

Route::get('/mapas/{map}', [PublicMapController::class, 'show'])
    ->name('maps.show');

Route::get('/campanas/{campaign}', [PublicCampaignController::class, 'show'])
    ->name('campaigns.show');

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
/*
|--------------------------------------------------------------------------
| Usuarios públicos
|--------------------------------------------------------------------------
*/

Route::get(
    '/usuarios',
    [PublicUserController::class, 'index']
)->name('users.index');

Route::get(
    '/usuarios/{user:nick}',
    [PublicUserController::class, 'show']
)->name('users.show');
/*
|--------------------------------------------------------------------------
| Páginas públicas
|--------------------------------------------------------------------------
|
| Esta ruta debe permanecer al final para no interferir con las rutas
| específicas de la aplicación.
|
*/

Route::get('/{page:slug}', [PageController::class, 'show'])
    ->name('pages.show');
