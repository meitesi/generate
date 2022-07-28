<?php

namespace Meitesi\Generate;

use Illuminate\Support\ServiceProvider;
use Meitesi\Generate\Commands\Gen;

class GenerateServiceProvider extends ServiceProvider
{
    protected $defer = true;
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/Stubs' => resource_path('stubs'),
        ], 'public');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('command.luyuan.gen', function () {
            return new Gen;
        });
        $this->commands(['command.luyuan.gen']);
    }


    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['command.luyuan.gen'];
    }

}