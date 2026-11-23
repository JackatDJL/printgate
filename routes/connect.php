<?php

use App\Http\Controllers\Connect\PairingController;
use App\Http\Controllers\Connect\SiteController;
use App\Http\Middleware\AuthenticateConnectSite;
use App\Http\Middleware\RequireConnectHubRole;
use Illuminate\Support\Facades\Route;

Route::middleware(RequireConnectHubRole::class)->group(function () {
    Route::middleware('auth')->prefix('connect')->name('connect.')->group(function () {
        Route::get('/', [PairingController::class, 'index'])->name('index');
        Route::post('/invitations', [PairingController::class, 'invite'])->middleware('throttle:5,1')->name('invitations.store');
        Route::post('/claims/{claim}/confirm', [PairingController::class, 'confirm'])->middleware('throttle:10,1')->name('claims.confirm');
        Route::delete('/sites/{site}', [SiteController::class, 'revoke'])->name('sites.revoke');
    });

    Route::prefix('api/connect')->name('connect.api.')->middleware('throttle:10,1')->group(function () {
        Route::post('/pairings', [PairingController::class, 'begin'])->name('pairings.begin');
        Route::post('/pairings/{claim}/credential', [PairingController::class, 'delivery'])->name('pairings.delivery');
        Route::middleware(AuthenticateConnectSite::class)->get('/site', function (\Illuminate\Http\Request $request) {
            $site = $request->attributes->get('connect.site');

            return response()->json([
                'site_id' => $site->id,
                'owner_id' => $site->owner_id,
                'user_id' => $site->owner_id,
                'name' => $site->name,
            ]);
        })->name('site');
    });
});
