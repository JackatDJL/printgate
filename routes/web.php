<?php

use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/', fn () => config('printgate.role') === 'hub'
        ? redirect()->route('connect.index')
        : view('dashboard'))->name('dashboard');
    Route::livewire('/print-jobs/new', 'pages::print-jobs.create')->name('print-jobs.create');
});

require __DIR__.'/connect.php';
