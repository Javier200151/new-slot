<?php

use App\Http\Controllers\Api\PublicCatalogApiController as Catalog;
use App\Http\Controllers\Api\PublicEventsApiController as Events;
use App\Http\Controllers\Api\PublicMembersApiController as Members;
use App\Http\Controllers\Api\PublicMediaApiController as Media;
use App\Http\Controllers\Api\PublicStatisticsApiController as Statistics;
use App\Http\Middleware\ForcePublicApiJson;
use Illuminate\Support\Facades\Route;

/* All GET endpoints in this file are anonymous JSON-only and expose public fields only. */
Route::middleware([ForcePublicApiJson::class, 'throttle:60,1'])->group(function (): void {
    Route::get('/eventos', [Events::class,'index'])->name('api.events.index');
    Route::get('/eventos/{id}', [Events::class,'show'])->whereNumber('id')->name('api.events.show');
    Route::get('/eventos/{id}/orbat', [Events::class,'orbat'])->whereNumber('id')->name('api.events.orbat');
    Route::get('/eventos/{id}/participantes', [Events::class,'participants'])->whereNumber('id');
    Route::get('/eventos/{id}/multimedia', [Events::class,'media'])->whereNumber('id');
    Route::get('/eventos/{id}/resultado', [Events::class,'result'])->whereNumber('id');

    Route::get('/usuarios', [Members::class,'index']);
    Route::get('/usuarios/{nick}', [Members::class,'show']);
    Route::get('/usuarios/{nick}/metopas', [Members::class,'metopas']);
    Route::get('/usuarios/{nick}/eventos', [Members::class,'events']);
    Route::get('/grupos', [Members::class,'groups']);
    Route::get('/grupos/{id}', [Members::class,'group'])->whereNumber('id');
    Route::get('/grupos/{id}/miembros', [Members::class,'groupMembers'])->whereNumber('id');

    Route::get('/actividades', [Catalog::class,'activities']);
    Route::get('/actividades/{id}', [Catalog::class,'activity'])->whereNumber('id');
    Route::get('/actividades/{id}/eventos', [Catalog::class,'activityEvents'])->whereNumber('id');
    Route::get('/tipos-actividad', [Catalog::class,'activityTypes']);
    Route::get('/estados/eventos', [Catalog::class,'eventStates']);
    Route::get('/estados/usuarios', [Catalog::class,'userStates']);
    Route::get('/mapas', [Catalog::class,'maps']);
    Route::get('/mapas/{id}', [Catalog::class,'map'])->whereNumber('id');
    Route::get('/plataformas', [Catalog::class,'platforms']);
    Route::get('/facciones', [Catalog::class,'factions']);
    Route::get('/tipos-slot', [Catalog::class,'slotTypes']);
    Route::get('/periodos', [Catalog::class,'periods']);

    Route::get('/campanas', [Catalog::class,'campaigns']);
    Route::get('/campanas/{id}', [Catalog::class,'campaign'])->whereNumber('id');
    Route::get('/campanas/{id}/eventos', [Catalog::class,'campaignEvents'])->whereNumber('id');
    Route::get('/campanas/{id}/aar', [Catalog::class,'campaignAars'])->whereNumber('id');
    Route::get('/metopas', [Catalog::class,'metopas']);
    Route::get('/metopas/{id}', [Catalog::class,'metopa'])->whereNumber('id');
    Route::get('/metopas/{id}/usuarios', [Catalog::class,'metopaMembers'])->whereNumber('id');

    Route::get('/clips', [Media::class,'clips']);
    Route::get('/clips/{id}', [Media::class,'clip'])->whereNumber('id');
    Route::get('/clips/{id}/comentarios', [Media::class,'comments'])->whereNumber('id');
    Route::get('/vods', [Media::class,'vods']);
    Route::get('/vods/{id}', [Media::class,'vod'])->whereNumber('id');
    Route::get('/directos', [Media::class,'streams']);
    Route::get('/streamers', [Media::class,'streamers']);
    Route::get('/clip-awards/ediciones', [Media::class,'editions']);
    Route::get('/clip-awards/ganadores', [Media::class,'winners']);
    Route::get('/actualidad', [Media::class,'news']);
    Route::get('/actualidad/{id}', [Media::class,'newsItem'])->whereNumber('id');

    Route::get('/estadisticas', [Statistics::class,'overview']);
    Route::get('/estadisticas/eventos', [Statistics::class,'events']);
    Route::get('/estadisticas/usuarios', [Statistics::class,'users']);
    Route::get('/estadisticas/actividades', [Statistics::class,'activities']);

    // Existing integrations stay compatible with the path and only receive public data.
    Route::get('/users', [Members::class,'index'])->name('api.users.index');
    Route::get('/users/{identifier}', [Members::class,'legacy'])->name('api.users.show');
});
