# LarabaseNotification

A lightweight Laravel package for sending Firebase Cloud Messaging (FCM) push notifications via the HTTP v1 API. Minimal dependencies, concurrent delivery, automatic retry, and full Laravel notification system integration.

## Features

- **Laravel Notification Channel**: Uses Laravel's native notification system with a custom `larabase` channel
- **Firebase HTTP v1 API**: Uses the current FCM standard with OAuth 2.0 authentication
- **Concurrent Delivery**: Sends to multiple devices in parallel via `Http::pool()`
- **OAuth Token Caching**: Caches access tokens to avoid redundant auth requests
- **Automatic Retry**: Configurable retry with backoff for transient failures (429, 500, 503)
- **NotificationFailed Events**: Dispatches Laravel events for failed deliveries (e.g., stale token cleanup)
- **Send Reports**: Structured results with success/failure counts and unregistered token detection
- **Image Support**: Native FCM notification images
- **Platform Config**: Customizable Android, iOS (APNs), and Webpush settings
- **Payload Validation**: Validates payload size against FCM limits before sending
- **Queue Support**: Works with Laravel's queue system out of the box
- **Multi-Tenant**: Inject custom Firebase clients per notification for different projects

## Requirements

- **PHP**: 8.1 or higher
- **Laravel**: 10.x, 11.x, or 12.x
- **Firebase Project**: A Firebase project with a service account JSON file

## Installation

```bash
composer require akhelij/larabase-notification
```

## Configuration

### 1. Publish the Configuration File

```bash
php artisan vendor:publish --provider="Akhelij\LarabaseNotification\LarabaseNotificationServiceProvider" --tag="config"
```

### 2. Set Environment Variables

Add the following to your `.env` file:

```env
FIREBASE_PROJECT_ID=your-firebase-project-id
FIREBASE_SERVICE_ACCOUNT_FILE=app/firebase_credentials.json
```

The service account file path is relative to `storage_path()`. Ensure the JSON file is kept secure and not committed to version control.

### 3. Configuration Options

The published `config/larabase-notification.php` supports these settings:

| Key | Env Variable | Default | Description |
|-----|-------------|---------|-------------|
| `project_id` | `FIREBASE_PROJECT_ID` | — | Your Firebase project ID |
| `service_account_file` | `FIREBASE_SERVICE_ACCOUNT_FILE` | `app/firebase_credentials.json` | Path to service account JSON (relative to `storage/`) |
| `retry.attempts` | `LARABASE_RETRY_ATTEMPTS` | `3` | Number of retry attempts on transient failures |
| `retry.delay` | `LARABASE_RETRY_DELAY` | `100` | Delay between retries in milliseconds |
| `timeout` | `LARABASE_TIMEOUT` | `30` | HTTP request timeout in seconds |
| `chunk_size` | `LARABASE_CHUNK_SIZE` | `500` | Max tokens per concurrent batch |

## Usage

### Basic Notification

```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Akhelij\LarabaseNotification\LarabaseMessage;

class OrderShipped extends Notification
{
    use Queueable;

    public function __construct(
        private string $orderId,
        private array $deviceTokens,
    ) {}

    public function via($notifiable): array
    {
        return ['larabase'];
    }

    public function toLarabase($notifiable): LarabaseMessage
    {
        return (new LarabaseMessage())
            ->withTitle('Order Shipped')
            ->withBody("Your order #{$this->orderId} has been shipped!")
            ->withAdditionalData([
                'order_id' => $this->orderId,
                'type' => 'order_shipped',
            ])
            ->asNotification($this->deviceTokens);
    }
}
```

### With Image

```php
public function toLarabase($notifiable): LarabaseMessage
{
    return (new LarabaseMessage())
        ->withTitle('New Photo')
        ->withBody('Someone shared a photo with you')
        ->withImage('https://example.com/photo.jpg')
        ->asNotification($this->tokens);
}
```

### Platform-Specific Configuration

```php
public function toLarabase($notifiable): LarabaseMessage
{
    return (new LarabaseMessage())
        ->withTitle('Alert')
        ->withBody('Important update')
        ->withPriority('HIGH')           // Android priority
        ->withSound('alert.caf')         // iOS sound
        ->withBadge(1)                   // iOS badge count
        ->withClickAction('OPEN_ORDER')  // Android click action
        ->asNotification($this->tokens);
}
```

