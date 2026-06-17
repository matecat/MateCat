<?php

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\V2\KeyCheckController;
use Controller\Services\RateLimiterService;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\ApiKeys\ApiKeyDao;
use Model\ApiKeys\ApiKeyStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;

class TestableKeyCheckController extends KeyCheckController
{
    private ?Response $rateLimitEmailResponse = null;
    private ?Response $rateLimitIpResponse = null;
    private int $rateLimitCallCount = 0;

    public function __construct()
    {
    }

    public function setRateLimitEmailResponse(?Response $response): void
    {
        $this->rateLimitEmailResponse = $response;
    }

    public function setRateLimitIpResponse(?Response $response): void
    {
        $this->rateLimitIpResponse = $response;
    }

    public function checkAndIncrementRateLimit(Response $response, string $identifier, string $route, int $maxRetries = 10, ?RateLimiterService $limiterService = null): ?Response
    {
        $this->rateLimitCallCount++;
        if ($this->rateLimitCallCount % 2 === 1) {
            return $this->rateLimitEmailResponse;
        }

        return $this->rateLimitIpResponse;
    }

    public function resetRateLimitCallCount(): void
    {
        $this->rateLimitCallCount = 0;
    }
}

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(KeyCheckController::class)]
class KeyCheckControllerTest extends AbstractTest
{
    private TestableKeyCheckController $controller;
    /** @var Request&MockObject */
    private Request&MockObject $requestMock;
    /** @var Response&MockObject */
    private Response&MockObject $responseMock;
    /** @var ApiKeyDao&MockObject */
    private ApiKeyDao&MockObject $apiKeyDaoMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new TestableKeyCheckController();
        $this->requestMock = $this->createMock(Request::class);
        $this->responseMock = $this->createMock(Response::class);
        $this->apiKeyDaoMock = $this->createMock(ApiKeyDao::class);

        $ref = new ReflectionClass(KeyCheckController::class);
        $parentRef = $ref;
        while ($parentRef && !$parentRef->hasProperty('request')) {
            $parentRef = $parentRef->getParentClass();
        }

        $parentRef->getProperty('request')->setValue($this->controller, $this->requestMock);
        $parentRef->getProperty('response')->setValue($this->controller, $this->responseMock);

        $kcRef = new ReflectionClass(KeyCheckController::class);
        $kcRef->getProperty('apiKeyDao')->setValue($this->controller, $this->apiKeyDaoMock);

