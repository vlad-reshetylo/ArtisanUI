<?php

namespace VladReshet\ArtisanUi;

use Illuminate\Support\ServiceProvider;
use VladReshet\ArtisanUi\Console\Commands\Ui;

class ArtisanUiServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/config/config.php' => config_path('artisanui.php'),
        ], 'artisanui');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Ui::class,
            ]);
        }
    }
}
