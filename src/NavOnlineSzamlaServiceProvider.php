<?php

declare(strict_types=1);

namespace Intragile\NavOnlineSzamla;

use Illuminate\Support\ServiceProvider;

final class NavOnlineSzamlaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nav-online-szamla.php', 'nav-online-szamla');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/nav-online-szamla.php' => config_path('nav-online-szamla.php'),
            ], 'nav-online-szamla-config');
        }
    }
}