Or pass full platform config arrays directly:

```php
public function toLarabase($notifiable): LarabaseMessage
{
    return (new LarabaseMessage())
        ->withTitle('Alert')
        ->withBody('Update available')
        ->withAndroid([
            'priority' => 'HIGH',
            'notification' => [
                'color' => '#ff0000',
                'click_action' => 'OPEN_ACTIVITY',
            ],
        ])
        ->withApns([
            'payload' => [
                'aps' => [
                    'sound' => 'custom.caf',
                    'badge' => 5,
                ],
            ],
        ])
        ->withWebpush([
            'notification' => [
                'icon' => '/icon-192.png',
            ],
        ])
        ->asNotification($this->tokens);
}
```

### Handling Failed Notifications

Register an event listener to handle failed deliveries (e.g., removing stale tokens):

```php
// app/Providers/EventServiceProvider.php
use Illuminate\Notifications\Events\NotificationFailed;
use App\Listeners\HandleFailedFcmNotification;

protected $listen = [
    NotificationFailed::class => [
        HandleFailedFcmNotification::class,
    ],
];
```

```php
// app/Listeners/HandleFailedFcmNotification.php
namespace App\Listeners;

use Akhelij\LarabaseNotification\LarabaseChannel;
use Illuminate\Notifications\Events\NotificationFailed;

class HandleFailedFcmNotification
{
    public function handle(NotificationFailed $event): void
    {
        if ($event->channel !== LarabaseChannel::class) {
            return;
        }

        $token = $event->data['token'];
        $errorCode = $event->data['error_code'] ?? '';

        if ($errorCode === 'UNREGISTERED') {
            // Remove the stale token from your database
            $event->notifiable->deviceTokens()
                ->where('fcm_token', $token)
                ->delete();
        }
    }
}
```

### Using Send Reports

The `send()` method returns a `LarabaseSendReport`:

```php
// If calling the channel directly (e.g., in tests):
$report = $channel->send($notifiable, $notification);

$report->successCount();        // Number of successful deliveries
$report->failureCount();        // Number of failures
$report->hasFailures();         // bool
$report->failedTokens();        // Array of failed token strings
$report->unregisteredTokens();  // Tokens that FCM reports as unregistered
```

### Multi-Tenant (Custom Firebase Project)

For applications using multiple Firebase projects:

```php
use Akhelij\LarabaseNotification\LarabaseNotification;

public function toLarabase($notifiable): LarabaseMessage
{
    $client = new LarabaseNotification(
        projectId: 'other-project-id',
        serviceAccountFile: '/path/to/other-service-account.json',
    );

    return (new LarabaseMessage())
        ->withTitle('Hello')
        ->withBody('From another project')
        ->usingClient($client)
        ->asNotification($this->tokens);
}
```

### Using the Facade

```php
use Akhelij\LarabaseNotification\LarabaseNotificationFacade as Larabase;

// Send to a single token
$response = Larabase::sendNotification($token, 'Title', 'Body', ['key' => 'value']);

// Send to multiple tokens concurrently
$results = Larabase::sendToMultipleTokens($tokens, 'Title', 'Body', ['key' => 'value']);
```

## Upgrading from v1.x

### Breaking Changes

- **PHP 8.1+ required** (was 7.4+)
- **Laravel 10+ required** (was 7+)

### Behavioral Changes (Non-Breaking)

- **Token filtering**: `asNotification()` now automatically filters out `null`, empty, and non-string values, and deduplicates tokens. Previously these would cause errors or wasted API calls.
- **Data validation**: `withAdditionalData()` now casts scalar values to strings and converts `null` to empty string. Non-scalar values (arrays, objects) throw `InvalidArgumentException`.
- **OAuth caching**: Access tokens are now cached for ~58 minutes instead of fetching a new one per send.
- **Concurrent sending**: Multiple tokens are sent in parallel instead of sequentially.
- **Events**: `NotificationFailed` events are now dispatched for each failed token.

## Testing

```bash
composer test
```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Contact

For questions or support, please open an issue on the [GitHub repository](https://github.com/akhelij/larabase-notification).
