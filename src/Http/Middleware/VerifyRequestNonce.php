<?php

namespace Develupers\CdnHeaders\Http\Middleware;

use Closure;
use Develupers\CdnHeaders\Nonce\NonceManager;
use Illuminate\Http\Request;

/**
 * Requires a single-use nonce on the request (see NonceManager). Passes everything through while the
 * nonce feature is disabled, so the middleware can be attached before it is switched on.
 *
 * Rejections: 429 with Retry-After when the session has used up its nonce budget (it is rate limited, and
 * retrying sooner won't help), otherwise 403 (missing, forged, replayed, expired or another session's nonce).
 */
class VerifyRequestNonce
{
    public function __construct(private NonceManager $nonces) {}

    /**
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if ($this->nonces->enabled() && ! $this->nonces->consume($request->header($this->nonces->header()), $request)) {
            if (($retryAfter = $this->nonces->retryAfter($request)) !== null) {
                $message = 'Request nonce limit reached.';
                $headers = ['Retry-After' => (string) $retryAfter];

                // retry_after in the body too: fetch wrappers often hand error handlers the body, not the headers
                if ($request->expectsJson()) {
                    return response()->json(['message' => $message, 'retry_after' => $retryAfter], 429, $headers);
                }

                abort(429, $message, $headers);
            }

            abort(403, 'Invalid or expired request nonce.');
        }

        return $next($request);
    }
}
