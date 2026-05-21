# Changelog

All notable changes to `laravel-cdn-headers` will be documented in this file.

## Unreleased

### Added
- Status-aware caching via the new `error_responses` config block. 3xx and 4xx responses now receive a separate, shorter edge TTL with a zero browser TTL by default; 5xx responses are not cached at all. This protects the origin from bot-scan storms on missing-resource lookups while ensuring users do not get locked into stale errors via browser cache.
- Respect for an existing `Cache-Control: no-store` directive on the response. Inner middleware that explicitly forbids caching (e.g. a bot-detection middleware returning 403) is no longer silently overridden — addresses a cache-poisoning risk where the inner intent was lost.

### Changed
- `applyCdnHeaders()` now accepts separate `$edgeTtl` and `$browserTtl` arguments. Callers that pass only one value (the previous signature) continue to work via a default that mirrors edge TTL to browser TTL.
- Successful (2xx) responses behave identically to before — `max-age` and `s-maxage` are both set to the configured route duration.
- Logging output now includes the response status code and separate `edge_ttl` / `browser_ttl` values instead of a single `duration`.
