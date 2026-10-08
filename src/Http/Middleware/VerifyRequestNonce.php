<?php

namespace Develupers\CdnHeaders\Http\Middleware;

use Closure;
use Develupers\CdnHeaders\Nonce\NonceManager;
use Illuminate\Http\Request;

/**
 * Requires a single-use nonce on the request (see NonceManager). Passes everything through while the
 * nonce feature is disabled, so the middleware can be attached before it is switched on.
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
            abort(403, 'Invalid or expired request nonce.');
        }

        return $next($request);
    }
}
