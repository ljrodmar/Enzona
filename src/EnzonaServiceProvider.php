<?php

namespace Macroseso\Enzona;

use Illuminate\Support\ServiceProvider;
use Macroseso\Enzona\Enzona;

class EnzonaServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publicar archivo de configuración
        $this->publishes([
            __DIR__.'/../config/enzona.php' => config_path('enzona.php'),
        ], 'enzona-config'); // El segundo parámetro es el "tag"
    }

    public function register()
    {
        $this->app->bind(Enzona::class, function () {
            return new Enzona();
        });
    }
}
