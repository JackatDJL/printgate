<?php

namespace App\Providers;

use App\Printing\Contracts\PrintBackend;
use App\Printing\CupsPrintBackend;
use App\Printing\PrintgateSettings;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PrintBackend::class, CupsPrintBackend::class);
        $this->app->singleton(PrintgateSettings::class, fn (): PrintgateSettings => PrintgateSettings::fromConfig(config('printgate')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
