<?php

namespace App\Providers;

use App\Models\TcPosition;
use App\Observers\TcPositionObserver;
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
        TcPosition::observe(TcPositionObserver::class);
    }
}
