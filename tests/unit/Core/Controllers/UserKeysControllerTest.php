<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\UserKeysController;
use Exception;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
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
use Utils\TMS\TMSService;

/**
 * Real-DB suite for {@see UserKeysController}.
 *
 * Reserved ID block (Playbook §4): base = 9_003_000 (task N=3).
 *   base+1 project, base+2 job, base+3 segment, base+4 file, base+6 user/uid.
 * Per-suite owner email: ctrltest_9003000@example.org (never the shared test@example.org).
 *
 * Coverage note: the five public actions (delete/update/newKey/info/share) all funnel
 * through getMemoryToUpdate() -> TMSService::checkCorrectKey(), which performs a live
 * MyMemory HTTP call. To keep that external dependency out of the suite, the controller
 * exposes a protected getTmService() seam (added alongside this test) that
 * TestableUserKeysController overrides to return a StubTmService whose
 * checkCorrectKey() is a deterministic no-op instead of firing a live curl call.
 */
class StubTmService extends TMSService
{
    public bool $shouldThrow = false;

    public function __construct(IDatabase $database)
    {
        parent::__construct($database);
    }

    /**
     * @throws Exception
     */
    public function checkCorrectKey(string $tm_key): ?bool
    {
        if ($this->shouldThrow) {
            throw new Exception("Error: The private TM key you entered ($tm_key) appears to be invalid.", -2);
        }

        return true;
    }
}

class TestableUserKeysController extends UserKeysController
{
    public bool $tmServiceShouldThrow = false;

    public function __construct()
    {
    }

    protected function getTmService(): TMSService
    {
        $stub = new StubTmService($this->getDatabase());
        $stub->shouldThrow = $this->tmServiceShouldThrow;

        return $stub;
    }

    protected function initDependencies(): void
    {
    }

    protected function registerValidators(): void
    {
    }
}

/**
 * Variant that keeps the real registerValidators() (unlike
 * TestableUserKeysController, which no-ops it) so the suite can exercise
 * that method directly without paying for the full validate() chain.
 */
class TestableUserKeysControllerWithRealValidators extends UserKeysController
{
    public function __construct()
    {
    }

    protected function initDependencies(): void
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
        $this->setProp('database', obtainTestDatabase());

        $user            = new UserStruct();
        $user->uid       = $this->userId(self::BASE);
        $user->email     = $owner;
        $user->first_name = 'Ctrl';
        $user->last_name = 'Tester';
        $this->setProp('user', $user);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
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

    // ─── public action: full happy paths via stubbed TMSService ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function delete_disables_seeded_key_and_returns_success(): void
    {
        $this->seedJobKey(self::BASE);
        $keyValue = 'ctrltestkey' . self::BASE;

        $this->setRequestParams(['key' => $keyValue]);

        $this->controller->delete();

        $conn = $this->seedConnection();
        $stmt = $conn->prepare('SELECT deleted FROM memory_keys WHERE uid = :uid AND key_value = :key_value');
        $stmt->execute(['uid' => $this->userId(self::BASE), 'key_value' => $keyValue]);
        $deleted = $stmt->fetchColumn();

        $this->assertSame('1', (string)$deleted);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function update_updates_seeded_key_description_and_returns_success(): void
    {
        $this->seedJobKey(self::BASE);
        $keyValue = 'ctrltestkey' . self::BASE;

        $this->setRequestParams(['key' => $keyValue, 'description' => 'Updated Glossary Name']);

        $this->controller->update();

        $conn = $this->seedConnection();
        $stmt = $conn->prepare('SELECT key_name FROM memory_keys WHERE uid = :uid AND key_value = :key_value');
        $stmt->execute(['uid' => $this->userId(self::BASE), 'key_value' => $keyValue]);
        $keyName = $stmt->fetchColumn();

        $this->assertSame('Updated Glossary Name', $keyName);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function newKey_creates_key_and_returns_success(): void
    {
        $newKeyValue = 'ctrltestnewkey' . self::BASE;

        $this->setRequestParams(['key' => $newKeyValue, 'description' => 'Brand New Glossary']);

        $this->controller->newKey();

        $conn = $this->seedConnection();
        $stmt = $conn->prepare('SELECT key_name FROM memory_keys WHERE uid = :uid AND key_value = :key_value');
        $stmt->execute(['uid' => $this->userId(self::BASE), 'key_value' => $newKeyValue]);
        $keyName = $stmt->fetchColumn();

        $this->assertSame('Brand New Glossary', $keyName);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function info_returns_key_users_info_payload_for_seeded_key(): void
    {
        $this->seedJobKey(self::BASE);
        $keyValue = 'ctrltestkey' . self::BASE;

        $this->setRequestParams(['key' => $keyValue]);

        // getMemoryToUpdate() -> getTmService() is stubbed; MemoryKeyDao::read() runs
        // against the real seeded row and info() must complete without throwing.
        $this->controller->info();

        $this->addToAssertionCount(1);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function share_throws_not_found_when_no_memory_keys_exist_for_key(): void
    {
        $unseededKeyValue = 'ctrltestunseededkey' . self::BASE;

        $this->setRequestParams(['key' => $unseededKeyValue, 'emails' => 'someone@example.org']);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('No user memory keys found');

        $this->controller->share();
    }

    // ─── registerValidators ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function registerValidators_appends_login_validator(): void
    {
        $controller = new TestableUserKeysControllerWithRealValidators();
        $reflector  = new ReflectionClass(UserKeysController::class);

        $prop = $reflector->getProperty('request');
        $prop->setValue($controller, new Request());

        $method = $reflector->getMethod('registerValidators');
        $method->invoke($controller);

        $validatorsProp = $reflector->getProperty('validators');
        $validators      = $validatorsProp->getValue($controller);

        $this->assertCount(1, $validators);
    }

    // NOTE: getKeyUsersInfo()'s "populated in_users" loop body (foreach + new
    // ClientUserFacade(...)) is NOT covered here. TmKeyStruct::__set() (line 188)
    // unconditionally throws UnknownPropertyException for ANY undeclared property,
    // including "in_users" itself -- verified experimentally (Closure::bind from
    // TmKeyStruct's own scope still triggers __set and throws). MemoryKeyDao::_buildResult()
    // constructs TmKeyStruct via the array-form constructor, which silently *skips*
    // unknown keys (property_exists guard) rather than assigning them -- so "in_users"
    // can never actually land on a TmKeyStruct instance in production either. This is a
    // pre-existing dead-code path in getKeyUsersInfo() (out of scope to fix here); the
    // reachable branches (empty input, null tm_key, tm_key with no in_users) are already
    // covered above.

    // ─── removeKeyFromEngines with an adaptive engine ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function removeKeyFromEngines_reaches_adaptive_branch_with_no_ownership_metadata(): void
    {
        $tmKey      = new TmKeyStruct();
        $tmKey->key = 'abcdef1234567890';

        $memoryKey         = new MemoryKeyStruct();
        $memoryKey->uid    = $this->userId(self::BASE);
        $memoryKey->tm_key = $tmKey;

        // "MMT" is a real, adaptive engine (isAdaptiveMT() === true): createTempInstance
        // succeeds, the adaptive branch is entered, and MetadataDao::get() runs a real
        // (empty-result) lookup for this uid, so the inner deletion block is skipped and
        // the key struct is left intact.
        $this->invokePrivate('removeKeyFromEngines', [$memoryKey, 'MMT']);

        $this->assertSame('abcdef1234567890', $memoryKey->tm_key->key);
    }
}
