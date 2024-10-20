<?php

namespace Akhelij\LarabaseNotification;

use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class LarabaseNotificationServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     */
    public function register()
    {
        // Merge the package configuration with the app's existing config
        $this->mergeConfigFrom(__DIR__.'/../config/larabase-notification.php', 'larabase-notification');
    }

    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        Log::info('LarabaseNotificationServiceProvider@boot method called.');

        // Defer the registration until the Notification service is resolved
        Notification::resolved(function (ChannelManager $service) {
            Log::info('Registering larabase notification channel.');

            $service->extend('larabase', function ($app) {
                return new LarabaseChannel();
            });
        });

        // Publish the configuration file
        $this->publishes([
            __DIR__ . '/../config/larabase-notification.php' => config_path('larabase-notification.php'),
        ], 'config');
    }
}
