<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase Project ID
    |--------------------------------------------------------------------------
    |
    | This is your Firebase project ID, which is required for using
    | Firebase Cloud Messaging's HTTP v1 API. You can find it in your
    | Firebase console under Project Settings -> General.
    |
    */
    'project_id' => env('FIREBASE_PROJECT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Firebase Service Account Key
    |--------------------------------------------------------------------------
    |
    | This is the path to the JSON file containing your Firebase service
    | account credentials. These credentials are needed to generate OAuth
    | tokens for authenticating requests to the Firebase HTTP v1 API.
    |
    */
    'service_account_file' => storage_path(env('FIREBASE_SERVICE_ACCOUNT_FILE', 'app/firebase_credentials.json')),

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configure retry behavior for failed FCM requests. Only 429 (rate limit),
    | 500, and 503 responses will be retried.
    |
    */
    'retry' => [
        'attempts' => (int) env('LARABASE_RETRY_ATTEMPTS', 3),
        'delay' => (int) env('LARABASE_RETRY_DELAY', 100), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout in seconds for each FCM HTTP request.
    |
    */
    'timeout' => (int) env('LARABASE_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Chunk Size
    |--------------------------------------------------------------------------
    |
    | Maximum number of tokens per concurrent batch when sending to multiple
    | devices. Tokens beyond this limit will be sent in subsequent batches.
    |
    */
    'chunk_size' => (int) env('LARABASE_CHUNK_SIZE', 500),

    /*
    |--------------------------------------------------------------------------
    | Pool Concurrency
    |--------------------------------------------------------------------------
    |
    | Maximum number of simultaneous HTTP connections within each pool chunk.
    | Set to 0 (default) to send all requests in a chunk at once.
    | Requires Laravel 11+.
    |
    */
    'concurrency' => (int) env('LARABASE_CONCURRENCY', 0),
];
