<?php

namespace Akhelij\LarabaseNotification;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class LarabaseChannel
{
    public function __construct(
        protected Dispatcher $events,
    ) {}

    public function send(object $notifiable, Notification $notification): ?LarabaseSendReport
    {
        $message = $notification->toLarabase($notifiable);

        $deviceTokens = $message->deviceTokens;

        if (empty($deviceTokens)) {
            $deviceTokens = $notifiable->routeNotificationFor('larabase', $notification);
        }

        if (empty($deviceTokens)) {
            Log::warning('No device tokens found for notification.');

            return null;
        }

        $deviceTokens = is_array($deviceTokens) ? $deviceTokens : [$deviceTokens];

        // Filter out any invalid tokens that may have been set via routeNotificationFor
        $deviceTokens = array_values(
            array_filter($deviceTokens, fn ($token) => is_string($token) && $token !== '')
        );

        if (empty($deviceTokens)) {
            Log::warning('All device tokens were invalid (null/empty).');

            return null;
        }

        $firebase = $message->client ?? app(LarabaseNotification::class);
        $report = new LarabaseSendReport();

        $results = $firebase->sendToMultipleTokens(
            $deviceTokens,
            $message->title,
            $message->body,
            $message->additionalData,
            $message,
        );

        foreach ($results as $token => $response) {
            if (isset($response['error'])) {
                $report->addFailure($token, $response);

                $errorCode = $response['error']['details'][0]['errorCode'] ?? '';

                if ($errorCode === 'UNREGISTERED') {
                    Log::warning('Device token unregistered: ' . $token);
                } else {
                    Log::error('Error sending notification to ' . $token . ': ' . ($response['error']['message'] ?? 'Unknown error'));
                }

                $this->events->dispatch(new NotificationFailed(
                    $notifiable,
                    $notification,
                    static::class,
                    [
                        'token' => $token,
                        'response' => $response,
                        'error_code' => $errorCode,
                    ],
                ));
            } else {
                $report->addSuccess($token, $response);
                Log::info('Notification sent to device token: ' . $token);
            }
        }

        return $report;
    }
}
