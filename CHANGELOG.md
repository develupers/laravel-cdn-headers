# Changelog

All notable changes to `laravel-cdn-headers` will be documented in this file.

## 0.2.1 - 2026-10-08

### Changed
- Sessions that have used up their nonce budget now get **429 Too Many Requests** with `Retry-After` (and `retry_after` in JSON responses) instead of the 403 used for missing or invalid nonces, so clients can stop retrying and tell the visitor when to try again. `NonceManager::retryAfter()` reports the wait without counting an attempt.

## 0.2.0 - 2026-10-07

### Added
- Single-use request nonces, opt-in via the new `nonce` config block (disabled by default). Unlike a CSRF token, a nonce is spent by the first request that presents it and is bound to the session it was issued to — for endpoints that must not be replayed, such as requests that trigger paid third-party calls.
  - `@nonceMeta` Blade directive renders the nonce meta tag and a script exposing `window.CdnHeaders.ready` / `refresh()` and a `cdn-headers:tokens-loaded` event.
  - The CDN middleware blanks the nonce on cached pages, exactly like the CSRF token; the script then fetches fresh tokens from the CSRF endpoint, so those pages skip the CSRF loader.
  - `cdn-headers.nonce` middleware rejects requests without a valid, unspent nonce (403).
  - `NonceManager::issue()` for the CSRF endpoint to return a `nonce` field; per-session issuance limits (per minute, per day).

## 0.1.0 - 2026-09-09

### Added
- Status-aware caching via the new `error_responses` config block. 3xx and 4xx responses now receive a separate, shorter edge TTL with a zero browser TTL by default; 5xx responses are not cached at all. This protects the origin from bot-scan storms on missing-resource lookups while ensuring users do not get locked into stale errors via browser cache.
- Respect for an existing `Cache-Control: no-store` directive on the response. Inner middleware that explicitly forbids caching (e.g. a bot-detection middleware returning 403) is no longer silently overridden — addresses a cache-poisoning risk where the inner intent was lost.

### Changed
- `applyCdnHeaders()` now accepts separate `$edgeTtl` and `$browserTtl` arguments. Callers that pass only one value (the previous signature) continue to work via a default that mirrors edge TTL to browser TTL.
- Successful (2xx) responses behave identically to before — `max-age` and `s-maxage` are both set to the configured route duration.
- Logging output now includes the response status code and separate `edge_ttl` / `browser_ttl` values instead of a single `duration`.