        $this->setControllerAuth(99, true);
        $this->controller->resetRateLimitCallCount();
    }

    private function setControllerAuth(int $uid, bool $withApiRecord): void
    {
        $user = new UserStruct();
        $user->uid = $uid;
        $user->email = 'test@example.com';

        $ref = new ReflectionClass($this->controller);
        while (!$ref->hasProperty('user') && $ref->getParentClass() !== false) {
            $ref = $ref->getParentClass();
        }
        $ref->getProperty('user')->setValue($this->controller, $user);
        $ref->getProperty('userIsLogged')->setValue($this->controller, true);

        if ($withApiRecord) {
            $apiRecord = new ApiKeyStruct([
                'api_key' => 'main_key', 'api_secret' => 'main_secret',
                'uid' => $uid, 'enabled' => true,
                'create_date' => '2024-01-01', 'last_update' => '2024-01-01',
            ]);
            $parentRef = new ReflectionClass($this->controller);
            while (!$parentRef->hasProperty('api_record') && $parentRef->getParentClass() !== false) {
                $parentRef = $parentRef->getParentClass();
            }
            $parentRef->getProperty('api_record')->setValue($this->controller, $apiRecord);
        }
    }

    private function setControllerNoAuth(): void
    {
        $ref = new ReflectionClass($this->controller);
        while (!$ref->hasProperty('api_record') && $ref->getParentClass() !== false) {
            $ref = $ref->getParentClass();
        }
        $ref->getProperty('api_record')->setValue($this->controller, null);

        $parentRef = new ReflectionClass($this->controller);
        while (!$parentRef->hasProperty('userIsLogged') && $parentRef->getParentClass() !== false) {
            $parentRef = $parentRef->getParentClass();
        }
        $parentRef->getProperty('userIsLogged')->setValue($this->controller, false);
    }

    // ─── ping() ──────────────────────────────────────────────────────────

    #[Test]
    public function pingReturnsEarlyOnEmailRateLimit(): void
    {
        $rateLimitResponse = $this->createMock(Response::class);
        $this->controller->setRateLimitEmailResponse($rateLimitResponse);

        $this->controller->ping();

        $ref = new ReflectionClass($this->controller);
        while (!$ref->hasProperty('response') && $ref->getParentClass() !== false) {
            $ref = $ref->getParentClass();
        }
        $actual = $ref->getProperty('response')->getValue($this->controller);
        $this->assertSame($rateLimitResponse, $actual);
    }

    #[Test]
    public function pingReturnsEarlyOnIpRateLimit(): void
    {
        $rateLimitResponse = $this->createMock(Response::class);
        $this->controller->setRateLimitIpResponse($rateLimitResponse);

        $this->controller->ping();

        $ref = new ReflectionClass($this->controller);
        while (!$ref->hasProperty('response') && $ref->getParentClass() !== false) {
            $ref = $ref->getParentClass();
        }
        $actual = $ref->getProperty('response')->getValue($this->controller);
        $this->assertSame($rateLimitResponse, $actual);
    }

    #[Test]
    public function pingReturns200WhenAuthenticated(): void
    {
        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(200);

        $this->controller->ping();
    }

    #[Test]
    public function pingThrowsAuthenticationErrorWhenNoApiRecord(): void
    {
        $this->setControllerNoAuth();

        $this->expectException(AuthenticationError::class);

        $this->controller->ping();
    }

    // ─── getUID() ────────────────────────────────────────────────────────

    #[Test]
    public function getUIDReturnsUserJsonOnValidKey(): void
    {
        $targetRecord = new ApiKeyStruct([
            'api_key' => 'target_key', 'api_secret' => 'target_secret',
            'uid' => 77, 'enabled' => true,
            'create_date' => '2024-01-01', 'last_update' => '2024-01-01',
        ]);

        $this->apiKeyDaoMock->method('findByKey')
            ->with('target_key')
            ->willReturn($targetRecord);

        $this->controller->params = ['user_api_key' => 'target_key-target_secret'];

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['user' => ['uid' => 77]]);

        $this->controller->getUID();
    }

    #[Test]
    public function getUIDThrowsNotFoundWhenKeyNotInDb(): void
    {
        $this->apiKeyDaoMock->method('findByKey')->willReturn(null);

        $this->controller->params = ['user_api_key' => 'unknown_key-unknown_secret'];

        $this->expectException(NotFoundException::class);

        $this->controller->getUID();
    }

    #[Test]
    public function getUIDThrowsNotFoundWhenSecretMismatch(): void
    {
        $targetRecord = new ApiKeyStruct([
            'api_key' => 'target_key', 'api_secret' => 'real_secret',
            'uid' => 77, 'enabled' => true,
            'create_date' => '2024-01-01', 'last_update' => '2024-01-01',
        ]);

        $this->apiKeyDaoMock->method('findByKey')
            ->with('target_key')
            ->willReturn($targetRecord);

        $this->controller->params = ['user_api_key' => 'target_key-wrong_secret'];

        $this->expectException(NotFoundException::class);

        $this->controller->getUID();
    }

    #[Test]
    public function getUIDThrowsAuthenticationErrorWhenNoApiRecord(): void
    {
        $this->setControllerNoAuth();

        $this->controller->params = ['user_api_key' => 'any-key'];

        $this->expectException(AuthenticationError::class);

        $this->controller->getUID();
    }

    #[Test]
    public function getUIDReturnsEarlyOnEmailRateLimit(): void
    {
        $rateLimitResponse = $this->createMock(Response::class);
        $this->controller->setRateLimitEmailResponse($rateLimitResponse);

        $this->controller->params = ['user_api_key' => 'any-key'];
        $this->controller->getUID();

        $ref = new ReflectionClass($this->controller);
        while (!$ref->hasProperty('response') && $ref->getParentClass() !== false) {
            $ref = $ref->getParentClass();
        }
        $actual = $ref->getProperty('response')->getValue($this->controller);
        $this->assertSame($rateLimitResponse, $actual);
    }

    #[Test]
    public function getUIDReturnsEarlyOnIpRateLimit(): void
    {
        $rateLimitResponse = $this->createMock(Response::class);
        $this->controller->setRateLimitIpResponse($rateLimitResponse);

        $this->controller->params = ['user_api_key' => 'any-key'];
        $this->controller->getUID();

        $ref = new ReflectionClass($this->controller);
        while (!$ref->hasProperty('response') && $ref->getParentClass() !== false) {
            $ref = $ref->getParentClass();
        }
        $actual = $ref->getProperty('response')->getValue($this->controller);
        $this->assertSame($rateLimitResponse, $actual);
    }

    #[Test]
    public function getUIDThrowsNotFoundWhenKeyOrSecretEmpty(): void
    {
        $this->controller->params = ['user_api_key' => '-'];

        $this->expectException(NotFoundException::class);

        $this->controller->getUID();
    }
}
