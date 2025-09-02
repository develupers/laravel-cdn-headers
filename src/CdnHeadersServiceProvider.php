<?php

namespace Develupers\CdnHeaders;

use Develupers\CdnHeaders\Commands\CdnHeadersClearCommand;
use Develupers\CdnHeaders\Commands\CdnHeadersStatusCommand;
use Develupers\CdnHeaders\Commands\CdnHeadersTestCommand;
use Develupers\CdnHeaders\Http\Middleware\CdnHeadersMiddleware;
use Illuminate\Contracts\Http\Kernel;
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

    public function packageBooted(): void
    {
        // Register middleware alias
        $this->app['router']->aliasMiddleware('cdn-headers', CdnHeadersMiddleware::class);

        // Auto-register as global middleware if configured
        if (config('cdn-headers.middleware_registration') === 'global') {
            $kernel = $this->app->make(Kernel::class);
            $kernel->pushMiddleware(CdnHeadersMiddleware::class);
        }
    }
}
