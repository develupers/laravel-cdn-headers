<?php

namespace Develupers\CdnHeaders\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CdnHeadersMiddleware
{
    /**
     * Track if CSRF tokens were removed from the response.
     */
    protected bool $csrfRemoved = false;

    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Check if middleware is enabled
        if (! config('cdn-headers.enabled', true)) {
            return $response;
        }

        // Early return for non-GET/HEAD requests
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
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

        // Remove CSRF tokens if configured
        if (config('cdn-headers.remove_csrf_tokens', true)) {
            $this->removeCsrfTokens($response);
        }

        // Inject CSRF loader if configured and tokens were removed
        if (config('cdn-headers.inject_csrf_loader', true)) {
            $this->injectCsrfLoader($response, $routeName);
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
     */
    protected function matchesPattern(string $routeName, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        // First escape dots, then convert asterisks to regex wildcards
        $regex = str_replace('.', '\.', $pattern);
        $regex = str_replace('*', '.*', $regex);

        return (bool) preg_match('/^'.$regex.'$/', $routeName);
    }

    /**
     * Check if URL path matches pattern.
     */
    protected function matchesUrlPattern(string $path, string $pattern): bool
    {
        // Normalize paths
        $path = '/'.ltrim($path, '/');
        $pattern = '/'.ltrim($pattern, '/');

        // Convert wildcard pattern to regex
        $regex = str_replace(
            ['*', '/'],
            ['[^/]*', '\/'],
            $pattern
        );

        return (bool) preg_match('/^'.$regex.'$/', $path);
    }

    /**
     * Apply CDN headers to response.
     *
     * @param  \Illuminate\Http\Response  $response
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
     */
    protected function removeVaryCookie($response): void
    {
        $vary = $response->headers->get('Vary', '');

        if (! $vary) {
            return;
        }

        $varyParts = array_map('trim', explode(',', $vary));
        $varyParts = array_filter($varyParts, function ($part) {
            return strtolower($part) !== 'cookie';
        });

        if (! empty($varyParts)) {
            $response->headers->set('Vary', implode(', ', $varyParts));
        } else {
            $response->headers->remove('Vary');
        }
    }

    /**
     * Remove CSRF tokens from HTML responses.
     *
     * @param  \Illuminate\Http\Response  $response
     */
    protected function removeCsrfTokens($response): void
    {
        // Only process HTML responses
        $contentType = $response->headers->get('Content-Type', '');
        if (! str_contains($contentType, 'text/html')) {
            return;
        }

        $content = $response->getContent();
        $originalContent = $content;

        // Remove meta tag CSRF token
        $content = preg_replace(
            '/<meta\s+name=["\']csrf-token["\']\s+content=["\'][^"\']*["\']\s*\/?>/i',
            '<meta name="csrf-token" content="">',
            $content
        );

        // Remove JavaScript CSRF token in window.Laravel
        $content = preg_replace(
            '/<script>\s*window\.Laravel\s*=\s*\{[^}]*["\']csrfToken["\']\s*:\s*["\'][^"\']*["\'][^}]*\}[^<]*<\/script>/i',
            '<script>window.Laravel = {/* CSRF token removed for caching */}</script>',
            $content
        );

        // Alternative pattern for window.Laravel
        $content = preg_replace(
            '/window\.Laravel\s*=\s*\{[^}]*["\']csrfToken["\']\s*:\s*["\'][^"\']*["\'][^}]*\}/i',
            'window.Laravel = {/* CSRF token removed for caching */}',
            $content
        );

        // Remove standalone CSRF token assignments
        $content = preg_replace(
            '/window\._token\s*=\s*["\'][^"\']*["\']/i',
            'window._token = null /* CSRF token removed for caching */',
            $content
        );

        // Remove CSRF tokens from form inputs
        $content = preg_replace(
            '/<input\s+([^>]*?)name=["\']_token["\']([^>]*?)value=["\'][^"\']*["\']([^>]*?)>/i',
            '<input $1name="_token"$2value=""$3>',
            $content
        );

        // Alternative pattern for form inputs (different attribute order)
        $content = preg_replace(
            '/<input\s+([^>]*?)value=["\'][^"\']*["\']([^>]*?)name=["\']_token["\']([^>]*?)>/i',
            '<input $1value=""$2name="_token"$3>',
            $content
        );

        // Track if tokens were actually removed
        if ($content !== $originalContent) {
            $this->csrfRemoved = true;
        }

        $response->setContent($content);
    }

    /**
     * Inject CSRF loader script into HTML responses.
     *
     * @param  \Illuminate\Http\Response  $response
     */
    protected function injectCsrfLoader($response, ?string $routeName): void
    {
        // Only process HTML responses
        $contentType = $response->headers->get('Content-Type', '');
        if (! str_contains($contentType, 'text/html')) {
            return;
        }

        // Check if we should inject the loader
        $shouldInject = false;
        $loaderConfig = config('cdn-headers.csrf_loader_routes', []);

        if ($loaderConfig['auto'] ?? true) {
            // Auto mode: inject if CSRF was removed
            $shouldInject = $this->csrfRemoved;
        } elseif ($routeName && isset($loaderConfig['routes'])) {
            // Manual mode: check if route is in the list
            foreach ($loaderConfig['routes'] as $pattern) {
                if ($this->matchesPattern($routeName, $pattern)) {
                    $shouldInject = true;
                    break;
                }
            }
        }

        if (! $shouldInject) {
            return;
        }

        $content = $response->getContent();
        $endpoint = config('cdn-headers.csrf_endpoint', '/users/csrf-token');

        $script = <<<'JS'
<script>
(function() {
    // Check if forms exist or AJAX libraries are present
    if (!document.querySelector('form') && !window.axios && typeof $ === 'undefined') return;
    
    // Load CSRF token
    fetch('ENDPOINT_PLACEHOLDER')
        .then(function(response) { return response.json(); })
        .then(function(data) {
            var token = data.csrf_token || data.token;
            if (!token) return;
            
            // Update or create meta tag
            var meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) {
                meta.content = token;
            } else {
                // Create meta tag if it doesn't exist
                meta = document.createElement('meta');
                meta.name = 'csrf-token';
                meta.content = token;
                document.head.appendChild(meta);
            }
            
            // Update window.Laravel
            if (typeof window.Laravel === 'object') {
                window.Laravel.csrfToken = token;
            } else {
                window.Laravel = { csrfToken: token };
            }
            
            // Update form inputs
            document.querySelectorAll('input[name="_token"]').forEach(function(input) {
                input.value = token;
            });
            
            // Configure axios if present
            if (window.axios) {
                window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
            }
            
            // Configure jQuery AJAX if present
            if (typeof $ !== 'undefined' && $.ajaxSetup) {
                $.ajaxSetup({
                    headers: { 'X-CSRF-TOKEN': token }
                });
            }
        })
        .catch(function(error) {
            console.error('Failed to load CSRF token:', error);
        });
})();
</script>
JS;

        // Replace the endpoint placeholder
        $script = str_replace('ENDPOINT_PLACEHOLDER', $endpoint, $script);

        // Inject before closing body tag
        $content = str_replace('</body>', $script."\n</body>", $content);

        $response->setContent($content);
    }
}
