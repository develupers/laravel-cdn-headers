<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CDN Headers Enabled
    |--------------------------------------------------------------------------
    |
    | This option determines if the CDN headers middleware is active.
    | You may want to disable this in local development environments.
    |
    */
    'enabled' => env('CDN_HEADERS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Middleware Registration
    |--------------------------------------------------------------------------
    |
    | Determines how the middleware should be registered:
    | - 'global': Automatically register as global middleware
    | - 'manual': You need to manually register in your Kernel.php
    |
    */
    'middleware_registration' => env('CDN_HEADERS_REGISTRATION', 'global'),

    /*
    |--------------------------------------------------------------------------
    | Skip Authenticated Users
    |--------------------------------------------------------------------------
    |
    | When true, authenticated users will not receive CDN cache headers.
    | This prevents caching of personalized content.
    |
    */
    'skip_authenticated' => env('CDN_HEADERS_SKIP_AUTH', true),

    /*
    |--------------------------------------------------------------------------
    | Remove Cookies
    |--------------------------------------------------------------------------
    |
    | When true, removes Set-Cookie headers from cacheable responses.
    | This is required for most CDNs to cache the response.
    |
    */
    'remove_cookies' => env('CDN_HEADERS_REMOVE_COOKIES', true),

    /*
    |--------------------------------------------------------------------------
    | Remove Vary Cookie
    |--------------------------------------------------------------------------
    |
    | When true, removes 'Cookie' from the Vary header.
    | This helps CDNs cache more effectively.
    |
    */
    'remove_vary_cookie' => env('CDN_HEADERS_REMOVE_VARY_COOKIE', true),

    /*
    |--------------------------------------------------------------------------
    | Enable Logging
    |--------------------------------------------------------------------------
    |
    | When true, logs when CDN headers are applied to responses.
    | Useful for debugging.
    |
    */
    'logging' => env('CDN_HEADERS_LOGGING', false),

    /*
    |--------------------------------------------------------------------------
    | Default Cache Duration
    |--------------------------------------------------------------------------
    |
    | Default cache duration in seconds when no specific duration is set.
    |
    */
    'default_duration' => env('CDN_HEADERS_DEFAULT_DURATION', 3600),

    /*
    |--------------------------------------------------------------------------
    | Cacheable Routes
    |--------------------------------------------------------------------------
    |
    | Define which routes should have CDN cache headers and their durations.
    | Format: 'route.name' => duration_in_seconds
    | Use '*' as wildcard: 'products.*' matches all routes starting with 'products.'
    |
    */
    'routes' => [
        // Examples:
        // 'home' => 300,                    // 5 minutes
        // 'products.index' => 3600,         // 1 hour
        // 'products.show' => 7200,          // 2 hours
        // 'products.*' => 3600,             // All product routes: 1 hour
        // 'api.v1.*' => 600,                // All API v1 routes: 10 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Patterns
    |--------------------------------------------------------------------------
    |
    | Alternative to named routes, you can use URL patterns.
    | Useful when you don't have named routes.
    |
    */
    'patterns' => [
        // '/products/*' => 3600,
        // '/api/v1/*' => 600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Routes
    |--------------------------------------------------------------------------
    |
    | Routes that should never have CDN headers, even if they match patterns.
    |
    */
    'excluded_routes' => [
        // 'admin.*',
        // 'dashboard.*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Headers
    |--------------------------------------------------------------------------
    |
    | Additional headers to set on cacheable responses.
    |
    */
    'custom_headers' => [
        // 'X-Cache-Status' => 'HIT',
    ],

    /*
    |--------------------------------------------------------------------------
    | Surrogate Control
    |--------------------------------------------------------------------------
    |
    | Enable Surrogate-Control headers for reverse proxy caches like Fastly.
    |
    */
    'surrogate_control' => env('CDN_HEADERS_SURROGATE', false),

    /*
    |--------------------------------------------------------------------------
    | Stale While Revalidate
    |--------------------------------------------------------------------------
    |
    | Add stale-while-revalidate directive to allow serving stale content
    | while fetching fresh content in the background.
    |
    */
    'stale_while_revalidate' => env('CDN_HEADERS_SWR', null),

    /*
    |--------------------------------------------------------------------------
    | Stale If Error
    |--------------------------------------------------------------------------
    |
    | Add stale-if-error directive to allow serving stale content
    | when origin is unavailable.
    |
    */
    'stale_if_error' => env('CDN_HEADERS_SIE', null),
];
