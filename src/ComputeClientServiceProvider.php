<?php
namespace Elytica\ComputeClient;

use Illuminate\Support\ServiceProvider;

class ComputeClientServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(ComputeService::class, function ($app) {
            return new ComputeService();
        });
    }

    public function boot()
    {
        //
    }
}

