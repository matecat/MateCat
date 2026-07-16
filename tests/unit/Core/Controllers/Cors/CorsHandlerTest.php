<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers\Cors;

/**
 * Unit test for {@see \Controller\Cors\CorsHandler}.
 *
 * Pure/DI unit test: the handler takes its config as constructor primitives
 * (no AppConfig coupling), so cases construct it directly and assert against
 * the built allowlist, the framework-agnostic {@see CorsHandler::isAllowed()}
 * and {@see CorsHandler::responseHeaders()}, and the Klein
 * {@see CorsHandler::apply()} adapter using real Klein Request/Response objects
 * (no mocks). No DB or Redis is touched.
 *
 * Domain-sharding CORS model: the page is served from the app host
 * (AppConfig::$HTTPHOST, e.g. https://www.matecat.com) and its XHR/fetch calls
 * target the shard hosts ({i}.ajax.<host>) — a *different* origin. So those
 * requests carry `Origin: <app host>`, and the shard responses must allow that
 * origin. The allowed origin is therefore the app's OWN host; the shard hosts
 * are request *targets*, never the Origin. Security (CWE-942): sibling
 * subdomains (site./guides.) and other hosts must be rejected.
 */

use Controller\Cors\CorsHandler;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;

class CorsHandlerTest extends AbstractTest
{
    private const HOST = 'https://www.matecat.com';

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // ── buildAllowedOrigins (via constructor + getAllowedOrigins) ──────────

    public function testMultiDomainOffYieldsNoOrigins(): void
    {
        // Sharding off => every API call is same-origin => no CORS needed.
        $handler = new CorsHandler(self::HOST, false);
        $this->assertSame([], $handler->getAllowedOrigins());
    }

    public function testAllowsAppOwnOriginWhenMultiDomainOn(): void
    {
        $handler = new CorsHandler(self::HOST, true);
        $this->assertSame(['https://www.matecat.com'], $handler->getAllowedOrigins());
    }

    public function testDerivesSchemeAndHostFromHttpHost(): void
    {
        // http scheme honored (not hardcoded https), host parsed out of the URL.
        $handler = new CorsHandler('http://localhost', true);
        $this->assertSame(['http://localhost'], $handler->getAllowedOrigins());
    }

    public function testFallsBackToHttpsWhenSchemeMissing(): void
    {
        $handler = new CorsHandler('dev.matecat.com', true);
        $this->assertSame(['https://dev.matecat.com'], $handler->getAllowedOrigins());
    }

    public function testStripsPathFromHttpHost(): void
    {
        $handler = new CorsHandler('https://www.matecat.com/some/path', true);
        $this->assertSame(['https://www.matecat.com'], $handler->getAllowedOrigins());
    }

    // ── isAllowed (framework-agnostic security core) ───────────────────────

    public function testAllowsAppOwnOrigin(): void
    {
        // The page origin that makes the cross-origin (sharded) requests.
        $handler = new CorsHandler('https://dev.matecat.com', true);
        $this->assertTrue($handler->isAllowed('https://dev.matecat.com'));
    }

    public function testRejectsShardHost(): void
    {
        // Shard hosts are request TARGETS, never the Origin header — reject them.
        $handler = new CorsHandler(self::HOST, true);
        $this->assertFalse($handler->isAllowed('https://0.ajax.www.matecat.com'));
        $this->assertFalse($handler->isAllowed('https://99.ajax.www.matecat.com'));
    }

    public function testRejectsSiblingSubdomain(): void
    {
        // Closes CWE-942: site./guides./blog. are siblings, not the app host.
        $handler = new CorsHandler(self::HOST, true);
        $this->assertFalse($handler->isAllowed('https://site.matecat.com'));
        $this->assertFalse($handler->isAllowed('https://guides.matecat.com'));
    }

    public function testRejectsApexDomain(): void
    {
        $handler = new CorsHandler(self::HOST, true);
        $this->assertFalse($handler->isAllowed('https://matecat.com'));
    }

