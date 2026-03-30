<?php

namespace Akhelij\LarabaseNotification\Tests\Unit;

use Akhelij\LarabaseNotification\LarabaseChannel;
use Akhelij\LarabaseNotification\LarabaseMessage;
use Akhelij\LarabaseNotification\LarabaseNotification;
use Akhelij\LarabaseNotification\LarabaseNotificationServiceProvider;
use Akhelij\LarabaseNotification\LarabaseSendReport;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Mockery;
use Orchestra\Testbench\TestCase;

class LarabaseChannelTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LarabaseNotificationServiceProvider::class];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createNotifiable(array $tokens = []): object
    {
        return new class($tokens) {
            public function __construct(private array $tokens) {}

            public function routeNotificationFor(string $channel, $notification = null)
            {
                return $this->tokens;
            }
        };
    }

    private function createNotification(LarabaseMessage $message): Notification
    {
        return new class($message) extends Notification {
            public function __construct(private LarabaseMessage $message) {}

            public function toLarabase($notifiable): LarabaseMessage
            {
                return $this->message;
            }
        };
    }

    public function test_returns_null_when_no_tokens(): void
    {
        $dispatcher = Mockery::mock(Dispatcher::class);
        $channel = new LarabaseChannel($dispatcher);

        $message = (new LarabaseMessage())
            ->withTitle('Test')
            ->withBody('Body');

        $notifiable = $this->createNotifiable([]);
        $notification = $this->createNotification($message);

        $result = $channel->send($notifiable, $notification);

        $this->assertNull($result);
    }

    public function test_returns_send_report_on_success(): void
    {
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldNotReceive('dispatch');

        $firebase = Mockery::mock(LarabaseNotification::class);
        $firebase->shouldReceive('sendToMultipleTokens')
            ->once()
            ->andReturn([
                'token1' => ['name' => 'projects/test/messages/123'],
            ]);

        $message = (new LarabaseMessage())
            ->withTitle('Test')
            ->withBody('Body')
            ->asNotification(['token1'])
            ->usingClient($firebase);

        $channel = new LarabaseChannel($dispatcher);
        $notifiable = $this->createNotifiable();
        $notification = $this->createNotification($message);

        $result = $channel->send($notifiable, $notification);

        $this->assertInstanceOf(LarabaseSendReport::class, $result);
        $this->assertSame(1, $result->successCount());
        $this->assertSame(0, $result->failureCount());
    }

    public function test_dispatches_notification_failed_event_on_error(): void
    {
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(NotificationFailed::class));

        $firebase = Mockery::mock(LarabaseNotification::class);
        $firebase->shouldReceive('sendToMultipleTokens')
            ->once()
            ->andReturn([
                'token1' => [
                    'error' => [
                        'message' => 'Token unregistered',
                        'details' => [['errorCode' => 'UNREGISTERED']],
                    ],
                ],
            ]);

        $message = (new LarabaseMessage())
            ->withTitle('Test')
            ->withBody('Body')
            ->asNotification(['token1'])
            ->usingClient($firebase);

        $channel = new LarabaseChannel($dispatcher);
        $notifiable = $this->createNotifiable();
        $notification = $this->createNotification($message);

        $result = $channel->send($notifiable, $notification);

        $this->assertInstanceOf(LarabaseSendReport::class, $result);
        $this->assertTrue($result->hasFailures());
        $this->assertSame(['token1'], $result->unregisteredTokens());
    }

    public function test_dispatches_event_for_each_failed_token(): void
    {
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->twice()
            ->with(Mockery::type(NotificationFailed::class));

        $firebase = Mockery::mock(LarabaseNotification::class);
        $firebase->shouldReceive('sendToMultipleTokens')
            ->once()
            ->andReturn([
                'token1' => ['name' => 'projects/test/messages/123'],
                'token2' => ['error' => ['message' => 'Error 1']],
                'token3' => ['error' => ['message' => 'Error 2']],
            ]);

        $message = (new LarabaseMessage())
            ->withTitle('Test')
            ->withBody('Body')
            ->asNotification(['token1', 'token2', 'token3'])
            ->usingClient($firebase);

        $channel = new LarabaseChannel($dispatcher);
        $notifiable = $this->createNotifiable();
        $notification = $this->createNotification($message);

        $result = $channel->send($notifiable, $notification);

        $this->assertSame(1, $result->successCount());
        $this->assertSame(2, $result->failureCount());
    }

    public function test_falls_back_to_route_notification_for(): void
    {
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldNotReceive('dispatch');

        $firebase = Mockery::mock(LarabaseNotification::class);
        $firebase->shouldReceive('sendToMultipleTokens')
            ->once()
            ->with(['fallback-token'], 'Test', 'Body', [], Mockery::type(LarabaseMessage::class))
            ->andReturn([
                'fallback-token' => ['name' => 'projects/test/messages/789'],
            ]);

        // Message with NO tokens set
        $message = (new LarabaseMessage())
            ->withTitle('Test')
            ->withBody('Body')
            ->usingClient($firebase);

        $channel = new LarabaseChannel($dispatcher);
        $notifiable = $this->createNotifiable(['fallback-token']);
        $notification = $this->createNotification($message);

        $result = $channel->send($notifiable, $notification);

        $this->assertSame(1, $result->successCount());
    }

    public function test_returns_null_when_all_tokens_are_invalid(): void
    {
        $dispatcher = Mockery::mock(Dispatcher::class);
        $channel = new LarabaseChannel($dispatcher);

        $message = (new LarabaseMessage())
            ->withTitle('Test')
            ->withBody('Body');

        // Notifiable returns only invalid tokens
        $notifiable = $this->createNotifiable([null, '', false]);
        $notification = $this->createNotification($message);

        $result = $channel->send($notifiable, $notification);

        $this->assertNull($result);
    }

    public function test_logs_batch_summary_on_success(): void
    {
        Log::shouldReceive('debug')
            ->once()
            ->with(Mockery::pattern('/sent 1\/1 notifications successfully/'));

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldNotReceive('dispatch');

        $firebase = Mockery::mock(LarabaseNotification::class);
        $firebase->shouldReceive('sendToMultipleTokens')
            ->once()
            ->andReturn([
                'token1' => ['name' => 'projects/test/messages/123'],
            ]);

        $message = (new LarabaseMessage())
            ->withTitle('Test')
            ->withBody('Body')
            ->asNotification(['token1'])
            ->usingClient($firebase);

        $channel = new LarabaseChannel($dispatcher);
        $result = $channel->send($this->createNotifiable(), $this->createNotification($message));

        $this->assertSame(1, $result->successCount());
    }

    public function test_logs_unregistered_and_failed_tokens_separately(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with(Mockery::pattern('/1 unregistered/'), Mockery::type('array'));

        Log::shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/1 token\(s\) failed/'), Mockery::type('array'));

        Log::shouldReceive('debug')->once();

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->twice();

        $firebase = Mockery::mock(LarabaseNotification::class);
        $firebase->shouldReceive('sendToMultipleTokens')
            ->once()
            ->andReturn([
                'token1' => [
                    'error' => [
                        'message' => 'Unregistered',
                        'details' => [['errorCode' => 'UNREGISTERED']],
                    ],
                ],
                'token2' => ['error' => ['message' => 'Internal error']],
            ]);

        $message = (new LarabaseMessage())
            ->withTitle('Test')
            ->withBody('Body')
            ->asNotification(['token1', 'token2'])
            ->usingClient($firebase);

        $channel = new LarabaseChannel($dispatcher);
        $result = $channel->send($this->createNotifiable(), $this->createNotification($message));

        $this->assertSame(2, $result->failureCount());
    }
}
