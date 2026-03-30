<?php

namespace Akhelij\LarabaseNotification\Tests\Unit;

use Akhelij\LarabaseNotification\Exceptions\PayloadTooLargeException;
use Akhelij\LarabaseNotification\LarabaseMessage;
use Akhelij\LarabaseNotification\LarabaseNotification;
use Akhelij\LarabaseNotification\LarabaseNotificationServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

class LarabaseNotificationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LarabaseNotificationServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('larabase-notification.project_id', 'test-project');
        $app['config']->set('larabase-notification.service_account_file', '/fake/path.json');
        $app['config']->set('larabase-notification.retry.attempts', 1);
        $app['config']->set('larabase-notification.retry.delay', 0);
        $app['config']->set('larabase-notification.timeout', 5);
        $app['config']->set('larabase-notification.chunk_size', 500);
    }

    private function fakeCacheToken(): void
    {
        Cache::shouldReceive('remember')
            ->andReturn('fake-access-token');
    }

    public function test_send_notification_posts_to_correct_url(): void
    {
        $this->fakeCacheToken();

        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/test/messages/123']),
        ]);

        $firebase = new LarabaseNotification();
        $response = $firebase->sendNotification('device-token', 'Title', 'Body');

        $this->assertSame('projects/test/messages/123', $response['name']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'projects/test-project/messages:send')
                && $request->hasHeader('Authorization', 'Bearer fake-access-token')
                && $request['message']['token'] === 'device-token'
                && $request['message']['notification']['title'] === 'Title'
                && $request['message']['notification']['body'] === 'Body';
        });
    }

    public function test_send_notification_includes_default_platform_config(): void
    {
        $this->fakeCacheToken();

        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['name' => 'ok']),
        ]);

        $firebase = new LarabaseNotification();
        $firebase->sendNotification('token', 'Title', 'Body');

        Http::assertSent(function ($request) {
            $android = $request['message']['android'] ?? null;
            $apns = $request['message']['apns'] ?? null;

            return $android['priority'] === 'HIGH'
                && $apns['payload']['aps']['sound'] === 'default';
        });
    }

    public function test_send_notification_includes_image_when_set(): void
    {
        $this->fakeCacheToken();

        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['name' => 'ok']),
        ]);

        $message = (new LarabaseMessage())
            ->withTitle('Title')
            ->withBody('Body')
            ->withImage('https://example.com/img.png');

        $firebase = new LarabaseNotification();
        $firebase->sendNotification('token', 'Title', 'Body', [], $message);

        Http::assertSent(function ($request) {
            return ($request['message']['notification']['image'] ?? null) === 'https://example.com/img.png';
        });
    }

    public function test_send_notification_uses_custom_platform_config(): void
    {
        $this->fakeCacheToken();

        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['name' => 'ok']),
        ]);

        $message = (new LarabaseMessage())
            ->withTitle('Title')
            ->withBody('Body')
            ->withAndroid(['priority' => 'NORMAL'])
            ->withApns(['payload' => ['aps' => ['sound' => 'custom.caf']]]);

        $firebase = new LarabaseNotification();
        $firebase->sendNotification('token', 'Title', 'Body', [], $message);

        Http::assertSent(function ($request) {
            return $request['message']['android']['priority'] === 'NORMAL'
                && $request['message']['apns']['payload']['aps']['sound'] === 'custom.caf';
        });
    }

    public function test_send_notification_includes_webpush_when_set(): void
    {
        $this->fakeCacheToken();

        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['name' => 'ok']),
        ]);

        $message = (new LarabaseMessage())
            ->withTitle('Title')
            ->withBody('Body')
            ->withWebpush(['notification' => ['icon' => '/icon.png']]);

        $firebase = new LarabaseNotification();
        $firebase->sendNotification('token', 'Title', 'Body', [], $message);

        Http::assertSent(function ($request) {
            return ($request['message']['webpush']['notification']['icon'] ?? null) === '/icon.png';
        });
    }

    public function test_send_notification_casts_additional_data_to_strings(): void
    {
        $this->fakeCacheToken();

        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['name' => 'ok']),
        ]);

        $firebase = new LarabaseNotification();
        $firebase->sendNotification('token', 'Title', 'Body', ['id' => 42, 'active' => true]);

        Http::assertSent(function ($request) {
            $data = $request['message']['data'];

            return $data['id'] === '42' && $data['active'] === '1';
        });
    }

    public function test_payload_too_large_throws_exception(): void
    {
        $this->fakeCacheToken();
        $this->expectException(PayloadTooLargeException::class);

        $firebase = new LarabaseNotification();

        // Create a payload that exceeds 4096 bytes
        $data = [];
        for ($i = 0; $i < 100; $i++) {
            $data["key_{$i}"] = str_repeat('x', 100);
        }

        $firebase->sendNotification('token', 'Title', 'Body', $data);
    }

    public function test_send_to_multiple_tokens_returns_results_per_token(): void
    {
        $this->fakeCacheToken();

        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/test/messages/ok']),
        ]);

        $firebase = new LarabaseNotification();
        $results = $firebase->sendToMultipleTokens(
            ['token1', 'token2', 'token3'],
            'Title',
            'Body'
        );

        $this->assertCount(3, $results);
        $this->assertArrayHasKey('token1', $results);
        $this->assertArrayHasKey('token2', $results);
        $this->assertArrayHasKey('token3', $results);
    }

    public function test_send_to_multiple_tokens_empty_array_returns_empty(): void
    {
        $firebase = new LarabaseNotification();
        $results = $firebase->sendToMultipleTokens([], 'Title', 'Body');

        $this->assertSame([], $results);
    }

    public function test_constructor_accepts_custom_project_and_credentials(): void
    {
        $this->fakeCacheToken();

        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['name' => 'ok']),
        ]);

        $firebase = new LarabaseNotification('custom-project', '/custom/path.json');
        $firebase->sendNotification('token', 'Title', 'Body');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'projects/custom-project/messages:send');
        });
    }

    public function test_send_to_multiple_tokens_validates_payload_size(): void
    {
        $this->fakeCacheToken();
        $this->expectException(PayloadTooLargeException::class);

        $firebase = new LarabaseNotification();

        $data = [];
        for ($i = 0; $i < 100; $i++) {
            $data["key_{$i}"] = str_repeat('x', 100);
        }

        $firebase->sendToMultipleTokens(['token1', 'token2'], 'Title', 'Body', $data);
    }

    public function test_cache_key_includes_project_id(): void
    {
        $cached = [];

        Cache::shouldReceive('remember')
            ->andReturnUsing(function ($key, $ttl, $callback) use (&$cached) {
                $cached[] = $key;

                return 'fake-token';
            });

        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['name' => 'ok']),
        ]);

        $firebase1 = new LarabaseNotification('project-a', '/a.json');
        $firebase1->sendNotification('token', 'Title', 'Body');

        $firebase2 = new LarabaseNotification('project-b', '/b.json');
        $firebase2->sendNotification('token', 'Title', 'Body');

        $this->assertCount(2, $cached);
        $this->assertNotSame($cached[0], $cached[1]);
        $this->assertStringContainsString('project-a', $cached[0]);
        $this->assertStringContainsString('project-b', $cached[1]);
    }
}
