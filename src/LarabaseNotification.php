<?php

namespace Akhelij\LarabaseNotification;

use Akhelij\LarabaseNotification\Exceptions\PayloadTooLargeException;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LarabaseNotification
{
    protected const MAX_PAYLOAD_BYTES = 4096;

    protected ?string $projectId;

    protected ?string $serviceAccountFile;

    public function __construct(?string $projectId = null, ?string $serviceAccountFile = null)
    {
        $this->projectId = $projectId;
        $this->serviceAccountFile = $serviceAccountFile;
    }

    /**
     * Send notification to a single device token.
     *
     * @return array The Firebase response
     */
    public function sendNotification(
        string $deviceToken,
        string $title,
        string $body,
        array $additionalData = [],
        ?LarabaseMessage $message = null,
    ): array {
        $additionalData = array_map('strval', $additionalData);
        $payload = $this->buildPayload($deviceToken, $title, $body, $additionalData, $message);

        $this->validatePayloadSize($payload);

        return $this->sendRequest($payload);
    }

    /**
     * Send notifications to multiple device tokens concurrently using Http::pool().
     *
     * @param  string[]  $tokens
     * @return array<string, array> Token => response mapping
     */
    public function sendToMultipleTokens(
        array $tokens,
        string $title,
        string $body,
        array $additionalData = [],
        ?LarabaseMessage $message = null,
    ): array {
        if (empty($tokens)) {
            return [];
        }

        $additionalData = array_map('strval', $additionalData);
        $accessToken = $this->getAccessToken();
        $url = $this->getApiUrl();
        $chunkSize = (int) config('larabase-notification.chunk_size', 500);
        $results = [];

        foreach (array_chunk($tokens, $chunkSize) as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk, $accessToken, $url, $title, $body, $additionalData, $message) {
                foreach ($chunk as $token) {
                    $payload = $this->buildPayload($token, $title, $body, $additionalData, $message);

                    $pool->as($token)
                        ->withToken($accessToken)
                        ->timeout((int) config('larabase-notification.timeout', 30))
                        ->retry(
                            (int) config('larabase-notification.retry.attempts', 3),
                            (int) config('larabase-notification.retry.delay', 100),
                            function (\Throwable $exception) {
                                if ($exception instanceof ConnectionException) {
                                    return true;
                                }

                                if ($exception instanceof RequestException) {
                                    return in_array($exception->response->status(), [429, 500, 503]);
                                }

                                return false;
                            },
                            throw: false,
                        )
                        ->post($url, $payload);
                }
            });

            foreach ($responses as $token => $response) {
                if ($response instanceof \Throwable) {
                    $results[$token] = ['error' => ['message' => $response->getMessage()]];
                } else {
                    $results[$token] = $response->json() ?? ['error' => ['message' => 'Empty response']];
                }
            }
        }

        return $results;
    }

    /**
     * Build the FCM payload for a single token.
     */
    protected function buildPayload(
        string $deviceToken,
        string $title,
        string $body,
        array $additionalData,
        ?LarabaseMessage $message = null,
    ): array {
        $notification = ['title' => $title, 'body' => $body];

        if ($message?->image) {
            $notification['image'] = $message->image;
        }

        $payload = [
            'message' => [
                'token' => $deviceToken,
                'notification' => $notification,
                'data' => $additionalData,
                'android' => $message?->androidConfig ?? ['priority' => 'HIGH'],
                'apns' => $message?->apnsConfig ?? [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                        ],
                    ],
                ],
            ],
        ];

        if ($message?->webpushConfig) {
            $payload['message']['webpush'] = $message->webpushConfig;
        }

        return $payload;
    }

    /**
     * Validate that the payload does not exceed FCM limits.
     *
     * @throws PayloadTooLargeException
     */
    protected function validatePayloadSize(array $payload): void
    {
        $size = strlen(json_encode($payload));

        if ($size > self::MAX_PAYLOAD_BYTES) {
            throw new PayloadTooLargeException($size);
        }
    }

    /**
     * Send a single request to FCM.
     */
    protected function sendRequest(array $payload): array
    {
        $accessToken = $this->getAccessToken();
        $url = $this->getApiUrl();

        $response = Http::withToken($accessToken)
            ->timeout((int) config('larabase-notification.timeout', 30))
            ->retry(
                (int) config('larabase-notification.retry.attempts', 3),
                (int) config('larabase-notification.retry.delay', 100),
                function (\Throwable $exception) {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    if ($exception instanceof RequestException) {
                        return in_array($exception->response->status(), [429, 500, 503]);
                    }

                    return false;
                },
                throw: false,
            )
            ->post($url, $payload);

        Log::info('Firebase Notification Response:', $response->json() ?? []);

        return $response->json() ?? [];
    }

    /**
     * Get the FCM API URL.
     */
    protected function getApiUrl(): string
    {
        $projectId = $this->projectId ?? config('larabase-notification.project_id');

        return "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
    }

    /**
     * Generate and cache an OAuth 2.0 access token using Firebase Service Account.
     */
    public function getAccessToken(): string
    {
        $cacheKey = 'larabase_fcm_oauth_token';

        return Cache::remember($cacheKey, 3500, function () {
            $jsonKeyFilePath = $this->serviceAccountFile
                ?? config('larabase-notification.service_account_file');

            $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
            $credentials = new ServiceAccountCredentials($scopes, $jsonKeyFilePath);
            $credentials->fetchAuthToken();

            return $credentials->getLastReceivedToken()['access_token'];
        });
    }
}
