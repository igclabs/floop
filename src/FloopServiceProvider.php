<?php

namespace IgcLabs\Floop;

use IgcLabs\Floop\Console\Commands\FloopActionCommand;
use IgcLabs\Floop\Console\Commands\FloopClearCommand;
use IgcLabs\Floop\Console\Commands\FloopDisableCommand;
use IgcLabs\Floop\Console\Commands\FloopEnableCommand;
use IgcLabs\Floop\Console\Commands\FloopListCommand;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class FloopServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/floop.php', 'floop');

        $this->app->singleton(FloopManager::class, function ($app) {
            return new FloopManager(
                config('floop.storage_path', storage_path('app/feedback'))
            );
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/floop.php');

        $this->loadViewsFrom(__DIR__.'/Views', 'floop');

        $this->publishes([
            __DIR__.'/../config/floop.php' => config_path('floop.php'),
        ], 'floop-config');

        Blade::directive('floop', function () {
            return "<?php echo view('floop::widget')->render(); ?>";
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                FloopListCommand::class,
                FloopActionCommand::class,
                FloopClearCommand::class,
                FloopEnableCommand::class,
                FloopDisableCommand::class,
            ]);
        }
    }
}
