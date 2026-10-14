# Laravel CDN Headers

[![Latest Version on Packagist](https://img.shields.io/packagist/v/develupers/laravel-cdn-headers.svg?style=flat-square)](https://packagist.org/packages/develupers/laravel-cdn-headers)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/develupers/laravel-cdn-headers/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/develupers/laravel-cdn-headers/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/develupers/laravel-cdn-headers/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/develupers/laravel-cdn-headers/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/develupers/laravel-cdn-headers.svg?style=flat-square)](https://packagist.org/packages/develupers/laravel-cdn-headers)

Automatically set proper cache-control headers for CDN caching in Laravel applications. This package helps you optimize your application's performance by enabling CDN caching for public pages while ensuring personalized content remains private.

## Features

- 🚀 Automatic cache-control header management
- 🔧 Flexible route-based configuration
- 🛡️ Automatic session cookie removal for cacheable responses
- 🔐 CSRF token removal for secure caching
- 🔄 Automatic CSRF token restoration via AJAX
- 🎟️ Single-use request nonces for endpoints that must not be replayed
- 🎯 Wildcard route patterns support
- 📊 Cloudflare integration for cache purging
- 🔍 Built-in debugging tools
- ⚡ Support for stale-while-revalidate and stale-if-error
- 🌐 Works with any CDN (Cloudflare, Fastly, CloudFront, etc.)

## Requirements

- PHP 8.3 or higher
- Laravel 11.x or 12.x

## Installation

### Install via Composer

Install the package from [Packagist](https://packagist.org/packages/develupers/laravel-cdn-headers) by running this command in your Laravel application's root directory:

```bash
composer require develupers/laravel-cdn-headers
```

Laravel automatically discovers the service provider. No custom Composer repository or manual provider registration is required.

If you previously followed the GitHub installation instructions, remove the old repository entry before running the command above:

```bash
composer config --unset repositories.laravel-cdn-headers
```

### Publish Configuration

After installation, publish the configuration file:

```bash
php artisan vendor:publish --provider="Develupers\CdnHeaders\CdnHeadersServiceProvider"
```

This will create a `config/cdn-headers.php` file where you can customize the package behavior.

### Quick Start

1. **Configure your cacheable routes** in `config/cdn-headers.php`:
   ```php
   'routes' => [
       'home' => 300,           // 5 minutes
       'products.*' => 3600,    // 1 hour for all product routes
       'api.*' => 600,          // 10 minutes for all API routes
   ],
   ```

2. **Verify configuration** with:
   ```bash
   php artisan cdn-headers:status
   ```

3. **Test a specific route** to see if it will be cached:
   ```bash
   php artisan cdn-headers:test /products
   ```

That's it! The middleware will automatically apply CDN headers to your configured routes.

## Configuration

The configuration file will be published to `config/cdn-headers.php`:

```php
return [
    // Enable/disable the middleware
    'enabled' => env('CDN_HEADERS_ENABLED', true),
    
    // Auto-register as global middleware or manual registration
    'middleware_registration' => env('CDN_HEADERS_REGISTRATION', 'global'),
    
    // Skip authenticated users
    'skip_authenticated' => true,
    
    // Remove cookies from cacheable responses
    'remove_cookies' => true,
    
    // Define cacheable routes and their TTL in seconds
    'routes' => [
        'home' => 300,                    // 5 minutes
        'products.index' => 3600,         // 1 hour
        'products.show' => 7200,          // 2 hours
        'products.*' => 3600,             // All product routes
        'api.v1.*' => 600,                // All API v1 routes
    ],
    
    // URL patterns (for routes without names)
    'patterns' => [
        '/blog/*' => 1800,
        '/api/v2/*' => 600,
    ],
    
    // Exclude specific routes
    'excluded_routes' => [
        'admin.*',
        'dashboard.*',
    ],
    
    // Security settings
    'remove_csrf_tokens' => true,    // Remove CSRF tokens from cached pages
];
```

## Usage

### Automatic Registration (Default)

By default, the middleware is automatically registered globally. Just configure your routes in the config file and you're ready to go!

### Manual Registration

If you prefer manual control, set `middleware_registration` to `'manual'` in the config, then register the middleware in your `app/Http/Kernel.php`:

```php
// As global middleware (runs on all routes)
protected $middleware = [
    // ... other middleware
    \Develupers\CdnHeaders\Http\Middleware\CdnHeadersMiddleware::class,
];
```

**Note**: The middleware automatically detects authenticated users and sends appropriate cache headers (`private` for logged-in, `public` for anonymous).

### Cloudflare Setup

When using Cloudflare as your CDN, you need to configure cache bypass rules for authenticated users. This prevents logged-in users from receiving cached anonymous pages.

#### Why Cloudflare Configuration is Needed

Cloudflare's edge servers don't process PHP or Laravel sessions. When a page is cached, Cloudflare serves it to everyone who requests that URL, regardless of authentication status. To fix this, you need to tell Cloudflare when to bypass the cache.

#### Step 1: Create Cache Eligibility Rules

By default, Cloudflare only caches static assets (JS, CSS, images). To enable HTML caching while respecting your Laravel cache headers:

1. Go to your Cloudflare Dashboard
2. Navigate to **Caching** → **Cache Rules**
3. Click **Create rule**
4. Configure the rule:

   **Rule name**: `Enable HTML Caching`
   
   **When incoming requests match...**
   - Field: `URI Path`
   - Operator: `equals` (or use `starts with` for multiple paths)
   - Value: `/` (or specify paths you want cached, e.g., `/song/*`)
   
   **Then...**
   - **Cache eligibility**: `Eligible for cache`
   - **Edge TTL**: 
     - Select: `Use cache-control header if present, bypass cache if not`
     
5. Click **Deploy**

**How this works**:
- Cloudflare will respect the `Cache-Control` headers from your Laravel application
- Pages with `Cache-Control: public` get cached (anonymous users)
- Pages with `Cache-Control: private` bypass cache (logged-in users)
- Your `cdn-headers.php` config remains in control of cache durations

#### Step 2: Create Cache Bypass Rule for Authenticated Users

To ensure authenticated users always receive fresh, personalized content:

1. Go to your Cloudflare Dashboard
2. Navigate to **Caching** → **Cache Rules**
3. Click **Create rule**
4. Configure the rule:

   **Rule name**: `Laravel Login Bypass`
   
   **If incoming requests match...**
   - Select: `Custom filter expression`
   
   **When incoming requests match...**
   - Field: `Cookie`
   - Operator: `contains`
   - Value: `remember_web_`
   
   **Then...**
   - **Cache eligibility**: `Bypass cache`
   
5. Click **Deploy**

**Why this works**: The `remember_web_*` cookie is only present for authenticated Laravel users. This rule ensures they always bypass Cloudflare's cache and get fresh content from your origin server.

#### Step 3: Understanding the Cookie Pattern

Laravel uses different cookies for session management:

- `laravel_session`: Present for ALL users (authenticated and anonymous)
- `remember_web_*`: ONLY present for authenticated users who checked "Remember Me"
- `XSRF-TOKEN`: CSRF protection token (present for all users)

The `remember_web_*` cookie is the most reliable indicator of an authenticated user.

#### Alternative: Session-Based Bypass

If your application doesn't use "Remember Me" functionality, you may need to:

1. Set a custom cookie when users log in:
   ```php
   // In your login controller
   Cookie::queue('authenticated', '1', 60 * 24 * 30);
   ```

2. Clear it on logout:
   ```php
   // In your logout controller
   Cookie::queue(Cookie::forget('authenticated'));
   ```

3. Use this custom cookie in your Cloudflare rule instead

#### Step 4: Verify Your Configuration

After setting up the Cloudflare rule:

1. **Test as anonymous user**: Page should be served from cache (`cf-cache-status: HIT`)
2. **Test as logged-in user**: Page should bypass cache (`cf-cache-status: BYPASS`)
3. **Clear Cloudflare cache** after making changes to ensure fresh start

#### Troubleshooting

**Issue**: Logged-in users still see cached pages
- **Solution**: Ensure the Cloudflare rule is active and matches your cookie pattern
- **Check**: Browser DevTools → Application → Cookies to verify cookie presence

**Issue**: Pages aren't being cached at all
- **Solution**: Check that `skip_authenticated` is `true` in your config
- **Verify**: Response headers should include `Cache-Control: public` for anonymous users

**Issue**: CSRF token errors on cached pages
- **Solution**: Enable `inject_csrf_loader` in config to dynamically load tokens
- **Note**: Ensure your CSRF endpoint is excluded from caching

#### Important Notes

1. **Two-Part Solution**: You need BOTH:
   - Cloudflare rules (to bypass cache for existing cached content)
   - Proper middleware configuration (to prevent future bad caches)

2. **Cache Purging**: After implementing these changes, purge your Cloudflare cache to remove any incorrectly cached authenticated pages

3. **Performance Impact**: Authenticated users will always hit your origin server, which is intentional to serve personalized content

### Artisan Commands

Check configuration status:
```bash
php artisan cdn-headers:status
```

Test a route:
```bash
php artisan cdn-headers:test /products
```

Clear CDN cache (Cloudflare):
```bash
php artisan cdn-headers:clear --all
php artisan cdn-headers:clear --url=https://example.com/products
```

## Security Considerations

### CSRF Token Removal

When `remove_csrf_tokens` is enabled (default), the package automatically removes CSRF tokens from HTML responses before caching. This is crucial because:

- **Security**: Prevents all users from receiving the same CSRF token
- **Cache Effectiveness**: Allows CDNs to properly cache pages
- **Session Independence**: Cached pages work regardless of user sessions

CSRF tokens are removed from:
- `<meta name="csrf-token" content="...">` tags
- `window.Laravel = {"csrfToken": "..."}` JavaScript objects
- Other common CSRF token patterns

**Important**: Only enable caching for public pages that don't require CSRF protection. Pages with forms should typically not be cached.

### CSRF Token Auto-Loading

When CSRF tokens are removed for caching, the package can automatically inject JavaScript to restore them via AJAX. This allows forms on cached pages to work correctly:

```php
'inject_csrf_loader' => true,  // Enable auto-injection

'csrf_loader_routes' => [
    'auto' => true,  // Inject on any page where CSRF was removed
    
    // Or specify exact routes (when auto is false)
    'routes' => [
        'contact.show',
        'auth.login',
        'auth.register',
    ],
],

'csrf_endpoint' => '/users/csrf-token',  // Your CSRF endpoint
```

The injected script will:
- Fetch a fresh CSRF token from your endpoint
- Update the `<meta name="csrf-token">` tag (or create it if it doesn't exist)
- Set `window.Laravel.csrfToken`
- Update all form `_token` inputs
- Configure axios and jQuery AJAX headers

**Note**: You must have a CSRF token endpoint that returns JSON:
```php
Route::get('/users/csrf-token', function () {
    return response()->json(['csrf_token' => csrf_token()]);
});
```

### Request Nonces

A CSRF token is reusable for the whole session: a client that loads one page can replay it against an endpoint any number of times. For endpoints where that matters — for example, a request that triggers a paid third-party API call — enable request nonces. A nonce is spent by the first request that presents it, and only the session it was issued to can spend it.

Nonces are disabled by default. To enable them:

```php
// config/cdn-headers.php
'nonce' => [
    'enabled' => env('CDN_HEADERS_NONCE_ENABLED', false),
    'ttl' => env('CDN_HEADERS_NONCE_TTL', 1800), // seconds
    'per_session_limit' => [
        'per_minute' => env('CDN_HEADERS_NONCE_PER_MINUTE', 30), // null = no limit
        'per_day' => env('CDN_HEADERS_NONCE_PER_DAY', 500),
    ],
    'meta_name' => 'request-nonce',
    'header' => 'X-Request-Nonce',
    'store' => env('CDN_HEADERS_NONCE_STORE'), // null = the default cache store
],
```

Every page view that renders `@nonceMeta` writes a few short-lived cache keys (the nonce, its spent marker and the per-session limits). On the `database` cache store expired rows are only removed when the same key is read again, so they pile up; point `store` at a store that expires keys itself, such as `redis`, to keep them out of your database.

**1. Render the nonce in your layout's `<head>`:**

```blade
@nonceMeta
```

This outputs `<meta name="request-nonce" content="...">` and a small script exposing `window.CdnHeaders`. On CDN-cached pages the middleware blanks the nonce (just like the CSRF token) and the script fetches fresh tokens from your CSRF endpoint instead — so pages rendering `@nonceMeta` don't also get the CSRF loader injected.

**2. Return a nonce from your CSRF endpoint:**

```php
use Develupers\CdnHeaders\Nonce\NonceManager;

Route::get('/users/csrf-token', function (NonceManager $nonces) {
    return response()->json([
        'csrf_token' => csrf_token(),
        'nonce' => $nonces->issue(),
    ]);
});
```

**3. Protect the endpoint:**

```php
Route::post('/expensive', ExpensiveController::class)->middleware('cdn-headers.nonce');
```

Requests without a valid, unspent nonce from their own session receive a 403. Once a session has used up its nonce budget, it gets a **429** instead, with a `Retry-After` header (and `retry_after` in JSON responses), so clients can stop retrying and tell the visitor when to try again. The middleware passes everything through while nonces are disabled.

**4. Send the nonce from the browser:**

```js
// Wait for tokens (already present on uncached pages, fetched on cached ones)
const tokens = await window.CdnHeaders.ready;

await fetch('/expensive', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': tokens.csrfToken, 'X-Request-Nonce': tokens.nonce },
});

// A nonce is single-use: fetch a fresh one before the next request
const next = await window.CdnHeaders.refresh();
```

A `cdn-headers:tokens-loaded` event is also dispatched on `document` whenever tokens are loaded or refreshed.

**Notes:**
- Single use relies on an atomic cache `add`. Redis, Memcached and database stores provide it; the file store does not.
- Per-session limits cap how many nonces one session can be issued. Past the limit, `issue()` returns `null` and requests from that session get a 429 until the window resets. `NonceManager::retryAfter()` reports the wait without counting an attempt.
- The lifetime can be generous: single use is what prevents replay, not a short TTL.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Omar Robinson](https://github.com/orobinson)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
