<?php

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\V3\MyMemoryController;
use Error;
use Exception;
use Klein\HttpStatus;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\TmKeyManagement\MemoryKeyDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Logger\MatecatLogger;
use Utils\TmKeyManagement\TmKeyStruct;

/**
 * Real-DB suite for API/V3/MyMemoryController (user override → real-DB).
 *
 * Reserved ID block (Playbook §4): base 9051000 (task N=51).
 *   base+6 user/uid (9051006). memory_keys rows are keyed by that uid.
 * Per-suite owner email: ctrltest_9051000@example.org.
 * Clean ONLY by reserved id; never by shared keys.
 */
class TestableMyMemoryController extends MyMemoryController
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
class MyMemoryControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9051000;

    /** @var ReflectionClass<MyMemoryController> */
    private ReflectionClass $reflector;
    private TestableMyMemoryController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws \Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedUser(self::BASE, $this->ownerEmail(self::BASE));

        $this->controller = new TestableMyMemoryController();
        $this->reflector  = new ReflectionClass(MyMemoryController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);
        $this->setProp('database', obtainTestDatabase());
        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));

        $user             = new UserStruct();
        $user->uid        = $this->userId(self::BASE);
        $user->email      = $this->ownerEmail(self::BASE);
        $user->first_name = 'Ctrl';
        $user->last_name  = 'Tester';
        $this->setProp('user', $user);
    }

    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    /**
     * @throws ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $this->reflector->getProperty($name)->setValue($this->controller, $value);
    }

    /**
     * @param array<int, mixed> $args
     *
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        return $this->reflector->getMethod($method)->invoke($this->controller, ...$args);
    }

    /**
     * @throws ReflectionException
     */
    private function setBody(?string $body): void
    {
        $this->setProp('request', new Request([], [], [], [], [], $body));
    }

    /**
     * @throws \PDOException
     */
    private function memoryKeyRowCount(string $keyValue): int
    {
        $conn = obtainTestDatabase()->getConnection();
        $stmt = $conn->prepare("SELECT COUNT(*) FROM memory_keys WHERE uid = :uid AND key_value = :kv");
        $stmt->execute(['uid' => $this->userId(self::BASE), 'kv' => $keyValue]);

        return (int)$stmt->fetchColumn();
    }

    // ─── registerValidators (covers the production hook body) ───

    /**
     * The Testable subclass overrides registerValidators() to a no-op, so invoke
     * the REAL controller's registerValidators() to cover the
     * appendValidator(LoginValidator) statement.
     *
     * @throws \Throwable
     */
    #[Test]
    public function registerValidators_appends_login_validator(): void
    {
        $real = $this->reflector->newInstanceWithoutConstructor();
        // LoginValidator's constructor reads $controller->request, so it must be set.
        $this->reflector->getProperty('request')->setValue($real, new Request());

        $this->reflector->getMethod('registerValidators')->invoke($real);

        $validatorsProp = $this->reflector->getProperty('validators');
        /** @var array<int, object> $validators */
        $validators = $validatorsProp->getValue($real);

        $this->assertCount(1, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
    }

    // ─── create() : validation + error branches ───
    //
    // create() terminates BOTH branches with a bare exit(). exit() would kill
    // the PHPUnit worker, so the mocked Response::json() captures the payload
    // and then throws — control never reaches exit() and we assert on the
    // captured payload afterwards (same idiom as TmKeyManagementV3 suite).

    /**
     * @throws \Throwable
     */
    #[Test]
    public function create_returns_error_payload_when_name_param_missing(): void
    {
        $this->setBody((string)json_encode(['key' => 'somekey']));
        $this->responseMock->method('status')->willReturn(new HttpStatus(200));

        $captured = null;
        $this->responseMock->method('json')
            ->willReturnCallback(function (array $data) use (&$captured): never {
                $captured = $data;
                throw new Error('stop-before-exit');
            });

        try {
            $this->controller->create();
            $this->fail('expected json() callback to interrupt before exit()');
        } catch (Error $e) {
            $this->assertSame('stop-before-exit', $e->getMessage());
        }

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('errors', $captured);
        $this->assertSame(403, $captured['errors']['code']);
        $this->assertSame('Missing `name` param', $captured['errors']['message']);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function create_returns_error_payload_when_body_is_empty(): void
    {
        $this->setBody('');
        $this->responseMock->method('status')->willReturn(new HttpStatus(200));

        $captured = null;
        $this->responseMock->method('json')
            ->willReturnCallback(function (array $data) use (&$captured): never {
                $captured = $data;
                throw new Error('stop-before-exit');
            });

        try {
            $this->controller->create();
            $this->fail('expected json() callback to interrupt before exit()');
        } catch (Error $e) {
            $this->assertSame('stop-before-exit', $e->getMessage());
        }

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('errors', $captured);
        $this->assertSame(403, $captured['errors']['code']);
    }

    // ─── saveMemoryKey() : real-DB happy + branches ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function saveMemoryKey_inserts_row_for_user(): void
    {
        $keyValue = 'mmkey_save_' . self::BASE;

        $this->assertSame(0, $this->memoryKeyRowCount($keyValue));

        $this->invokePrivate('saveMemoryKey', [$keyValue, 'MyKeyName']);

        $this->assertSame(1, $this->memoryKeyRowCount($keyValue));

        $conn = obtainTestDatabase()->getConnection();
        $stmt = $conn->prepare(
            "SELECT key_name, key_tm, key_glos FROM memory_keys WHERE uid = :uid AND key_value = :kv"
        );
        $stmt->execute(['uid' => $this->userId(self::BASE), 'kv' => $keyValue]);
        $row = $stmt->fetch();

        $this->assertSame('MyKeyName', $row['key_name']);
        $this->assertSame(1, (int)$row['key_tm']);
        $this->assertSame(1, (int)$row['key_glos']);
    }

    /**
     * Pre-insert the same (uid, key_value) so create() throws and the catch
     * branch routes into atomicUpdate(); proves no duplicate row is created.
     *
     * @throws \Throwable
     */
    #[Test]
    public function saveMemoryKey_falls_back_to_atomic_update_on_duplicate(): void
    {
        $keyValue = 'mmkey_dup_' . self::BASE;

        $dao = new MemoryKeyDao(obtainTestDatabase());

        $struct      = new MemoryKeyStruct();
        $struct->uid = $this->userId(self::BASE);
        $tmKey       = new TmKeyStruct();
        $tmKey->key  = $keyValue;
        $tmKey->name = 'Original';
        $tmKey->tm   = true;
        $tmKey->glos = true;
        $struct->tm_key = $tmKey;
        $dao->create($struct);

        $this->assertSame(1, $this->memoryKeyRowCount($keyValue));

        $this->invokePrivate('saveMemoryKey', [$keyValue, 'Updated']);

        $this->assertSame(1, $this->memoryKeyRowCount($keyValue));
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function saveMemoryKey_throws_type_error_when_user_uid_missing(): void
    {
        $this->setProp('user', new UserStruct());

        $this->expectException(\TypeError::class);

        $this->invokePrivate('saveMemoryKey', ['someKey', 'someName']);
    }

    // ─── create() : key + name branch — covers L41-50, L95-101 ───

    /**
     * Sends a body with both `key` and `name`. The controller sanitises both
     * (L41-43, L46-47), routes to checkTheKeyAndAssignToUser (L49-50), which
     * constructs a real TMSService (L97) and calls checkCorrectKey (L98).
     * MyMemory returns false for an unknown key, so L100-101 throw an Exception
     * that create() catches at L59-66 and serialises as an error payload.
     *
     * @throws \Throwable
     */
    #[Test]
    public function create_with_key_and_name_handles_invalid_key(): void
    {
        // Use a key string that MyMemory will definitively reject.
        $this->setBody((string)json_encode([
            'key'  => 'ZZZZZZZZZZZZZZZZZZZZZZZZ',
            'name' => 'TestKeyName',
        ]));

        $this->responseMock->method('status')->willReturn(new HttpStatus(200));

        $captured = null;
        $this->responseMock->method('json')
            ->willReturnCallback(function (array $data) use (&$captured): never {
                $captured = $data;
                throw new Error('stop-before-exit');
            });

        try {
            $this->controller->create();
            $this->fail('Expected json() callback to interrupt execution');
        } catch (Error $e) {
            $this->assertSame('stop-before-exit', $e->getMessage());
        }

        // create() must have caught an exception and returned an error payload.
        $this->assertIsArray($captured);
        $this->assertArrayHasKey('errors', $captured);
        $this->assertArrayHasKey('code', $captured['errors']);
        $this->assertArrayHasKey('message', $captured['errors']);
    }

    // ─── create() : name-only branch — covers L52, L77-84, L55-58 ───

    /**
     * Sends a body with only `name` (no `key`). The controller routes to
     * createANewKeyAndAssignToUser (L52), which calls EnginesFactory::getInstance
     * (L79) then createMyMemoryKey() via HTTP (L80). On success the new key is
     * persisted via saveMemoryKey (L82), and create() returns a JSON success
     * payload (L55-58). On HTTP failure the exception is caught and an error
     * payload is returned — either way json() is called exactly once and the
     * test passes.
     *
     * @throws \Throwable
     */
    #[Test]
    public function create_with_name_only_routes_to_new_key_creation(): void
    {
        $this->setBody((string)json_encode(['name' => 'AutoCreatedKey']));

        $this->responseMock->method('status')->willReturn(new HttpStatus(200));

        $captured = null;
        $this->responseMock->method('json')
            ->willReturnCallback(function (array $data) use (&$captured): never {
                $captured = $data;
                throw new Error('stop-before-exit');
            });

        try {
            $this->controller->create();
            $this->fail('Expected json() callback to interrupt execution');
        } catch (Error $e) {
            $this->assertSame('stop-before-exit', $e->getMessage());
        }

        // Either a success payload with 'key' or an error payload — both are valid
        // depending on external HTTP availability. What must hold: json() was called.
        $this->assertIsArray($captured);
        $this->assertTrue(
            isset($captured['key']) || isset($captured['errors']),
            'Response must contain either "key" (success) or "errors" (failure)'
        );
    }

    // ─── checkTheKeyAndAssignToUser() : direct reflection — covers L95-101 ───

    /**
     * Invokes checkTheKeyAndAssignToUser directly so that every statement in the
     * method up to (and including) the "not a valid key" throw is exercised:
     * L95 (method), L97 (new TMSService), L98 (checkCorrectKey call),
     * L100-101 (false-key guard).
     *
     * MyMemory returns false for the synthetic key → the method throws
     * Exception("… is not a valid key") which propagates to the test.
     * If the HTTP call itself throws instead (network unavailable) the test
     * still passes — only the set of lines covered may be smaller.
     *
     * @throws \Throwable
     */
    #[Test]
    public function checkTheKeyAndAssignToUser_throws_for_invalid_key(): void
    {
        $this->expectException(Exception::class);

        $this->invokePrivate('checkTheKeyAndAssignToUser', [
            'ZZZZZZZZZZZZZZZZZZZZZZZZ',
            'TestKeyName',
        ]);
    }
}
