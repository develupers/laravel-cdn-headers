<?php

namespace Develupers\CdnHeaders\Commands;

use Illuminate\Console\Command;

class CdnHeadersStatusCommand extends Command
{
    protected $signature = 'cdn-headers:status';

    protected $description = 'Show CDN headers configuration status';

    public function handle(): int
    {
        $this->info('CDN Headers Package Status');
        $this->info('===========================');
        $this->newLine();

        // Check if enabled
        $enabled = config('cdn-headers.enabled', true);
        $this->line('Status: ' . ($enabled ? '<fg=green>Enabled</>' : '<fg=red>Disabled</>'));

        // Registration mode
        $registration = config('cdn-headers.middleware_registration', 'manual');
        $this->line('Registration: ' . ($registration === 'global' ? '<fg=green>Global (automatic)</>' : '<fg=yellow>Manual</>'));

        // Settings
        $this->newLine();
        $this->info('Settings:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Skip Authenticated Users', config('cdn-headers.skip_authenticated') ? 'Yes' : 'No'],
                ['Remove Cookies', config('cdn-headers.remove_cookies') ? 'Yes' : 'No'],
                ['Remove Vary Cookie', config('cdn-headers.remove_vary_cookie') ? 'Yes' : 'No'],
                ['Logging Enabled', config('cdn-headers.logging') ? 'Yes' : 'No'],
                ['Default Duration', config('cdn-headers.default_duration') . ' seconds'],
            ]
        );

        // Configured routes
        $routes = config('cdn-headers.routes', []);
        if (!empty($routes)) {
            $this->newLine();
            $this->info('Configured Routes:');
            $routeData = [];
            foreach ($routes as $route => $duration) {
                $routeData[] = [$route, $this->formatDuration($duration)];
            }
            $this->table(['Route', 'Cache Duration'], $routeData);
        }

        // URL patterns
        $patterns = config('cdn-headers.patterns', []);
        if (!empty($patterns)) {
            $this->newLine();
            $this->info('URL Patterns:');
            $patternData = [];
            foreach ($patterns as $pattern => $duration) {
                $patternData[] = [$pattern, $this->formatDuration($duration)];
            }
            $this->table(['Pattern', 'Cache Duration'], $patternData);
        }

        // Excluded routes
        $excluded = config('cdn-headers.excluded_routes', []);
        if (!empty($excluded)) {
            $this->newLine();
            $this->info('Excluded Routes:');
            foreach ($excluded as $route) {
                $this->line('  - ' . $route);
            }
        }

        return self::SUCCESS;
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
