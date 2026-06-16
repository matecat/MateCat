<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\UserKeysController;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\Exceptions\NotFoundException;
use Model\FeaturesBase\FeatureSet;
use Model\TmKeyManagement\MemoryKeyDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Throwable;
use Utils\Logger\MatecatLogger;
use Utils\TmKeyManagement\TmKeyStruct;

/**
 * Real-DB suite for {@see UserKeysController}.
 *
 * Reserved ID block (Playbook §4): base = 9_003_000 (task N=3).
 *   base+1 project, base+2 job, base+3 segment, base+4 file, base+6 user/uid.
 * Per-suite owner email: ctrltest_9003000@example.org (never the shared test@example.org).
 *
 * Coverage note: the five public actions (delete/update/newKey/info/share) all funnel
 * through getMemoryToUpdate() -> TMSService::checkCorrectKey(), which performs a live
 * MyMemory HTTP call. That external dependency is unit-untestable, so those actions are
 * covered only up to the validateTheRequest() boundary; the testable private/protected
 * helpers (validateTheRequest, getMkDao, getKeyUsersInfo, getMemoryToUpdate boundary,
 * removeKeyFromEngines) are exercised directly via reflection.
 */
class TestableUserKeysController extends UserKeysController
{
    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }

    protected function registerValidators(): void
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class UserKeysControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_003_000;

    /** @var ReflectionClass<UserKeysController> */
    private ReflectionClass $reflector;
    private TestableUserKeysController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $owner = $this->ownerEmail(self::BASE);
        $this->seedUser(self::BASE, $owner);

        $this->controller = new TestableUserKeysController();
        $this->reflector  = new ReflectionClass(UserKeysController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);

        $user            = new UserStruct();
        $user->uid       = $this->userId(self::BASE);
        $user->email     = $owner;
        $user->first_name = 'Ctrl';
        $user->last_name = 'Tester';
        $this->setProp('user', $user);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet());
    }

    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    /**
     * @throws Throwable
     */
    private function setProp(string $name, mixed $value): void
    {
        $prop = $this->reflector->getProperty($name);
        $prop->setValue($this->controller, $value);
    }

    /**
     * @param array<string, string> $params
     *
     * @throws Throwable
     */
    private function setRequestParams(array $params): void
    {
        $serverParams       = ['REQUEST_URI' => '/api/app/user/keys', 'REQUEST_METHOD' => 'POST'];
        $this->requestStub  = new Request($params, [], [], $serverParams);
        $this->setProp('request', $this->requestStub);
    }

    /**
     * @param array<int, mixed> $args
     * @throws Throwable
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        return $this->reflector->getMethod($method)->invoke($this->controller, ...$args);
    }

    // ─── validateTheRequest ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateTheRequest_returns_expected_shape_for_valid_input(): void
    {
        $this->setRequestParams([
            'key'         => 'abcdef1234567890',
            'emails'      => 'a@b.com',
            'description' => 'My Glossary',
            'remove_from' => 'NONE',
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertIsArray($result);
        $this->assertSame('abcdef1234567890', $result['key']);
        $this->assertSame('My Glossary', $result['description']);
        $this->assertSame('a@b.com', $result['emails']);
        $this->assertSame('NONE', $result['remove_from']);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateTheRequest_nullifies_empty_description(): void
    {
        $this->setRequestParams([
            'key' => 'abcdef1234567890',
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertNull($result['description']);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateTheRequest_throws_minus_two_when_key_missing(): void
    {
        $this->setRequestParams(['description' => 'x']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-2);

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateTheRequest_throws_minus_three_on_xss_description(): void
    {
        $this->setRequestParams([
            'key'         => 'abcdef1234567890',
            'description' => '<script>alert(1)</script>',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-3);

        $this->invokePrivate('validateTheRequest');
    }

    // ─── getMkDao ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function getMkDao_returns_memory_key_dao(): void
    {
        $dao = $this->invokePrivate('getMkDao');

        $this->assertInstanceOf(MemoryKeyDao::class, $dao);
    }

    // ─── getKeyUsersInfo ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function getKeyUsersInfo_returns_empty_success_payload_for_empty_input(): void
    {
        $result = $this->invokePrivate('getKeyUsersInfo', [[]]);

        $this->assertSame([], $result['data']);
        $this->assertSame([], $result['errors']);
        $this->assertTrue($result['success']);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function getKeyUsersInfo_returns_success_with_no_in_users(): void
    {
        // TmKeyStruct::__set rejects undeclared properties, so 'in_users' is never
        // present on a struct built here; getKeyUsersInfo therefore iterates an
        // empty user list and returns the empty-data success payload.
        $tmKey      = new TmKeyStruct();
        $tmKey->key = 'abcdef1234567890';

        $memoryKey         = new MemoryKeyStruct();
        $memoryKey->uid    = $this->userId(self::BASE);
        $memoryKey->tm_key = $tmKey;

        $result = $this->invokePrivate('getKeyUsersInfo', [[$memoryKey]]);

        $this->assertSame([], $result['data']);
        $this->assertSame([], $result['errors']);
        $this->assertTrue($result['success']);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function getKeyUsersInfo_returns_success_when_tm_key_is_null(): void
    {
        $memoryKey         = new MemoryKeyStruct();
        $memoryKey->uid    = $this->userId(self::BASE);
        $memoryKey->tm_key = null;

        $result = $this->invokePrivate('getKeyUsersInfo', [[$memoryKey]]);

        $this->assertSame([], $result['data']);
        $this->assertTrue($result['success']);
    }

    // ─── removeKeyFromEngines ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function removeKeyFromEngines_noop_for_empty_engine_list(): void
    {
        $tmKey      = new TmKeyStruct();
        $tmKey->key = 'abcdef1234567890';

        $memoryKey         = new MemoryKeyStruct();
        $memoryKey->uid    = $this->userId(self::BASE);
        $memoryKey->tm_key = $tmKey;

        // Empty CSV -> the engine loop never runs and the key is left untouched.
        $this->invokePrivate('removeKeyFromEngines', [$memoryKey, '']);

        $this->assertSame('abcdef1234567890', $memoryKey->tm_key->key);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function removeKeyFromEngines_skips_non_adaptive_engine(): void
    {
        $tmKey      = new TmKeyStruct();
        $tmKey->key = 'abcdef1234567890';

        $memoryKey         = new MemoryKeyStruct();
        $memoryKey->uid    = $this->userId(self::BASE);
        $memoryKey->tm_key = $tmKey;

        // "NONE" is a real, non-adaptive engine: createTempInstance succeeds,
        // isAdaptiveMT() returns false, so the inner deletion block is skipped
        // and the key struct is left intact.
        $this->invokePrivate('removeKeyFromEngines', [$memoryKey, 'NONE']);

        $this->assertSame('abcdef1234567890', $memoryKey->tm_key->key);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function removeKeyFromEngines_catches_unknown_engine(): void
    {
        $tmKey      = new TmKeyStruct();
        $tmKey->key = 'abcdef1234567890';

        $memoryKey         = new MemoryKeyStruct();
        $memoryKey->uid    = $this->userId(self::BASE);
        $memoryKey->tm_key = $tmKey;

        // An unknown engine name makes EnginesFactory::createTempInstance throw;
        // the controller swallows and logs it, so no exception escapes and the
        // key struct is left intact.
        $this->invokePrivate('removeKeyFromEngines', [$memoryKey, 'ThisEngineDoesNotExist']);

        $this->assertSame('abcdef1234567890', $memoryKey->tm_key->key);
    }

    // ─── public action: validation boundary (pre external call) ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function delete_throws_on_missing_key_before_external_call(): void
    {
        $this->setRequestParams([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-2);

        $this->controller->delete();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function update_throws_on_missing_key_before_external_call(): void
    {
        $this->setRequestParams([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-2);

        $this->controller->update();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function newKey_throws_on_missing_key_before_external_call(): void
    {
        $this->setRequestParams([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-2);

        $this->controller->newKey();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function info_throws_on_missing_key_before_external_call(): void
    {
        $this->setRequestParams([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-2);

        $this->controller->info();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function share_throws_on_missing_key_before_external_call(): void
    {
        $this->setRequestParams([]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-2);

        $this->controller->share();
    }
}
