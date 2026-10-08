<?php

use Develupers\CdnHeaders\Http\Middleware\CdnHeadersMiddleware;
use Develupers\CdnHeaders\Http\Middleware\VerifyRequestNonce;
use Develupers\CdnHeaders\Nonce\NonceManager;
use Develupers\CdnHeaders\Nonce\TokenScript;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** A request carrying its own freshly started session, bound as the current request. */
function nonceRequest(string $uri = '/'): Request
{
    $session = new Store('test', new ArraySessionHandler(120));
    $session->start();

    $request = Request::create($uri, 'GET');
    $request->setLaravelSession($session);
    app()->instance('request', $request);

    return $request;
}

/** Run an HTML page through the CDN middleware on a route cached for an hour. */
function cachedPage(string $html): string
{
    config(['cdn-headers.routes' => ['test.route' => 3600]]);
    Route::get('/test', fn () => 'test')->name('test.route');

    $request = Request::create('/test', 'GET');
    $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

    return (new CdnHeadersMiddleware)->handle($request, function () use ($html) {
        $response = new Response($html);
        $response->headers->set('Content-Type', 'text/html');

        return $response;
    })->getContent();
}

beforeEach(function () {
    config(['cdn-headers.nonce.enabled' => true]);
    $this->nonces = app(NonceManager::class);
});

it('accepts a nonce exactly once', function () {
    $request = nonceRequest();
    $nonce = $this->nonces->issue($request);

    expect($nonce)->toMatch('/^[a-f0-9]{64}$/');
    expect($this->nonces->consume($nonce, $request))->toBeTrue();
    expect($this->nonces->consume($nonce, $request))->toBeFalse();
});

it('rejects a nonce presented by another session without spending it', function () {
    $owner = nonceRequest();
    $nonce = $this->nonces->issue($owner);

    expect($this->nonces->consume($nonce, nonceRequest()))->toBeFalse();
    expect($this->nonces->consume($nonce, $owner))->toBeTrue();
});

it('rejects an expired nonce', function () {
    config(['cdn-headers.nonce.ttl' => 60]);
    $request = nonceRequest();
    $nonce = $this->nonces->issue($request);

    $this->travel(61)->seconds();

    expect($this->nonces->consume($nonce, $request))->toBeFalse();
});

it('rejects missing, malformed and never-issued nonces', function () {
    $request = nonceRequest();

    expect($this->nonces->consume(null, $request))->toBeFalse();
    expect($this->nonces->consume('', $request))->toBeFalse();
    expect($this->nonces->consume(str_repeat('z', 64), $request))->toBeFalse();
    expect($this->nonces->consume(bin2hex(random_bytes(32)), $request))->toBeFalse();
});

it('issues nothing without a session or while disabled', function () {
    expect($this->nonces->issue(Request::create('/')))->toBeNull();

    config(['cdn-headers.nonce.enabled' => false]);

    expect($this->nonces->issue(nonceRequest()))->toBeNull();
});

it('stops issuing once a session reaches its per-minute limit', function () {
    config(['cdn-headers.nonce.per_session_limit.per_minute' => 3]);
    $request = nonceRequest();

    foreach (range(1, 3) as $i) {
        expect($this->nonces->issue($request))->not->toBeNull();
    }

    expect($this->nonces->issue($request))->toBeNull();
    expect($this->nonces->issue(nonceRequest()))->not->toBeNull();

    $this->travel(61)->seconds();

    expect($this->nonces->issue($request))->not->toBeNull();
});

it('does not limit a window set to null', function () {
    config(['cdn-headers.nonce.per_session_limit' => ['per_minute' => null, 'per_day' => null]]);
    $request = nonceRequest();

    foreach (range(1, 50) as $i) {
        expect($this->nonces->issue($request))->not->toBeNull();
    }
});

it('renders the nonce meta tag and token script', function () {
    nonceRequest();

    expect(Blade::render('@nonceMeta'))
        ->toMatch('/<meta name="request-nonce" content="[a-f0-9]{64}">/')
        ->toContain(TokenScript::MARKER)
        ->toContain('var endpoint = "/users/csrf-token";');
});

it('renders nothing from @nonceMeta while disabled', function () {
    config(['cdn-headers.nonce.enabled' => false]);
    nonceRequest();

    expect(Blade::render('@nonceMeta'))->toBe('');
});

it('blanks the nonce on cached pages and leaves token loading to the token script', function () {
    config(['cdn-headers.csrf_loader_routes' => ['auto' => true]]);
    $tags = $this->nonces->tags(nonceRequest());

    $content = cachedPage('<html><head><meta name="csrf-token" content="abc">'.$tags.'</head><body></body></html>');

    expect($content)
        ->toContain('<meta name="request-nonce" content="">')
        ->not->toMatch('/content="[a-f0-9]{64}"/')
        ->toContain(TokenScript::MARKER)
        ->not->toContain('Failed to load CSRF token');
});

it('keeps the nonce on pages that are not CDN-cached', function () {
    $this->actingAs(Mockery::mock(Authenticatable::class));
    $tags = $this->nonces->tags(nonceRequest());

    $content = cachedPage('<html><head>'.$tags.'</head><body></body></html>');

    expect($content)->toMatch('/<meta name="request-nonce" content="[a-f0-9]{64}">/');
});

it('leaves cached pages exactly as v0.1.0 did while disabled', function () {
    config([
        'cdn-headers.nonce.enabled' => false,
        'cdn-headers.remove_csrf_tokens' => true,
        'cdn-headers.inject_csrf_loader' => true,
        'cdn-headers.csrf_loader_routes' => ['auto' => true],
        'cdn-headers.csrf_endpoint' => '/users/csrf-token',
    ]);

    // Line endings follow the checkout (core.autocrlf), not the package, so compare without them
    $lf = fn (string $html) => str_replace("\r\n", "\n", $html);

    expect($lf(cachedPage(file_get_contents(__DIR__.'/fixtures/page.html'))))
        ->toBe($lf(file_get_contents(__DIR__.'/fixtures/page.v0.1.0.cached.html')));
});

it('lets a request through once with a valid nonce and rejects the replay', function () {
    $request = nonceRequest();
    $request->headers->set('X-Request-Nonce', $this->nonces->issue($request));
    $middleware = app(VerifyRequestNonce::class);

    expect($middleware->handle($request, fn () => 'ok'))->toBe('ok');
    expect(fn () => $middleware->handle($request, fn () => 'ok'))
        ->toThrow(fn (HttpException $e) => expect($e->getStatusCode())->toBe(403));
});

it('rejects a request without a nonce', function () {
    expect(fn () => app(VerifyRequestNonce::class)->handle(nonceRequest(), fn () => 'ok'))
        ->toThrow(fn (HttpException $e) => expect($e->getStatusCode())->toBe(403));
});

it('passes requests through while disabled', function () {
    config(['cdn-headers.nonce.enabled' => false]);

    expect(app(VerifyRequestNonce::class)->handle(nonceRequest(), fn () => 'ok'))->toBe('ok');
});

it('registers the cdn-headers.nonce middleware alias', function () {
    expect(app('router')->getMiddleware())->toHaveKey('cdn-headers.nonce', VerifyRequestNonce::class);
});
