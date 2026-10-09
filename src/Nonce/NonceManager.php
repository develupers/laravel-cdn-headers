<?php

namespace Develupers\CdnHeaders\Nonce;

use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;

/**
 * Single-use request nonces, bound to the session they were issued to.
 *
 * A CSRF token is reusable for the whole session, so one harvested page can replay it against an
 * endpoint any number of times. A nonce is spent by the first request that presents it.
 */
class NonceManager
{
    public function __construct(private Repository $cache, private RateLimiter $limiter) {}

    public function enabled(): bool
    {
        return (bool) config('cdn-headers.nonce.enabled', false);
    }

    public function header(): string
    {
        return config('cdn-headers.nonce.header', 'X-Request-Nonce');
    }

    public function metaName(): string
    {
        return config('cdn-headers.nonce.meta_name', 'request-nonce');
    }

    /**
     * Issue a nonce for the request's session. Null when disabled, when there is no session, or when
     * the session is over its issuance limit.
     */
    public function issue(?Request $request = null): ?string
    {
        $session = $this->sessionHash($request ?? request());

        if (! $this->enabled() || $session === null || $this->overLimit($session)) {
            return null;
        }

        $nonce = bin2hex(random_bytes(32));

        $this->cache->put($this->key($nonce), $session, $this->ttl());

        return $nonce;
    }

    /**
     * Spend a nonce. True only the first time it is presented, by the session it was issued to, before
     * it expires.
     */
    public function consume(?string $nonce, ?Request $request = null): bool
    {
        $session = $this->sessionHash($request ?? request());

        if ($session === null || $nonce === null || ! preg_match('/^[a-f0-9]{64}$/', $nonce)) {
            return false;
        }

        $key = $this->key($nonce);
        $owner = $this->cache->get($key);

        // Rejected without being spent, so a nonce leaked to another session can't be burned by it.
        if (! is_string($owner) || ! hash_equals($owner, $session)) {
            return false;
        }

        // `add` is atomic on Redis, Memcached and database stores: of two concurrent requests
        // presenting the same nonce, exactly one claims it.
        if (! $this->cache->add($key.':spent', true, $this->ttl())) {
            return false;
        }

        $this->cache->forget($key);

        return true;
    }

    /**
     * The nonce meta tag plus the script that loads and refreshes request tokens. Empty when disabled.
     */
    public function tags(?Request $request = null): string
    {
        if (! $this->enabled()) {
            return '';
        }

        $nonce = $this->issue($request) ?? '';

        return '<meta name="'.e($this->metaName()).'" content="'.$nonce.'">'."\n"
            .TokenScript::render(config('cdn-headers.csrf_endpoint', '/users/csrf-token'), $this->metaName());
    }

    /**
     * Seconds until the request's session can be issued nonces again, or null while it is within its limits.
     * Read-only: unlike issue(), it never counts an attempt.
     */
    public function retryAfter(?Request $request = null): ?int
    {
        $session = $this->sessionHash($request ?? request());

        if ($session === null) {
            return null;
        }

        $wait = null;

        foreach ($this->limits() as $window => [$max]) {
            $key = $this->limitKey($window, $session);

            if ($max !== null && $this->limiter->tooManyAttempts($key, (int) $max)) {
                $wait = max($wait ?? 1, $this->limiter->availableIn($key));
            }
        }

        return $wait;
    }

    private function overLimit(string $session): bool
    {
        foreach ($this->limits() as $window => [$max, $decaySeconds]) {
            if ($max !== null && $this->limiter->hit($this->limitKey($window, $session), $decaySeconds) > (int) $max) {
                return true;
            }
        }

        return false;
    }

    /**
     * Per-session issuance windows: name => [max nonces (null = unlimited), window in seconds].
     *
     * @return array<string, array{0: int|string|null, 1: int}>
     */
    private function limits(): array
    {
        return [
            'minute' => [config('cdn-headers.nonce.per_session_limit.per_minute', 30), 60],
            'day' => [config('cdn-headers.nonce.per_session_limit.per_day', 500), 86400],
        ];
    }

    private function limitKey(string $window, string $session): string
    {
        return "cdn-headers:nonce-limit:{$window}:{$session}";
    }

    private function sessionHash(Request $request): ?string
    {
        return $request->hasSession() ? hash('sha256', $request->session()->getId()) : null;
    }

    private function key(string $nonce): string
    {
        return 'cdn-headers:nonce:'.hash('sha256', $nonce);
    }

    private function ttl(): int
    {
        return (int) config('cdn-headers.nonce.ttl', 1800);
    }
}
