<?php

namespace Macroseso\Enzona;

use Illuminate\Support\ServiceProvider;
use Macroseso\Enzona\Models\EnzonaSetting;
use Macroseso\Enzona\Payment\PaymentRequest;

class EnzonaServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/enzona.php' => config_path('enzona.php'),
        ], 'enzona-config');
        $this->publishes([
            __DIR__ . '/../resources/images/enzona-logo.svg' => public_path('vendor/enzona/enzona-logo.svg'),
        ], 'enzona-assets');
        $this->publishes([
            __DIR__ . '/../database/migrations/2026_11_06_000000_create_enzona_settings_table.php'
                => database_path('migrations/2026_11_06_000000_create_enzona_settings_table.php'),
        ], 'enzona-migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if (config('enzona.register_routes', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/enzona.php', 'enzona');

        $this->app->bind(EnzonaSetting::class, function () {
            return EnzonaSetting::global();
        });
        $this->app->bind(PaymentRequest::class, function ($app) {
            return new PaymentRequest($app->make(EnzonaSetting::class), $app->make(RequestToken::class));
        });
        $this->app->bind(Enzona::class, function ($app) {
            $settings = EnzonaSetting::global();
            return new Enzona(
                $settings,
                new PaymentRequest($settings, $app->make(RequestToken::class))
            );
        });
    }
}
