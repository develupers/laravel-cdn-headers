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
    | Remove CSRF Tokens
    |--------------------------------------------------------------------------
    |
    | When true, removes CSRF tokens from HTML responses before caching.
    | This prevents security issues where all users receive the same token.
    | CSRF tokens should never be cached as they are unique per session.
    |
    */
    'remove_csrf_tokens' => env('CDN_HEADERS_REMOVE_CSRF', true),

    /*
    |--------------------------------------------------------------------------
    | Inject CSRF Loader Script
    |--------------------------------------------------------------------------
    |
    | When true, automatically injects JavaScript to load CSRF tokens
    | dynamically on cached pages that need them.
    |
    */
    'inject_csrf_loader' => env('CDN_HEADERS_INJECT_CSRF_LOADER', true),

    /*
    |--------------------------------------------------------------------------
    | CSRF Loader Routes
    |--------------------------------------------------------------------------
    |
    | Determines which routes should have the CSRF loader script injected.
    | - 'auto': Inject on any route where CSRF tokens were removed
    | - 'routes': Array of specific route names that need the loader
    |
    */
    'csrf_loader_routes' => [
        'auto' => true,  // Automatically inject where CSRF was removed

        // Or specify exact routes (used when auto is false)
        'routes' => [
            // 'contact.show',
            // 'auth.login',
            // 'auth.register',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | CSRF Token Endpoint
    |--------------------------------------------------------------------------
    |
    | The endpoint used to fetch fresh CSRF tokens via AJAX.
    | This endpoint should return JSON with a 'csrf_token' field.
    |
    */
    'csrf_endpoint' => env('CDN_HEADERS_CSRF_ENDPOINT', '/users/csrf-token'),

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
        // Artist routes
        'artists.show' => 21600,           // 6 hours
        'artists.showById' => 21600,       // 6 hours
        'artists.show.tracks' => 21600,    // 6 hours
        'artists.show.albums' => 21600,    // 6 hours

        // Album & Track routes
        'albums.show' => 21600,            // 6 hours
        'tracks.show' => 21600,            // 6 hours

        // Home & Charts
        'home.show' => 300,                // 5 minutes
        'chart.artists' => 86400,          // 24 hours
        'chart.tracks' => 86400,           // 24 hours

        // Other cacheable routes from responsecache config
        'contact.show' => 259200,          // 3 days
        'page.show' => 259200,             // 3 days
        'hashtags.show' => 86400,          // 24 hours
        'playlist.new-tracks' => 86400,    // 24 hours
        'playlist.new-albums' => 86400,    // 24 hours
        'search.index' => 3600,            // 1 hour
        'users.show' => 21600,             // 6 hours
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

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Cloudflare CDN cache management.
    |
    */
    'cloudflare' => [
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
    ],
];
