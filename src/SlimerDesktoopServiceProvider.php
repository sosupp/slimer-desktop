<?php
namespace Sosupp\SlimerDesktop;

use Illuminate\Support\ServiceProvider;
use Sosupp\SlimerDesktop\Console\DesktopBuild;
use Sosupp\SlimerDesktop\Console\DesktopPrep;
use Sosupp\SlimerDesktop\Console\DesktopShip;

class SlimerDesktoopServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register()
    {
        // Register bindings, singletons, etc.
        // $this->app->singleton(TenantResolverService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        // Publish config, migrations, routes, etc.

        if($this->app->runningInConsole()){
            $this->publishes([
                __DIR__.'/../config/config.php' => config_path('slimertenancy.php'),
            ], 'slimer-tenancy-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'slimer-landlord-migrations');

            // Commands
            $this->customCommands();
        }
    }

    protected function customCommands()
    {
        $this->commands([
            DesktopBuild::class,
            DesktopShip::class,
            DesktopPrep::class,
        ]);
    }


}
