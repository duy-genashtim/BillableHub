<?php

return [
    /*
    |--------------------------------------------------------------------------
    | TimeDoctor Token Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for TimeDoctor API token management and refresh behavior.
    | These settings control when and how tokens are refreshed to prevent
    | expiration during critical operations like cron job syncing.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Token Expiry Buffer Settings
    |--------------------------------------------------------------------------
    |
    | For 6-day token lifespan, refresh 3 days (72 hours) before expiry.
    | This provides a 50% safety margin and prevents token expiration errors.
    |
    */
    'token_expiry_buffer_seconds' => env('TIMEDOCTOR_TOKEN_EXPIRY_BUFFER', 259200), // 3 days
    'token_expiry_buffer_hours' => env('TIMEDOCTOR_TOKEN_EXPIRY_BUFFER_HOURS', 72), // 3 days

    /*
    |--------------------------------------------------------------------------
    | API Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for handling failed API calls and token refresh attempts.
    |
    */
    'max_refresh_retries' => env('TIMEDOCTOR_MAX_REFRESH_RETRIES', 3),
    'refresh_retry_delay' => env('TIMEDOCTOR_REFRESH_RETRY_DELAY', 5), // seconds

    /*
    |--------------------------------------------------------------------------
    | Token Lifespan Documentation
    |--------------------------------------------------------------------------
    |
    | Documentation of expected token behavior for reference and monitoring.
    |
    */
    'expected_token_lifespan_days' => 6,
    'refresh_safety_margin_days' => 3,

    /*
    |--------------------------------------------------------------------------
    | Token Refresh Schedule
    |--------------------------------------------------------------------------
    |
    | Day 0: New token obtained (6 days = 144 hours lifespan)
    | Day 3: Automatic proactive refresh (72 hours before expiry)
    | Day 6: Original token would have expired (but already renewed 3 days ago)
    |
    | This schedule ensures zero token expiration errors with maximum safety margin.
    |
    */
];