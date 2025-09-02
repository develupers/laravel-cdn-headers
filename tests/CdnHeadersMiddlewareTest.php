<?php

use Develupers\CdnHeaders\Http\Middleware\CdnHeadersMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->middleware = new CdnHeadersMiddleware();
});

it('adds cdn headers to configured routes', function () {
    config(['cdn-headers.routes' => [
        'test.route' => 3600,
    ]]);

    Route::get('/test', fn() => 'test')->name('test.route');

    $request = Request::create('/test', 'GET');
    $request->setRouteResolver(fn() => Route::getRoutes()->match($request));

    $response = $this->middleware->handle($request, function () {
        return new Response('test content');
    });

    expect($response->headers->get('Cache-Control'))->toBe('public, max-age=3600, s-maxage=3600');
});

it('removes cookies when configured', function () {
    config([
        'cdn-headers.routes' => ['test.route' => 3600],
        'cdn-headers.remove_cookies' => true,
    ]);

    Route::get('/test', fn() => 'test')->name('test.route');

    $request = Request::create('/test', 'GET');
    $request->setRouteResolver(fn() => Route::getRoutes()->match($request));

    $response = $this->middleware->handle($request, function () {
        $response = new Response('test content');
        $response->headers->set('Set-Cookie', 'test=value');
        return $response;
    });

    expect($response->headers->get('Set-Cookie'))->toBeNull();
});

it('skips authenticated users when configured', function () {
    config([
        'cdn-headers.routes' => ['test.route' => 3600],
        'cdn-headers.skip_authenticated' => true,
    ]);

    Route::get('/test', fn() => 'test')->name('test.route');

    // Mock authenticated user
    $this->actingAs(Mockery::mock('User'));

    $request = Request::create('/test', 'GET');
    $request->setRouteResolver(fn() => Route::getRoutes()->match($request));

    $response = $this->middleware->handle($request, function () {
        return new Response('test content');
    });

    expect($response->headers->get('Cache-Control'))->toBeNull();
});

it('matches wildcard route patterns', function () {
    config(['cdn-headers.routes' => [
        'products.*' => 7200,
    ]]);

    Route::get('/products', fn() => 'test')->name('products.index');

    $request = Request::create('/products', 'GET');
    $request->setRouteResolver(fn() => Route::getRoutes()->match($request));

    $response = $this->middleware->handle($request, function () {
        return new Response('test content');
    });

    expect($response->headers->get('Cache-Control'))->toBe('public, max-age=7200, s-maxage=7200');
});

it('matches url patterns', function () {
    config(['cdn-headers.patterns' => [
        '/api/*' => 600,
    ]]);

    $request = Request::create('/api/users', 'GET');

    $response = $this->middleware->handle($request, function () {
        return new Response('test content');
    });

    expect($response->headers->get('Cache-Control'))->toBe('public, max-age=600, s-maxage=600');
});

it('excludes configured routes', function () {
    config([
        'cdn-headers.routes' => ['admin.*' => 3600],
        'cdn-headers.excluded_routes' => ['admin.*'],
    ]);

    Route::get('/admin', fn() => 'test')->name('admin.dashboard');

    $request = Request::create('/admin', 'GET');
    $request->setRouteResolver(fn() => Route::getRoutes()->match($request));

    $response = $this->middleware->handle($request, function () {
        return new Response('test content');
    });

    expect($response->headers->get('Cache-Control'))->toBeNull();
});

it('only processes get and head requests', function () {
    config(['cdn-headers.routes' => ['test.route' => 3600]]);

    Route::post('/test', fn() => 'test')->name('test.route');

    $request = Request::create('/test', 'POST');
    $request->setRouteResolver(fn() => Route::getRoutes()->match($request));

    $response = $this->middleware->handle($request, function () {
        return new Response('test content');
    });

    expect($response->headers->get('Cache-Control'))->toBeNull();
});

it('adds stale directives when configured', function () {
    config([
        'cdn-headers.routes' => ['test.route' => 3600],
        'cdn-headers.stale_while_revalidate' => 86400,
        'cdn-headers.stale_if_error' => 604800,
    ]);

    Route::get('/test', fn() => 'test')->name('test.route');

    $request = Request::create('/test', 'GET');
    $request->setRouteResolver(fn() => Route::getRoutes()->match($request));

    $response = $this->middleware->handle($request, function () {
        return new Response('test content');
    });

    expect($response->headers->get('Cache-Control'))
        ->toBe('public, max-age=3600, s-maxage=3600, stale-while-revalidate=86400, stale-if-error=604800');
});

it('adds surrogate control when configured', function () {
    config([
        'cdn-headers.routes' => ['test.route' => 3600],
        'cdn-headers.surrogate_control' => true,
    ]);

    Route::get('/test', fn() => 'test')->name('test.route');

    $request = Request::create('/test', 'GET');
    $request->setRouteResolver(fn() => Route::getRoutes()->match($request));

    $response = $this->middleware->handle($request, function () {
        return new Response('test content');
    });

    expect($response->headers->get('Surrogate-Control'))->toBe('max-age=3600');
});

it('removes vary cookie header when configured', function () {
    config([
        'cdn-headers.routes' => ['test.route' => 3600],
        'cdn-headers.remove_vary_cookie' => true,
    ]);

    Route::get('/test', fn() => 'test')->name('test.route');

    $request = Request::create('/test', 'GET');
    $request->setRouteResolver(fn() => Route::getRoutes()->match($request));

    $response = $this->middleware->handle($request, function () {
        $response = new Response('test content');
        $response->headers->set('Vary', 'Accept-Encoding, Cookie');
        return $response;
    });

    expect($response->headers->get('Vary'))->toBe('Accept-Encoding');
});

it('can be disabled via config', function () {
    config([
        'cdn-headers.enabled' => false,
        'cdn-headers.routes' => ['test.route' => 3600],
    ]);

    Route::get('/test', fn() => 'test')->name('test.route');

    $request = Request::create('/test', 'GET');
    $request->setRouteResolver(fn() => Route::getRoutes()->match($request));

    $response = $this->middleware->handle($request, function () {
        return new Response('test content');
    });

    expect($response->headers->get('Cache-Control'))->toBeNull();
});

it('applies custom headers when configured', function () {
    config([
        'cdn-headers.routes' => ['test.route' => 3600],
        'cdn-headers.custom_headers' => [
            'X-Cache-Status' => 'CDN',
            'X-Custom' => 'Value',
        ],
    ]);

    Route::get('/test', fn() => 'test')->name('test.route');

    $request = Request::create('/test', 'GET');
    $request->setRouteResolver(fn() => Route::getRoutes()->match($request));

    $response = $this->middleware->handle($request, function () {
        return new Response('test content');
    });

    expect($response->headers->get('X-Cache-Status'))->toBe('CDN');
    expect($response->headers->get('X-Custom'))->toBe('Value');
});
