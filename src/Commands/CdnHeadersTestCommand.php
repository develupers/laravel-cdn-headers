<?php

namespace Develupers\CdnHeaders\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class CdnHeadersTestCommand extends Command
{
    protected $signature = 'cdn-headers:test {url} {--method=GET}';

    protected $description = 'Test CDN headers for a given URL';

    public function handle(): int
    {
        $url = $this->argument('url');
        $method = $this->option('method');

        $this->info("Testing CDN headers for: {$url}");
        $this->info("Method: {$method}");
        $this->newLine();

        // Create a request
        $request = Request::create($url, $method);

        // Try to match the route
        try {
            $route = app('router')->getRoutes()->match($request);
            $request->setRouteResolver(function () use ($route) {
                return $route;
            });
        } catch (\Exception $e) {
            $this->error('Could not match route for URL: ' . $url);
            return self::FAILURE;
        }

        $routeName = $route->getName();
        $this->line('Matched Route: ' . ($routeName ?: '<no name>'));

        // Check if route is configured
        $routes = config('cdn-headers.routes', []);
        $patterns = config('cdn-headers.patterns', []);
        $excluded = config('cdn-headers.excluded_routes', []);

        // Check if excluded
        $isExcluded = false;
        foreach ($excluded as $pattern) {
            if ($this->matchesPattern($routeName, $pattern)) {
                $isExcluded = true;
                break;
            }
        }

        if ($isExcluded) {
            $this->warn('⚠ This route is EXCLUDED from CDN caching');
            return self::SUCCESS;
        }

        // Find cache duration
        $duration = null;
        $matchedBy = null;

        // Check exact route match
        if ($routeName && isset($routes[$routeName])) {
            $duration = $routes[$routeName];
            $matchedBy = "Route: {$routeName}";
        }

        // Check route patterns
        if (!$duration && $routeName) {
            foreach ($routes as $pattern => $d) {
                if ($this->matchesPattern($routeName, $pattern)) {
                    $duration = $d;
                    $matchedBy = "Route Pattern: {$pattern}";
                    break;
                }
            }
        }

        // Check URL patterns
        if (!$duration) {
            $path = parse_url($url, PHP_URL_PATH) ?: '/';
            foreach ($patterns as $pattern => $d) {
                if ($this->matchesUrlPattern($path, $pattern)) {
                    $duration = $d;
                    $matchedBy = "URL Pattern: {$pattern}";
                    break;
                }
            }
        }

        if ($duration) {
            $this->info('✓ This route WILL have CDN headers');
            $this->line('Matched by: ' . $matchedBy);
            $this->line('Cache duration: ' . $this->formatDuration($duration));

            $this->newLine();
            $this->info('Headers that will be set:');

            $headers = [
                'Cache-Control' => $this->buildCacheControl($duration),
            ];

            if (config('cdn-headers.surrogate_control')) {
                $headers['Surrogate-Control'] = "max-age={$duration}";
            }

            foreach (config('cdn-headers.custom_headers', []) as $header => $value) {
                $headers[$header] = $value;
            }

            foreach ($headers as $header => $value) {
                $this->line("  {$header}: {$value}");
            }

            if (config('cdn-headers.remove_cookies')) {
                $this->newLine();
                $this->warn('Note: Set-Cookie headers will be removed');
            }
        } else {
            $this->warn('✗ This route will NOT have CDN headers');
            $this->line('Add it to your cdn-headers.php config file to enable caching.');
        }

        return self::SUCCESS;
    }

    protected function matchesPattern(string $routeName, string $pattern): bool
    {
        $regex = str_replace(
            ['*', '.'],
            ['.*', '\.'],
            $pattern
        );
        return (bool) preg_match('/^' . $regex . '$/', $routeName);
    }

    protected function matchesUrlPattern(string $path, string $pattern): bool
    {
        $path = '/' . ltrim($path, '/');
        $pattern = '/' . ltrim($pattern, '/');
        $regex = str_replace(
            ['*', '/'],
            ['[^/]*', '\/'],
            $pattern
        );
        return (bool) preg_match('/^' . $regex . '$/', $path);
    }

    protected function buildCacheControl(int $duration): string
    {
        $directives = [
            'public',
            "max-age={$duration}",
            "s-maxage={$duration}",
        ];

        if ($swr = config('cdn-headers.stale_while_revalidate')) {
            $directives[] = "stale-while-revalidate={$swr}";
        }

        if ($sie = config('cdn-headers.stale_if_error')) {
            $directives[] = "stale-if-error={$sie}";
        }

        return implode(', ', $directives);
    }

    protected function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' seconds';
        } elseif ($seconds < 3600) {
            return round($seconds / 60) . ' minutes';
        } elseif ($seconds < 86400) {
            return round($seconds / 3600, 1) . ' hours';
        } else {
            return round($seconds / 86400, 1) . ' days';
        }
    }
}
