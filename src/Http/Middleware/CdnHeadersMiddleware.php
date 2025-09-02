<?php

namespace Develupers\CdnHeaders\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CdnHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Check if middleware is enabled
        if (!config('cdn-headers.enabled', true)) {
            return $response;
        }

        // Early return for non-GET/HEAD requests
        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            return $response;
        }

        // Skip if user is authenticated (if configured)
        if (config('cdn-headers.skip_authenticated', true) && auth()->check()) {
            return $response;
        }

        // Get route name and path
        $routeName = $request->route()?->getName();
        $path = $request->path();

        // Check if route is excluded
        if ($routeName && $this->isRouteExcluded($routeName)) {
            return $response;
        }

        // Get cache duration
        $duration = $this->getCacheDuration($routeName, $path);

        if ($duration === null) {
            return $response;
        }

        // Apply CDN headers
        $this->applyCdnHeaders($response, $duration);

        // Remove cookies if configured
        if (config('cdn-headers.remove_cookies', true)) {
            $response->headers->remove('Set-Cookie');
        }

        // Remove Vary: Cookie header if configured
        if (config('cdn-headers.remove_vary_cookie', true)) {
            $this->removeVaryCookie($response);
        }

        // Apply custom headers
        foreach (config('cdn-headers.custom_headers', []) as $header => $value) {
            $response->headers->set($header, $value);
        }

        // Log if enabled
        if (config('cdn-headers.logging', false)) {
            Log::info('CDN Headers Applied', [
                'route' => $routeName,
                'path' => $path,
                'duration' => $duration,
                'cache-control' => $response->headers->get('Cache-Control'),
            ]);
        }

        return $response;
    }

    /**
     * Check if route is excluded.
     *
     * @param  string  $routeName
     * @return bool
     */
    protected function isRouteExcluded(string $routeName): bool
    {
        $excludedRoutes = config('cdn-headers.excluded_routes', []);

        foreach ($excludedRoutes as $pattern) {
            if ($this->matchesPattern($routeName, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get cache duration for route.
     *
     * @param  string|null  $routeName
     * @param  string  $path
     * @return int|null
     */
    protected function getCacheDuration(?string $routeName, string $path): ?int
    {
        // Check named routes first
        if ($routeName) {
            $routes = config('cdn-headers.routes', []);

            // Check exact match
            if (isset($routes[$routeName])) {
                return $routes[$routeName];
            }

            // Check wildcard patterns
            foreach ($routes as $pattern => $duration) {
                if ($this->matchesPattern($routeName, $pattern)) {
                    return $duration;
                }
            }
        }

        // Check URL patterns
        $patterns = config('cdn-headers.patterns', []);
        foreach ($patterns as $pattern => $duration) {
            if ($this->matchesUrlPattern($path, $pattern)) {
                return $duration;
            }
        }

        return null;
    }

    /**
     * Check if route name matches pattern.
     *
     * @param  string  $routeName
     * @param  string  $pattern
     * @return bool
     */
    protected function matchesPattern(string $routeName, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        $regex = str_replace(
            ['*', '.'],
            ['.*', '\.'],
            $pattern
        );

        return (bool) preg_match('/^' . $regex . '$/', $routeName);
    }

    /**
     * Check if URL path matches pattern.
     *
     * @param  string  $path
     * @param  string  $pattern
     * @return bool
     */
    protected function matchesUrlPattern(string $path, string $pattern): bool
    {
        // Normalize paths
        $path = '/' . ltrim($path, '/');
        $pattern = '/' . ltrim($pattern, '/');

        // Convert wildcard pattern to regex
        $regex = str_replace(
            ['*', '/'],
            ['[^/]*', '\/'],
            $pattern
        );

        return (bool) preg_match('/^' . $regex . '$/', $path);
    }

    /**
     * Apply CDN headers to response.
     *
     * @param  \Illuminate\Http\Response  $response
     * @param  int  $duration
     * @return void
     */
    protected function applyCdnHeaders($response, int $duration): void
    {
        $directives = [
            'public',
            "max-age={$duration}",
            "s-maxage={$duration}",
        ];

        // Add stale-while-revalidate if configured
        if ($swr = config('cdn-headers.stale_while_revalidate')) {
            $directives[] = "stale-while-revalidate={$swr}";
        }

        // Add stale-if-error if configured
        if ($sie = config('cdn-headers.stale_if_error')) {
            $directives[] = "stale-if-error={$sie}";
        }

        $response->headers->set('Cache-Control', implode(', ', $directives));

        // Add Surrogate-Control header if configured
        if (config('cdn-headers.surrogate_control', false)) {
            $response->headers->set('Surrogate-Control', "max-age={$duration}");
        }
    }

    /**
     * Remove Cookie from Vary header.
     *
     * @param  \Illuminate\Http\Response  $response
     * @return void
     */
    protected function removeVaryCookie($response): void
    {
        $vary = $response->headers->get('Vary', '');

        if (!$vary) {
            return;
        }

        $varyParts = array_map('trim', explode(',', $vary));
        $varyParts = array_filter($varyParts, function ($part) {
            return strtolower($part) !== 'cookie';
        });

        if (!empty($varyParts)) {
            $response->headers->set('Vary', implode(', ', $varyParts));
        } else {
            $response->headers->remove('Vary');
        }
    }
}
