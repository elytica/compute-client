<?php
namespace Elytica\ComputeClient;

use Illuminate\Support\ServiceProvider;

class ComputeClientServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/compute.php', 'compute');

        $this->app->singleton(ComputeService::class, function ($app) {
            return new ComputeService(
                config('compute.token'),
                config('compute.base_url')
            );
        });
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/compute.php' => config_path('compute.php'),
            ], 'compute-config');
        }
    }
}
