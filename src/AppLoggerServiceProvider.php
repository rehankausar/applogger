<?php

namespace FastnetKSA\AppLogger;

use FastnetKSA\AppLogger\Models\ApplicationLog;
use Illuminate\Support\ServiceProvider;

class AppLoggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/applogger.php',
            'applogger'
        );
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/applogger.php' => config_path('applogger.php'),
        ], 'applogger-config');

        // Publish migration (apps must publish + customise before running migrate)
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'applogger-migrations');

        // Publish views (allows apps to override individual views)
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/applogger'),
        ], 'applogger-views');

        // Load views under the 'applogger::' namespace
        // Published copies in resources/views/vendor/applogger/ take precedence automatically
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'applogger');

        // Register routes conditionally
        if (config('applogger.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/applogger.php');
        }

        // Register dynamic Eloquent relationships from config
        foreach (config('applogger.relationships', []) as $name => $definition) {
            ApplicationLog::resolveRelationUsing($name, function (ApplicationLog $model) use ($definition) {
                return $model->belongsTo($definition['model'], $definition['foreign']);
            });
        }
    }
}
