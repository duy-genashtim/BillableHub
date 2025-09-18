<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'timedoctor_v1' => [
        'client_id' => env('TIMEDOCTOR_V1_CLIENT_ID'),
        'client_secret' => env('TIMEDOCTOR_V1_CLIENT_SECRET'),
        'redirect_uri' => env('TIMEDOCTOR_V1_REDIRECT_URI'),
        'base_url' => env('TIMEDOCTOR_V1_BASE_URL', 'https://webapi.timedoctor.com/v1.1'),
    ],
    'timedoctor_v2' => [
        'username' => env('TIMEDOCTOR_V2_USERNAME'),
        'password' => env('TIMEDOCTOR_V2_PASSWORD'),
        'company_id' => env('TIMEDOCTOR_V2_COMPANY_ID'),
        'base_url' => 'https://api2.timedoctor.com/api/1.0',
    ],
    'nad' => [
        'url' => env('NAD_API_URL'),
        'secret_key' => env('API_NAD_SECRET_KEY'),
        'nad_hour_rate' => [
            'rate' => env('NAD_HOUR_RATE', 8)],
    ],
    'hrms' => [
        'ivas_info_url' => env('HRMS_API_IVAS_INFO_URL', 'https://my.genashtim.com/api/employee/get-employee-list-info'),
    ],

];
