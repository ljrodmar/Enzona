<?php

namespace Lmacroseso\Enzona;

use Illuminate\Support\ServiceProvider;

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
        // Aquí puedes registrar otros servicios si es necesario
    }
}
