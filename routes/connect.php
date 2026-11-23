<?php

use App\Http\Controllers\Connect\PairingController;
use App\Http\Controllers\Connect\PrintJobController;
use App\Http\Controllers\Connect\SiteController;
use App\Http\Controllers\Connect\TransportController;
use App\Http\Middleware\AuthenticateConnectSite;
use App\Http\Middleware\RequireConnectHubRole;
use Illuminate\Support\Facades\Route;

Route::middleware(RequireConnectHubRole::class)->group(function () {
    Route::middleware('auth')->prefix('connect')->name('connect.')->group(function () {
        Route::get('/', [PairingController::class, 'index'])->name('index');
        Route::post('/invitations', [PairingController::class, 'invite'])->middleware('throttle:5,1')->name('invitations.store');
        Route::post('/claims/{claim}/confirm', [PairingController::class, 'confirm'])->middleware('throttle:10,1')->name('claims.confirm');
        Route::delete('/sites/{site}', [SiteController::class, 'revoke'])->name('sites.revoke');
        Route::post('/sites/{site}/jobs', [PrintJobController::class, 'store'])->name('sites.jobs.store');
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

    Route::prefix('api/connect/v1')->name('connect.api.v1.')->middleware(['throttle:120,1', AuthenticateConnectSite::class])->group(function () {
        Route::post('/poll', [TransportController::class, 'poll'])->name('poll');
        Route::get('/jobs/{id}/document', [TransportController::class, 'document'])->name('jobs.document');
        Route::post('/jobs/{id}/status', [TransportController::class, 'status'])->name('jobs.status');
    });
});
