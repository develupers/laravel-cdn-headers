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
- 🎯 Wildcard route patterns support
- 📊 Cloudflare integration for cache purging
- 🔍 Built-in debugging tools
- ⚡ Support for stale-while-revalidate and stale-if-error
- 🌐 Works with any CDN (Cloudflare, Fastly, CloudFront, etc.)

## Installation

### Install via Composer

Since the package is not yet on Packagist, you need to add the GitHub repository to your `composer.json`:

```bash
composer config repositories.laravel-cdn-headers vcs https://github.com/develupers/laravel-cdn-headers
```

Then require the package:

```bash
composer require develupers/laravel-cdn-headers:dev-main
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
