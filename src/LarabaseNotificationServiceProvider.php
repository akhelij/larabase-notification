<?php

namespace Akhelij\LarabaseNotification;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class LarabaseNotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/larabase-notification.php', 'larabase-notification');

        $this->app->singleton(LarabaseNotification::class);

        $this->app->singleton('larabase-notification', function ($app) {
            return $app->make(LarabaseNotification::class);
        });
    }

    public function boot(): void
    {
        Notification::resolved(function (ChannelManager $service) {
            $service->extend('larabase', function ($app) {
                return new LarabaseChannel($app->make(Dispatcher::class));
            });
        });

        $this->publishes([
            __DIR__ . '/../config/larabase-notification.php' => config_path('larabase-notification.php'),
        ], 'config');
    }
}
