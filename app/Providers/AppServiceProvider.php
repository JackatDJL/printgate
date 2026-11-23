<?php

namespace App\Providers;

use App\Printing\Contracts\PrintBackend;
use App\Printing\CupsPrintBackend;
use App\Printing\PrintgateSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by(Str::transliterate(
                Str::lower($request->string('email')).'|'.$request->ip(),
            ));
        });
    }
}
