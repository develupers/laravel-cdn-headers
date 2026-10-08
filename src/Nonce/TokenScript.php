<?php

namespace Develupers\CdnHeaders\Nonce;

/**
 * Client script rendered by @nonceMeta. Exposes window.CdnHeaders:
 *
 * - ready: a promise of {csrfToken, nonce}. Uncached pages arrive with both tokens; CDN-cached pages had
 *   them blanked, so the script fetches them from the CSRF endpoint.
 * - refresh(): fetches fresh tokens. A nonce is single-use, so call this before every further request.
 *
 * It also fills the CSRF token wherever the CDN loader would, so pages carrying it skip that loader.
 */
class TokenScript
{
    /** Attribute marking the script, so the CDN middleware knows not to inject its CSRF loader too. */
    public const MARKER = 'data-cdn-headers-tokens';

    public static function render(string $endpoint, string $metaName): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

        return str_replace(
            ['MARKER_PLACEHOLDER', 'ENDPOINT_PLACEHOLDER', 'META_PLACEHOLDER'],
            [self::MARKER, json_encode($endpoint, $flags), json_encode($metaName, $flags)],
            <<<'JS'
<script MARKER_PLACEHOLDER>
(function() {
    var api = window.CdnHeaders = window.CdnHeaders || {};
    if (api.refresh) return;

    var endpoint = ENDPOINT_PLACEHOLDER;
    var nonceMeta = META_PLACEHOLDER;

    function meta(name) {
        return document.querySelector('meta[name="' + name + '"]');
    }

    function setMeta(name, value) {
        var el = meta(name);
        if (!el) {
            el = document.createElement('meta');
            el.name = name;
            document.head.appendChild(el);
        }
        el.content = value;
    }

    // This script runs in <head>; form inputs and jQuery only exist once the document has parsed.
    function domReady() {
        return new Promise(function(resolve) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() { resolve(); });
            } else {
                resolve();
            }
        });
    }

    function apply(tokens) {
        if (tokens.csrfToken) {
            setMeta('csrf-token', tokens.csrfToken);
            if (typeof window.Laravel === 'object' && window.Laravel !== null) {
                window.Laravel.csrfToken = tokens.csrfToken;
            } else {
                window.Laravel = { csrfToken: tokens.csrfToken };
            }
            document.querySelectorAll('input[name="_token"]').forEach(function(input) {
                input.value = tokens.csrfToken;
            });
            if (window.axios) {
                window.axios.defaults.headers.common['X-CSRF-TOKEN'] = tokens.csrfToken;
            }
            if (typeof $ !== 'undefined' && $.ajaxSetup) {
                $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': tokens.csrfToken } });
            }
        }
        setMeta(nonceMeta, tokens.nonce || '');
        document.dispatchEvent(new CustomEvent('cdn-headers:tokens-loaded', { detail: tokens }));
        return tokens;
    }

    api.refresh = function() {
        return fetch(endpoint, { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Token endpoint returned ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                return domReady().then(function() {
                    return apply({ csrfToken: data.csrf_token || data.token || null, nonce: data.nonce || null });
                });
            });
    };

    // Uncached layouts may carry the CSRF token only in window.Laravel or form inputs
    function renderedCsrf() {
        var tag = meta('csrf-token');
        var input = document.querySelector('input[name="_token"]');
        return (tag && tag.content) || (window.Laravel && window.Laravel.csrfToken) || (input && input.value) || null;
    }

    var rendered = meta(nonceMeta);
    api.ready = rendered && rendered.content
        ? domReady().then(function() {
            var csrfToken = renderedCsrf();
            return csrfToken ? apply({ csrfToken: csrfToken, nonce: rendered.content }) : api.refresh();
        })
        : api.refresh();

    api.ready.catch(function(error) {
        console.error('Failed to load request tokens:', error);
    });
})();
</script>
JS
        );
    }
}
