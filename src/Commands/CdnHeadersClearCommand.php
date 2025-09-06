<?php

namespace Develupers\CdnHeaders\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CdnHeadersClearCommand extends Command
{
    protected $signature = 'cdn-headers:clear
                            {--zone= : Cloudflare Zone ID}
                            {--token= : Cloudflare API Token}
                            {--url=* : Specific URLs to purge}
                            {--all : Purge entire cache}';

    protected $description = 'Clear CDN cache (Cloudflare)';

    public function handle(): int
    {
        $zoneId = $this->option('zone') ?: config('cdn-headers.cloudflare.zone_id');
        $token = $this->option('token') ?: config('cdn-headers.cloudflare.api_token');

        if (! $zoneId || ! $token) {
            $this->error('Cloudflare Zone ID and API Token are required.');
            $this->line('Set them via --zone and --token options, or in your .env file:');
            $this->line('CLOUDFLARE_ZONE_ID=your-zone-id');
            $this->line('CLOUDFLARE_API_TOKEN=your-api-token');

            return self::FAILURE;
        }

        $urls = $this->option('url');
        $purgeAll = $this->option('all');

        if (! $purgeAll && empty($urls)) {
            $this->error('Please specify URLs to purge with --url or use --all to purge everything.');

            return self::FAILURE;
        }

        $this->info('Clearing Cloudflare cache...');

        try {
            $response = Http::withToken($token)
                ->post("https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache",
                    $purgeAll ? ['purge_everything' => true] : ['files' => $urls]
                );

            if ($response->successful()) {
                $this->info('✓ Cache cleared successfully!');

                if ($purgeAll) {
                    $this->line('Purged: Entire cache');
                } else {
                    $this->line('Purged URLs:');
                    foreach ($urls as $url) {
                        $this->line('  - '.$url);
                    }
                }

                return self::SUCCESS;
            } else {
                $this->error('Failed to clear cache.');
                $errors = $response->json()['errors'] ?? [];
                foreach ($errors as $error) {
                    $this->error($error['message'] ?? 'Unknown error');
                }

                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Error communicating with Cloudflare API: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
