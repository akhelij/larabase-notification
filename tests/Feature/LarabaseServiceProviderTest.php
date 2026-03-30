<?php

namespace Akhelij\LarabaseNotification\Tests\Feature;

use Akhelij\LarabaseNotification\LarabaseNotification;
use Akhelij\LarabaseNotification\LarabaseNotificationServiceProvider;
use Orchestra\Testbench\TestCase;

class LarabaseServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LarabaseNotificationServiceProvider::class];
    }

    public function test_config_is_merged(): void
    {
        // project_id defaults to env() which may be null, so check the key exists
        $this->assertTrue($this->app['config']->has('larabase-notification.project_id'));
        $this->assertTrue($this->app['config']->has('larabase-notification.service_account_file'));
        $this->assertNotNull(config('larabase-notification.retry'));
        $this->assertNotNull(config('larabase-notification.timeout'));
        $this->assertNotNull(config('larabase-notification.chunk_size'));
    }

    public function test_larabase_notification_is_bound_as_singleton(): void
    {
        $instance1 = $this->app->make(LarabaseNotification::class);
        $instance2 = $this->app->make(LarabaseNotification::class);

        $this->assertSame($instance1, $instance2);
    }

    public function test_facade_accessor_is_bound(): void
    {
        $instance = $this->app->make('larabase-notification');

        $this->assertInstanceOf(LarabaseNotification::class, $instance);
    }

    public function test_retry_config_has_defaults(): void
    {
        $this->assertSame(3, config('larabase-notification.retry.attempts'));
        $this->assertSame(100, config('larabase-notification.retry.delay'));
    }

    public function test_timeout_config_has_default(): void
    {
        $this->assertSame(30, config('larabase-notification.timeout'));
    }

    public function test_chunk_size_config_has_default(): void
    {
        $this->assertSame(500, config('larabase-notification.chunk_size'));
    }

    public function test_concurrency_config_has_default(): void
    {
        $this->assertSame(0, config('larabase-notification.concurrency'));
    }
}
