<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        /*
        |--------------------------------------------------------------------------
        | Force HTTPS in Production
        |--------------------------------------------------------------------------
        |
        | This is a safety net for Render production deployment.
        | Local development still uses HTTP because APP_ENV=local.
        |
        */

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}