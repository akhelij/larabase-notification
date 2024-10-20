# LarabaseNotification

LarabaseNotification is a Laravel package that enables you to send Firebase Cloud Messaging (FCM) notifications using Laravel's notification system. It integrates seamlessly with Laravel, allowing you to send push notifications to your users via FCM with ease.

## Features

- **Laravel Notification Channel**: Uses Laravel's notification system with a custom channel.
- **Firebase HTTP v1 API**: Utilizes the latest Firebase Cloud Messaging API.
- **Device Token Management**: Send notifications to specific device tokens.
- **Customizable Payloads**: Add additional data to your notifications.
- **Queue Support**: Notifications can be queued using Laravel's queue system.

## Requirements

- **PHP**: 7.4 or higher
- **Laravel**: 7.x or higher
- **Firebase Project**: A Firebase project with a service account JSON file

## Installation

Install the package via Composer:

```bash
composer require akhelij/larabase-notification
```

## Configuration

### 1. Publish the Configuration File

Publish the package configuration file using Artisan:

```bash
php artisan vendor:publish --provider="Akhelij\LarabaseNotification\LarabaseNotificationServiceProvider" --tag="config"
```

This will create a `config/larabase-notification.php` file in your Laravel application.

### 2. Set Up Firebase Credentials

Update the `config/larabase-notification.php` file with your Firebase project details:

```php
return [

    /*
    |--------------------------------------------------------------------------
    | Firebase Project ID
    |--------------------------------------------------------------------------
    |
    | The ID of your Firebase project. This is used to construct the API endpoint
    | for sending notifications.
    |
    */

    'project_id' => env('FIREBASE_PROJECT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Service Account JSON File
    |--------------------------------------------------------------------------
    |
    | The path to your Firebase service account JSON file. This file is used to
    | generate an OAuth 2.0 access token for authenticating with Firebase.
    |
    */

    'service_account_file' => env('FIREBASE_SERVICE_ACCOUNT_FILE'),

];
```

### 3. Set Environment Variables

In your `.env` file, add the following entries:

```env
FIREBASE_PROJECT_ID=your-firebase-project-id
FIREBASE_SERVICE_ACCOUNT_FILE=/path/to/your/service-account.json
```

- **FIREBASE_PROJECT_ID**: Your Firebase project ID.
- **FIREBASE_SERVICE_ACCOUNT_FILE**: The absolute path to your Firebase service account JSON file.

**Note:** Ensure the service account JSON file is kept secure and not committed to version control.

## Usage

### 1. Create a Notification Class

Generate a new notification class:

```bash
php artisan make:notification TestFirebaseNotification
```

### 2. Update the Notification Class

Modify the generated notification class to use the LarabaseNotification package:

```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Akhelij\LarabaseNotification\LarabaseMessage;

class TestFirebaseNotification extends Notification
{
    use Queueable;

    public $title;
    public $body;
    public $additionalData;
    public $deviceTokens;

    /**
     * Create a new notification instance.
     *
     * @param string $title
     * @param string $body
     * @param array $additionalData
     * @param array $deviceTokens
     */
    public function __construct($title, $body, $additionalData = [], $deviceTokens = [])
    {
        $this->title = $title;
        $this->body = $body;
        $this->additionalData = $additionalData;
        $this->deviceTokens = $deviceTokens;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['larabase'];
    }

    /**
     * Get the Larabase representation of the notification.
     *
     * @param mixed $notifiable
     * @return \Akhelij\LarabaseNotification\LarabaseMessage
     */
    public function toLarabase($notifiable)
    {
        // Ensure all additional data values are strings
        $this->additionalData = array_map('strval', $this->additionalData);

        return (new LarabaseMessage())
            ->withTitle($this->title)
            ->withBody($this->body)
            ->withAdditionalData($this->additionalData)
            ->asNotification($this->deviceTokens);
    }
}
```

### 3. Send the Notification

You can now send the notification to a user:

```php
use App\Models\User;
use App\Notifications\TestFirebaseNotification;

$user = User::find(1); // Replace with the appropriate user ID

$title = 'Test Notification';
$body = 'This is a test message';
$additionalData = [
    'key1' => 'value1',
    'key2' => 'value2',
];

$deviceTokens = [$request->input('fcm_token')]; // Replace with actual tokens

$notification = new TestFirebaseNotification($title, $body, $additionalData, $deviceTokens);

$user->notify($notification);
```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Contact

For questions or support, please open an issue on the [GitHub repository](https://github.com/yourusername/larabase-notification).