    public function testRejectsOtherTenantHost(): void
    {
        $handler = new CorsHandler('https://freddy.matecat-staging.com', true);
        $this->assertFalse($handler->isAllowed('https://alice.matecat-staging.com'));
    }

    public function testRejectsEmptyOrigin(): void
    {
        $handler = new CorsHandler(self::HOST, true);
        $this->assertFalse($handler->isAllowed(''));
    }

    public function testRejectsHttpVariantOfHttpsOrigin(): void
    {
        // Scheme is part of the origin; http:// is not the https:// origin.
        $handler = new CorsHandler(self::HOST, true);
        $this->assertFalse($handler->isAllowed('http://www.matecat.com'));
    }

    public function testRejectsOwnOriginWhenMultiDomainOff(): void
    {
        // No allowlist at all when sharding is off.
        $handler = new CorsHandler(self::HOST, false);
        $this->assertFalse($handler->isAllowed('https://www.matecat.com'));
    }

    // ── responseHeaders (header map emitted with native header()) ──────────

    public function testResponseHeadersForAllowedOrigin(): void
    {
        $handler = new CorsHandler(self::HOST, true);
        $origin  = 'https://www.matecat.com';

        $this->assertSame([
            'Access-Control-Allow-Origin'      => $origin,
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Methods'     => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers'     => 'Content-Type',
            'Vary'                             => 'Origin',
        ], $handler->responseHeaders($origin));
    }

    public function testResponseHeadersEmptyForShardHost(): void
    {
        $handler = new CorsHandler(self::HOST, true);
        $this->assertSame([], $handler->responseHeaders('https://0.ajax.www.matecat.com'));
    }

    public function testResponseHeadersEmptyForDisallowedOrigin(): void
    {
        $handler = new CorsHandler(self::HOST, true);
        $this->assertSame([], $handler->responseHeaders('https://site.matecat.com'));
    }

    public function testResponseHeadersEmptyForEmptyOrigin(): void
    {
        $handler = new CorsHandler(self::HOST, true);
        $this->assertSame([], $handler->responseHeaders(''));
    }

    public function testResponseHeadersReflectExactOrigin(): void
    {
        // The reflected ACAO is the request Origin verbatim, never a wildcard.
        $handler = new CorsHandler(self::HOST, true);
        $headers = $handler->responseHeaders('https://www.matecat.com');
        $this->assertSame('https://www.matecat.com', $headers['Access-Control-Allow-Origin']);
        $this->assertNotSame('*', $headers['Access-Control-Allow-Origin']);
    }

    // ── apply (Klein middleware adapter, real Request/Response) ────────────

    public function testApplyStampsHeadersForAllowedOrigin(): void
    {
        $handler  = new CorsHandler(self::HOST, true);
        $origin   = 'https://www.matecat.com';
        $request  = new Request([], [], [], ['HTTP_ORIGIN' => $origin]);
        $response = new Response();

        $result = $handler->apply($request, $response);

        $this->assertTrue($result);
        $this->assertSame($origin, $response->headers()->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->headers()->get('Access-Control-Allow-Credentials'));
        $this->assertSame('Origin', $response->headers()->get('Vary'));
    }

    public function testApplyIsNoopForShardOrigin(): void
    {
        $handler  = new CorsHandler(self::HOST, true);
        $request  = new Request([], [], [], ['HTTP_ORIGIN' => 'https://0.ajax.www.matecat.com']);
        $response = new Response();

        $this->assertFalse($handler->apply($request, $response));
        $this->assertNull($response->headers()->get('Access-Control-Allow-Origin'));
    }

    public function testApplyIsNoopWhenNoOriginHeader(): void
    {
        $handler  = new CorsHandler(self::HOST, true);
        $request  = new Request(); // no Origin header
        $response = new Response();

        $this->assertFalse($handler->apply($request, $response));
        $this->assertNull($response->headers()->get('Access-Control-Allow-Origin'));
    }
}
