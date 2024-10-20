<?php

namespace Akhelij\LarabaseNotification;

use Illuminate\Notifications\Notification;
use Akhelij\LarabaseNotification\LarabaseNotification;
use Illuminate\Support\Facades\Log;

class LarabaseChannel
{
    public function send($notifiable, Notification $notification)
    {
        Log::info('LarabaseChannel@send method called.');

        // Retrieve the message from the notification
        $message = $notification->toLarabase($notifiable);

        // Get device tokens
        $deviceTokens = $message->deviceTokens;

        if (empty($deviceTokens)) {
            $deviceTokens = $notifiable->routeNotificationFor('larabase', $notification);
        }

        if (empty($deviceTokens)) {
            Log::warning('No device tokens found for notification.');
            return;
        }

        // Ensure device tokens are in an array
        $deviceTokens = is_array($deviceTokens) ? $deviceTokens : [$deviceTokens];

        // Send notifications
        foreach ($deviceTokens as $deviceToken) {
            try {
                $firebase = new LarabaseNotification();
                $firebase->sendNotification(
                    $deviceToken,
                    $message->title,
                    $message->body,
                    $message->additionalData
                );
                Log::info('Notification sent to device token: ' . $deviceToken);
            } catch (\Exception $e) {
                Log::error('Error sending notification: ' . $e->getMessage());
            }
        }
    }
}
