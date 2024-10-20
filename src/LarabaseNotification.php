<?php

namespace Akhelij\LarabaseNotification;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LarabaseNotification
{
    /**
     * Send notification to Firebase Cloud Messaging via HTTP v1 API.
     *
     * @param string $deviceToken The recipient's device token
     * @param string $title The title of the notification
     * @param string $body The body of the notification
     * @param array  $additionalData Optional data to be sent with the notification
     * @return array
     */
    public function sendNotification($deviceToken, $title, $body, $additionalData = [])
    {
        // Ensure all additional data values are strings
        $additionalData = array_map('strval', $additionalData);

        $accessToken = $this->getAccessToken();
        $projectId = config('larabase-notification.project_id');
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $payload = [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $additionalData,
                // Optional platform-specific configurations
                'android' => [
                    'priority' => 'HIGH',
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withToken($accessToken)
            ->post($url, $payload);

        // Log the response for debugging
        Log::info('Firebase Notification Response:', $response->json());

        return $response->json();
    }

    /**
     * Generate OAuth 2.0 Token using Firebase Service Account.
     *
     * @return string
     */
    public function getAccessToken()
    {
        // Path to your service account JSON file from the configuration
        $jsonKeyFilePath = config('larabase-notification.service_account_file');

        // Set the scope for Firebase messaging
        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];

        // Create service account credentials
        $credentials = new ServiceAccountCredentials($scopes, $jsonKeyFilePath);

        // Get the access token
        $credentials->fetchAuthToken();
        return $credentials->getLastReceivedToken()['access_token'];
    }
}
