<?php

namespace Matecat\Core\Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\WhitelistAccessValidator;
use DomainException;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

/**
 * Minimal controller stub – WhitelistAccessValidator only reads getRequest().
 */
class WhitelistAccessValidatorTestController extends KleinController
{
    public function __construct()
    {
    }
}

/**
 * Pure-logic suite (no DB). Reserved ID block base = 9_928_000 (unused; kept for
 * registry consistency). Owner: ctrltest_9928000@example.org
 *
 * WhitelistAccessValidator reads Utils::getRealIpAddr() which inspects $_SERVER.
 * We control the IP by setting $_SERVER['REMOTE_ADDR'] before each test and
 * restoring it in tearDown.
 */
class WhitelistAccessValidatorTest extends AbstractTest
{
    private const string OWNER = 'ctrltest_9928000@example.org';

    private WhitelistAccessValidatorTestController $controller;
    private ReflectionClass $ctrlRef;

    /** Saved $_SERVER state so we can restore it after each test. */
    private array $savedServer = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->savedServer = $_SERVER;

        $this->controller = new WhitelistAccessValidatorTestController();
        $this->ctrlRef    = new ReflectionClass(KleinController::class);

        $this->setRequest();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->savedServer;
        parent::tearDown();
    }

    // ─── helpers ───────────────────────────────────────────────────────────────

    private function setCtrlProp(string $name, mixed $value): void
    {
        $c = $this->ctrlRef;
        while ($c !== false && !$c->hasProperty($name)) {
            $c = $c->getParentClass();
        }
        $p = $c->getProperty($name);
        $p->setAccessible(true);
        $p->setValue($this->controller, $value);
    }

    private function setRequest(): void
    {
        $this->setCtrlProp(
            'request',
            new Request([], [], [], ['REQUEST_URI' => '/api/test', 'REQUEST_METHOD' => 'GET'])
        );
    }

    private function setRemoteAddr(string $ip): void
    {
        // Clear all forwarding headers so only REMOTE_ADDR is used.
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP',
                  'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'HTTP_CLIENT_IP'] as $k) {
            unset($_SERVER[$k]);
        }
        $_SERVER['REMOTE_ADDR'] = $ip;
    }

    // ─── whitelisted IP ranges ─────────────────────────────────────────────────

    public static function whitelistedIpProvider(): array
    {
        return [
            '10.40.1.x range'          => ['10.40.1.1'],
            '10.40.1.255'              => ['10.40.1.255'],
            '10.128.x.x range'         => ['10.128.0.1'],
            '10.128.255.255'           => ['10.128.255.255'],
            '10.144.x.x range'         => ['10.144.0.1'],
            '172.16.x.x (RFC-1918)'    => ['172.16.0.1'],
            '172.31.x.x'               => ['172.31.255.255'],
            '149.7.212.x range'        => ['149.7.212.1'],
            '149.7.212.255'            => ['149.7.212.255'],
            '127.0.0.1 loopback'       => ['127.0.0.1'],
            '127.0.0.255'              => ['127.0.0.255'],
            '93.43.95.129'             => ['93.43.95.129'],
            '93.43.95.134'             => ['93.43.95.134'],
        ];
    }

    #[Test]
    #[DataProvider('whitelistedIpProvider')]
    public function allows_whitelisted_ip(string $ip): void
    {
        $this->setRemoteAddr($ip);

        $validator = new WhitelistAccessValidator($this->controller);
        $validator->_validate(); // must not throw

        $this->assertTrue(true); // reached here = pass
    }

    // ─── blocked IP ranges ────────────────────────────────────────────────────

    public static function blockedIpProvider(): array
    {
        return [
            'public IP 8.8.8.8'       => ['8.8.8.8'],
            'public IP 1.1.1.1'       => ['1.1.1.1'],
            '10.40.2.1 (not 10.40.1.x)' => ['10.40.2.1'],
            '10.100.0.1'              => ['10.100.0.1'],
            '172.15.0.1 (not in range)' => ['172.15.0.1'],
            '172.32.0.1 (not in range)' => ['172.32.0.1'],
            '149.7.213.1'             => ['149.7.213.1'],
            '93.43.95.128 (below range)' => ['93.43.95.128'],
            '93.43.95.135 (above range)' => ['93.43.95.135'],
        ];
    }

    #[Test]
    #[DataProvider('blockedIpProvider')]
    public function throws_domain_exception_for_blocked_ip(string $ip): void
    {
        $this->setRemoteAddr($ip);

        $validator = new WhitelistAccessValidator($this->controller);

        $this->expectException(DomainException::class);
        $this->expectExceptionCode(403);

        $validator->_validate();
    }

    // ─── forwarding header respected ──────────────────────────────────────────

    #[Test]
    public function allows_whitelisted_ip_via_x_forwarded_for(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.128.5.5';

        $validator = new WhitelistAccessValidator($this->controller);
        $validator->_validate();

        $this->assertTrue(true);
    }

    #[Test]
    public function blocks_ip_via_x_forwarded_for(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '8.8.8.8';

        $validator = new WhitelistAccessValidator($this->controller);

        $this->expectException(DomainException::class);
        $this->expectExceptionCode(403);

        $validator->_validate();
    }

    // ─── validate() wrapper ───────────────────────────────────────────────────

    #[Test]
    public function validate_passes_through_for_allowed_ip(): void
    {
        $this->setRemoteAddr('127.0.0.1');

        $validator = new WhitelistAccessValidator($this->controller);
        $validator->validate(); // must not throw

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_re_throws_domain_exception_for_blocked_ip(): void
    {
        $this->setRemoteAddr('8.8.8.8');

        $validator = new WhitelistAccessValidator($this->controller);

        $this->expectException(DomainException::class);
        $this->expectExceptionCode(403);

        $validator->validate();
    }

    // ─── exception message contains the IP ───────────────────────────────────

    #[Test]
    public function exception_message_contains_blocked_ip(): void
    {
        $blockedIp = '1.2.3.4';
        $this->setRemoteAddr($blockedIp);

        $validator = new WhitelistAccessValidator($this->controller);

        try {
            $validator->_validate();
            $this->fail('Expected DomainException was not thrown');
        } catch (DomainException $e) {
            $this->assertStringContainsString($blockedIp, $e->getMessage());
            $this->assertSame(403, $e->getCode());
        }
    }
}
