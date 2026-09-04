<?php

use App\Http\Controllers\MetopaController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PublicContactController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\CommunityDiaryController;
use App\Http\Controllers\CommunityForumController;
use App\Http\Controllers\CommunityPollController;
use App\Http\Controllers\CommunityProcessController;
use App\Http\Controllers\CommunitySubscriptionController;
use App\Http\Controllers\CommunityRouletteController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicCampaignController;
use App\Http\Controllers\CampaignAarController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\PublicLoginController;
use App\Http\Controllers\PublicMapController;
use App\Http\Controllers\PublicRegisterController;
use App\Http\Controllers\PublicActivityController;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PublicUserController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PublicStreamerController;
use App\Http\Controllers\StreamerBroadcastController;

/*
|--------------------------------------------------------------------------
| Página principal
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::post('/contacto', [PublicContactController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('public.contact.store');

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
        '/notificaciones/poll',
        [NotificationController::class, 'poll'],
    )->name('notifications.poll');

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

Route::get('/eventos/{event}/ruleta-estado', [PublicEventController::class, 'rouletteLockState'])
    ->name('events.roulette-lock-state');

Route::get('/eventos/{event}', [PublicEventController::class, 'show'])
    ->name('events.show');

/*
|--------------------------------------------------------------------------
| Actividades públicas
|--------------------------------------------------------------------------
*/

Route::get(
    '/actividades',
    [PublicActivityController::class, 'index']
)->name('activities.index');

Route::get(
    '/actividades/{activity}',
    [PublicActivityController::class, 'show']
)->name('activities.show');

/*
|--------------------------------------------------------------------------
| Compatibilidad con URLs históricas de Operativos
|--------------------------------------------------------------------------
*/

Route::get('/operativos', function () {
    return redirect()->route(
        'activities.index',
        request()->query(),
        301,
    );
})->name('operations.index');

Route::get('/operativos/{operation}', function (string $operation) {
    return redirect()->route(
        'activities.show',
        ['activity' => $operation],
        301,
    );
})->name('operations.show');

/*
|--------------------------------------------------------------------------
| Compatibilidad con URLs históricas de Filament
|--------------------------------------------------------------------------
*/

Route::redirect('/admin/operations', '/admin/activities', 301);
Route::redirect('/admin/operations/create', '/admin/activities/create', 301);
Route::get('/admin/operations/{record}/edit', fn (string $record) =>
    redirect("/admin/activities/{$record}/edit", 301)
);

Route::redirect(
    '/admin/configuration/operation-types',
    '/admin/configuration/activity-types',
    301,
);
Route::redirect(
    '/admin/configuration/operation-types/create',
    '/admin/configuration/activity-types/create',
    301,
);
Route::get(
    '/admin/configuration/operation-types/{record}/edit',
    fn (string $record) => redirect(
        "/admin/configuration/activity-types/{$record}/edit",
        301,
    ),
);

Route::redirect(
    '/admin/configuration/operation-statuses',
    '/admin/configuration/activity-statuses',
    301,
);
Route::redirect(
    '/admin/configuration/operation-statuses/create',
    '/admin/configuration/activity-statuses/create',
    301,
);
Route::get(
    '/admin/configuration/operation-statuses/{record}/edit',
    fn (string $record) => redirect(
        "/admin/configuration/activity-statuses/{$record}/edit",
        301,
    ),
);

Route::redirect(
    '/admin/configuration/operation-days',
    '/admin/configuration/activity-days',
    301,
);
Route::redirect(
    '/admin/configuration/operation-days/create',
    '/admin/configuration/activity-days/create',
    301,
);
Route::get(
    '/admin/configuration/operation-days/{record}/edit',
    fn (string $record) => redirect(
        "/admin/configuration/activity-days/{$record}/edit",
        301,
    ),
);

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

        /*
    |--------------------------------------------------------------------------
    | Multimedia del evento
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/eventos/{event}/multimedia',
        [PublicEventController::class, 'storeMedia']
    )->name('events.media.store');

    Route::delete(
        '/eventos/{event}/multimedia/{eventMedia}',
        [PublicEventController::class, 'destroyMedia']
    )->name('events.media.destroy');
});

Route::get('/mapas/{map}', [PublicMapController::class, 'show'])
    ->name('maps.show');

Route::get('/campanas', [PublicCampaignController::class, 'index'])
    ->name('campaigns.index');

Route::get('/campanas/{campaign}', [PublicCampaignController::class, 'show'])
    ->name('campaigns.show');

Route::get('/campanas/{campaign}/aar', [CampaignAarController::class, 'index'])
    ->name('campaigns.aars.index');

Route::get('/campanas/{campaign}/aar/{event}', [CampaignAarController::class, 'show'])
    ->name('campaigns.aars.show');

Route::middleware('auth')->group(function (): void {
    Route::put(
        '/campanas/{campaign}/aar/{event}',
        [CampaignAarController::class, 'update'],
    )->name('campaigns.aars.update');
});

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
})
    ->where('nick', '[^/]+')
    ->name('firmas.show');
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
| Directos
|--------------------------------------------------------------------------
*/

