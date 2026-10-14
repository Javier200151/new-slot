<?php

use App\Http\Controllers\WikiOidcController;
use Illuminate\Support\Facades\Route;

Route::get('/.well-known/openid-configuration', [WikiOidcController::class, 'discovery'])->name('wiki.oidc.discovery');
Route::get('/oauth/jwks', [WikiOidcController::class, 'jwks'])->name('wiki.oidc.jwks');
Route::get('/oauth/authorize', [WikiOidcController::class, 'authorize'])->middleware('throttle:30,1')->name('wiki.oidc.authorize');
Route::post('/oauth/token', [WikiOidcController::class, 'token'])->middleware('throttle:30,1')->name('wiki.oidc.token');
Route::get('/oauth/userinfo', [WikiOidcController::class, 'userinfo'])->middleware('throttle:60,1')->name('wiki.oidc.userinfo');
