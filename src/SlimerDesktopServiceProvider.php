<?php
namespace Sosupp\SlimerDesktop;

use Illuminate\Support\ServiceProvider;
use Sosupp\SlimerDesktop\Console\DesktopBuild;
use Sosupp\SlimerDesktop\Console\DesktopInstall;
use Sosupp\SlimerDesktop\Console\DesktopPrep;
use Sosupp\SlimerDesktop\Console\DesktopShip;
use Sosupp\SlimerDesktop\Http\Middleware\VerifyRemoteSyncToken;

class SlimerDesktopServiceProvider extends ServiceProvider
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
                __DIR__.'/../config/config.php' => config_path('slimerdesktop.php'),
            ], 'slimer-desktop-config');


            $path = config('slimertenancy.enabled') ? 'migrations/tenant' : 'migrations';

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path($path),
            ], 'slimer-desktop-migrations');

            // Commands
            $this->customCommands();
        }

        // Routes
        $this->loadRoutesFrom(__DIR__. '/../routes/api.php');

        // Middleware
        $router = $this->app['router'];
        $router->aliasMiddleware('remote.verify', VerifyRemoteSyncToken::class);
        
    }

    protected function customCommands()
    {
        $this->commands([
            DesktopBuild::class,
            DesktopShip::class,
            DesktopPrep::class,
            DesktopInstall::class,
        ]);
    }


}
