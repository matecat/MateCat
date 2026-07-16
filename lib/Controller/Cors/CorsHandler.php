<?php

namespace Controller\Cors;

use Klein\Request;
use Klein\Response;

/**
 * Computes credentialed CORS headers for the AJAX domain-sharding feature.
 * Config is injected, so it is correct for any install domain (www.matecat.com,
 * a per-user staging sandbox, localhost, an OSS installer's own host) with no
 * literal domain anywhere.
 *
 * Sharding topology: the page is served from the app host ($httpHost) and its
 * XHR/fetch calls target the shard hosts ({i}.ajax.<host>) — a *different*
 * origin. So those requests carry `Origin: <app host>` and the shard responses
 * must allow that origin. The single allowed origin is therefore the app's OWN
 * host; the shard hosts are request TARGETS, never the Origin.
 *
 * Replaces the over-broad Apache reflection
 * `Origin ~ https://.*(\.matecat\.com|\.matecat-staging\.com)$` (CWE-942),
 * which trusted every subdomain (siblings, other tenants).
 */
class CorsHandler
{
    /**
     * Exact set of allowed origins, built once at construction.
     *
     * @var string[]
     */
    private array $allowedOrigins;

    public function __construct(string $httpHost, bool $multiDomain)
    {
        $this->allowedOrigins = $this->buildAllowedOrigins($httpHost, $multiDomain);
    }

    /**
     * @return string[]
     */
    public function getAllowedOrigins(): array
    {
        return $this->allowedOrigins;
    }

    /**
     * Exact string match — no regex, no anchors, no escaping (the pattern class
     * that caused CWE-942).
     */
    public function isAllowed(string $origin): bool
    {
        return $origin !== '' && in_array($origin, $this->allowedOrigins, true);
    }

    /**
     * CORS response headers to emit for the given request Origin, or an empty
     * array when the Origin is not one of this instance's shard origins (a
     * same-origin request needs no Access-Control-Allow-Origin header).
     *
     * @return array<string, string> header name => value
     */
    public function responseHeaders(string $origin): array
    {
        if (!$this->isAllowed($origin)) {
            return [];
        }

        return [
            'Access-Control-Allow-Origin'      => $origin,
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Methods'     => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers'     => 'Content-Type',
            'Vary'                             => 'Origin',
        ];
    }

    /**
     * Klein middleware adapter: stamp the CORS headers on the response if the
     * request Origin is one of this instance's shard origins. Registered as the
     * first respond() handler in router.php so it runs before any controller
     * sends/locks its Response (klein >= 3.3.1 dispatches in registration order).
     */
    public function apply(Request $request, Response $response): bool
    {
        $headers = $this->responseHeaders((string)$request->headers()->get('Origin'));

        if ($headers === []) {
            return false;
        }

        foreach ($headers as $name => $value) {
            $response->header($name, $value);
        }

        return true;
    }

    /**
     * The only origin that legitimately makes the cross-origin (sharded)
     * requests is the app's own host: the page is served from $httpHost and its
     * XHR targets `//{i}.ajax.{location.host}/`
     * (public/js/utils/getMatecatApiDomain/getMatecatApiDomain.js), so the
     * request Origin is $httpHost. When sharding is off every call is
     * same-origin and needs no CORS headers at all.
     *
     * @param string $httpHost    the app's own base URL (AppConfig::$HTTPHOST), e.g. "https://www.matecat.com"
     * @param bool   $multiDomain whether AJAX domain-sharding is enabled (AppConfig::$ENABLE_MULTI_DOMAIN_API)
     *
     * @return string[] e.g. ["https://www.matecat.com"] or []
     */
    private function buildAllowedOrigins(string $httpHost, bool $multiDomain): array
    {
        if (!$multiDomain) {
            return [];
        }

        $scheme = parse_url($httpHost, PHP_URL_SCHEME) ?: 'https';
        $host   = parse_url($httpHost, PHP_URL_HOST) ?: $httpHost;

        return [sprintf('%s://%s', $scheme, $host)];
    }
}
