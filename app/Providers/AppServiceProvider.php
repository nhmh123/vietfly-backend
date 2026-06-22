<?php

namespace App\Providers;

use App\Adapters\DatacomAdapter;
use App\Contracts\FlightProviderInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            FlightProviderInterface::class,
            DatacomAdapter::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