Route::get(
    '/directos',
    [PublicStreamerController::class, 'index']
)->name('streams.index');

Route::get(
    '/directos/estado',
    [PublicStreamerController::class, 'status']
)->name('streams.status');

Route::middleware([
    'auth',
    'verified',
])->group(function (): void {

    Route::put(
        '/directos/mi-emision',
        [StreamerBroadcastController::class, 'update']
    )->name('streams.mine.update');

    Route::delete(
        '/directos/mi-emision/{event}',
        [StreamerBroadcastController::class, 'destroy']
    )->name('streams.mine.destroy');
});

/*
|--------------------------------------------------------------------------
| Comunidad y Área privada
|--------------------------------------------------------------------------
*/

Route::get(
    '/comunidad/organigrama',
    [CommunityController::class, 'organization']
)->name('community.organization');

Route::middleware('auth')->group(function (): void {
    Route::get(
        '/area/foro',
        [CommunityForumController::class, 'forum']
    )->name('community.forum.home');

    Route::get(
        '/area/diario',
        [CommunityDiaryController::class, 'index']
    )
        ->name('community.diary.index');

    Route::post(
        '/area/diario/iniciar',
        [CommunityDiaryController::class, 'start']
    )
        ->name('community.diary.start');

    Route::get(
        '/area/diario/eventos/{event}/escuadra',
        [CommunityDiaryController::class, 'eventSquad']
    )
        ->whereNumber('event')
        ->name('community.diary.event-squad');

    Route::get(
        '/area/diario/{diary}',
        [CommunityDiaryController::class, 'show']
    )
        ->whereNumber('diary')
        ->name('community.diary.show');

    Route::post(
        '/area/diario',
        [CommunityDiaryController::class, 'store']
    )
        ->name('community.diary.store');

    Route::patch(
        '/area/diario/entradas/{entry}',
        [CommunityDiaryController::class, 'update']
    )
        ->name('community.diary.update');

    Route::delete(
        '/area/diario/entradas/{entry}',
        [CommunityDiaryController::class, 'destroy']
    )
        ->name('community.diary.destroy');

    Route::post(
        '/area/diario/{diary}/entradas/{entry}/respuestas',
        [CommunityDiaryController::class, 'comment']
    )
        ->whereNumber('diary')
        ->whereNumber('entry')
        ->name('community.diary.comments.store');

    Route::patch(
        '/area/diario/{diary}/respuestas/{comment}',
        [CommunityDiaryController::class, 'updateComment']
    )
        ->whereNumber('diary')
        ->whereNumber('comment')
        ->name('community.diary.comments.update');

    Route::delete(
        '/area/diario/{diary}/respuestas/{comment}',
        [CommunityDiaryController::class, 'destroyComment']
    )
        ->whereNumber('diary')
        ->whereNumber('comment')
        ->name('community.diary.comments.destroy');

    Route::get(
        '/area/foro/{channel}',
        [CommunityForumController::class, 'index']
    )
        ->whereIn('channel', ['cantina', 'personal'])
        ->name('community.forum.index');

    Route::post(
        '/area/foro/{channel}',
        [CommunityForumController::class, 'store']
    )
        ->whereIn('channel', ['cantina', 'personal'])
        ->name('community.forum.store');

    Route::get(
        '/area/foro/categoria/{category}',
        [CommunityForumController::class, 'category']
    )
        ->where('category', '[A-Za-z0-9-]+')
        ->name('community.forum.category');

    Route::post(
        '/area/foro/categoria/{category}',
        [CommunityForumController::class, 'storeCategory']
    )
        ->where('category', '[A-Za-z0-9-]+')
        ->name('community.forum.category.store');

    Route::get(
        '/area/foro/{channel}/{post}',
        [CommunityForumController::class, 'show']
    )
        ->whereIn('channel', ['cantina', 'personal'])
        ->name('community.forum.show');

    Route::patch(
        '/area/foro/{channel}/{post}',
        [CommunityForumController::class, 'update']
    )
        ->whereIn('channel', ['cantina', 'personal'])
        ->name('community.forum.update');

    Route::delete(
        '/area/foro/{channel}/{post}',
        [CommunityForumController::class, 'destroy']
    )
        ->whereIn('channel', ['cantina', 'personal'])
        ->name('community.forum.destroy');

    Route::patch(
        '/area/foro/{channel}/{post}/estado',
        [CommunityForumController::class, 'toggleLock']
    )
        ->whereIn('channel', ['cantina', 'personal'])
        ->name('community.forum.lock');

    Route::patch(
        '/area/foro/{channel}/{post}/fijado',
        [CommunityForumController::class, 'togglePin']
    )
        ->whereIn('channel', ['cantina', 'personal'])
        ->name('community.forum.pin');

    Route::post(
        '/area/foro/{channel}/{post}/respuestas',
        [CommunityForumController::class, 'comment']
    )
        ->whereIn('channel', ['cantina', 'personal'])
        ->name('community.forum.comments.store');

    Route::patch(
        '/area/foro/{channel}/{post}/respuestas/{comment}',
        [CommunityForumController::class, 'updateComment']
    )
        ->whereIn('channel', ['cantina', 'personal'])
        ->name('community.forum.comments.update');

    Route::delete(
        '/area/foro/{channel}/{post}/respuestas/{comment}',
        [CommunityForumController::class, 'destroyComment']
    )
        ->whereIn('channel', ['cantina', 'personal'])
        ->name('community.forum.comments.destroy');

    Route::patch(
        '/area/personal/procesos/{process}',
        [CommunityProcessController::class, 'update']
    )
        ->whereNumber('process')
        ->name('community.processes.update');

    Route::post(
        '/area/personal/procesos/{process}/postulacion',
        [CommunityProcessController::class, 'apply']
    )
        ->whereNumber('process')
        ->name('community.processes.apply');

    Route::delete(
        '/area/personal/procesos/{process}/postulacion',
        [CommunityProcessController::class, 'withdraw']
    )
        ->whereNumber('process')
        ->name('community.processes.withdraw');

    Route::post(
        '/area/foro/personal/{post}/votacion',
        [CommunityPollController::class, 'storeForPost']
    )
        ->whereNumber('post')
        ->name('community.polls.store-for-post');

    Route::post(
        '/area/suscripciones/{type}/{id}',
        [CommunitySubscriptionController::class, 'toggle']
    )
        ->whereIn('type', ['hilo', 'diario'])
        ->whereNumber('id')
        ->name('community.subscriptions.toggle');

    Route::get(
        '/area/votaciones',
        [CommunityPollController::class, 'index']
    )
        ->name('community.polls.index');

    Route::post(
        '/area/votaciones/{poll}/votar',
        [CommunityPollController::class, 'vote']
    )
        ->name('community.polls.vote');


    /*
    |--------------------------------------------------------------------------
    | Ruleta de responsabilidad
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/area/ruleta',
        [CommunityRouletteController::class, 'index']
    )->name('community.roulette.index');

    Route::get(
        '/area/ruleta/crear',
        [CommunityRouletteController::class, 'create']
    )->name('community.roulette.create');

    Route::post(
        '/area/ruleta',
        [CommunityRouletteController::class, 'store']
    )->name('community.roulette.store');

    Route::get(
        '/area/ruleta/salas/{room}',
        [CommunityRouletteController::class, 'show']
    )->whereNumber('room')->name('community.roulette.show');

    Route::get(
        '/area/ruleta/salas/{room}/estado',
        [CommunityRouletteController::class, 'state']
    )->whereNumber('room')->name('community.roulette.state');

    Route::patch(
        '/area/ruleta/salas/{room}',
        [CommunityRouletteController::class, 'update']
    )->whereNumber('room')->name('community.roulette.update');

    Route::post(
        '/area/ruleta/salas/{room}/girar',
        [CommunityRouletteController::class, 'spin']
    )->whereNumber('room')->name('community.roulette.spin');

    Route::post(
        '/area/ruleta/salas/{room}/repetir',
        [CommunityRouletteController::class, 'repeat']
    )->whereNumber('room')->name('community.roulette.repeat');

    Route::delete(
        '/area/ruleta/salas/{room}',
        [CommunityRouletteController::class, 'destroy']
    )->whereNumber('room')->name('community.roulette.destroy');
});

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
