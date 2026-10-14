<?php

namespace Develupers\CdnHeaders;

use Develupers\CdnHeaders\Commands\CdnHeadersClearCommand;
use Develupers\CdnHeaders\Commands\CdnHeadersStatusCommand;
use Develupers\CdnHeaders\Commands\CdnHeadersTestCommand;
use Develupers\CdnHeaders\Http\Middleware\CdnHeadersMiddleware;
use Develupers\CdnHeaders\Http\Middleware\VerifyRequestNonce;
use Develupers\CdnHeaders\Nonce\NonceManager;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Blade;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CdnHeadersServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-cdn-headers')
            ->hasConfigFile('cdn-headers')
            ->hasCommands([
                CdnHeadersStatusCommand::class,
                CdnHeadersTestCommand::class,
                CdnHeadersClearCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // Nonces and their issuance limits live in one store: `nonce.store`, or the application's default cache
        // and rate limiter (which honours `cache.limiter`) when it isn't set.
        $this->app->bind(NonceManager::class, function ($app) {
            $store = config('cdn-headers.nonce.store');

            if (! $store) {
                return new NonceManager($app['cache']->store(), $app->make(RateLimiter::class));
            }

            $cache = $app['cache']->store($store);

            return new NonceManager($cache, new RateLimiter($cache));
        });
    }

    public function packageBooted(): void
    {
        // Register middleware alias
        $this->app['router']->aliasMiddleware('cdn-headers', CdnHeadersMiddleware::class);
        $this->app['router']->aliasMiddleware('cdn-headers.nonce', VerifyRequestNonce::class);

        // Renders nothing while the nonce feature is disabled
        Blade::directive('nonceMeta', fn () => '<?php echo app(\\'.NonceManager::class.'::class)->tags(); ?>');

        // Auto-register as global middleware if configured
        if (config('cdn-headers.middleware_registration') === 'global') {
            $kernel = $this->app->make(Kernel::class);
            $kernel->pushMiddleware(CdnHeadersMiddleware::class);
        }
    }
}
