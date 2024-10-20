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
    | Firebase Server Key (Optional for Legacy)
    |--------------------------------------------------------------------------
    |
    | This is your Firebase server key. It's still needed if you are
    | supporting the legacy FCM API. You can find it in the Firebase console
    | under Project Settings -> Cloud Messaging.
    |
    */
    'server_key' => env('FIREBASE_SERVER_KEY'),
];
