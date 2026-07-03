<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\V2\MemoryKeysController;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Users\UserStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Utils\Logger\MatecatLogger;

class TestableMemoryKeysController extends MemoryKeysController
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

/**
 * Mock-seam suite for {@see MemoryKeysController}.
 *
 * ID block base = 9058000 (task N=58). Pattern: mock seam — listKeys() reads the user's TM keys
 * through {@see \Model\TmKeyManagement\MemoryKeyDao::read} which goes straight to PDO
 * (cacheTTL=0, no Redis read). The Database singleton is replaced with an {@see IDatabase} stub
 * whose getConnection()->prepare() returns a statement whose fetchAll() yields pre-built
 * ShapelessConcreteStruct rows; MemoryKeyDao::_buildResult then reconstructs MemoryKeyStruct
 * instances. No real-DB rows are seeded; per-suite owner identity ctrltest_9058000@example.org
 * is unused (kept for ID-registry consistency).
 */
#[AllowMockObjectsWithoutExpectations]
class MemoryKeysControllerTest extends AbstractTest
{
    private const int TEST_UID = 9058001;

    /** @var \ReflectionClass<MemoryKeysController> */
    private \ReflectionClass $reflector;
    private TestableMemoryKeysController $controller;
    private Response&MockObject $responseMock;

    /**
     * @throws \ReflectionException
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \PHPUnit\Event\NoPreviousThrowableException
     * @throws \PHPUnit\Framework\InvalidArgumentException
     * @throws \Exception
     * @throws \TypeError
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new TestableMemoryKeysController();
        $this->reflector  = new \ReflectionClass(MemoryKeysController::class);

        $this->responseMock = $this->createMock(Response::class);
        $this->setProp('response', $this->responseMock);
        $this->setProp('request', new Request());
        $this->setProp('logger', $this->createMock(MatecatLogger::class));
    }

    protected function tearDown(): void
    {
        // installDb()/setDatabaseInstance() set databaseMockApplied; parent::tearDown() restores it.
        parent::tearDown();
    }

    /**
     * @throws \ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $this->reflector->getProperty($name)->setValue($this->controller, $value);
    }

    /**
     * @throws \ReflectionException
     */
    private function setUser(?int $uid): void
    {
        $user = new UserStruct();
        if ($uid !== null) {
            $user->uid = $uid;
        }
        $this->setProp('user', $user);
    }

    /**
     * Install a DB singleton stub whose prepare()->fetchAll() returns the given key rows.
     *
     * @param list<ShapelessConcreteStruct> $rows
     *
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \PHPUnit\Event\NoPreviousThrowableException
     * @throws \PHPUnit\Framework\InvalidArgumentException
     * @throws \ReflectionException
     */
    private function installDb(array $rows): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->queryString = '';
        $stmt->method('fetchAll')->willReturn($rows);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($pdo);

        $this->setDatabaseInstance($db);
        $this->reflector->getProperty('database')->setValue($this->controller, $db);
    }

    /**
     * Build a memory_keys query row as produced by MemoryKeyDao::read's SELECT.
     *
     * @return ShapelessConcreteStruct
     */
    private function makeKeyRow(string $keyValue, string $keyName, int $ownersTot): ShapelessConcreteStruct
    {
        return new ShapelessConcreteStruct([
            'uid'        => self::TEST_UID,
            'key_value'  => $keyValue,
            'key_name'   => $keyName,
            'tm'         => 1,
            'glos'       => 1,
            'owners_tot' => $ownersTot,
            'owner_uids' => (string)self::TEST_UID,
        ]);
    }

    // ─── listKeys() happy path ───

    /**
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \ReflectionException
     * @throws \Exception
     * @throws \TypeError
     */
    #[Test]
    public function listKeys_returns_private_keys_for_unshared_key(): void
    {
        $this->setUser(self::TEST_UID);
        $this->installDb([$this->makeKeyRow('priv-key-9058', 'My Private Key', 1)]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->listKeys();

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('private_keys', $captured);
        $this->assertArrayNotHasKey('shared_keys', $captured);
        $this->assertCount(1, $captured['private_keys']);
        $this->assertSame('priv-key-9058', $captured['private_keys'][0]['key']);
        $this->assertSame('My Private Key', $captured['private_keys'][0]['name']);
    }

    /**
     * owners_tot > 1 marks the key shared -> it is bucketed under shared_keys.
     *
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \ReflectionException
     * @throws \Exception
     * @throws \TypeError
     */
    #[Test]
    public function listKeys_buckets_shared_and_private_keys_separately(): void
    {
        $this->setUser(self::TEST_UID);
        $this->installDb([
            $this->makeKeyRow('shared-key-9058', 'Team Key', 3),
            $this->makeKeyRow('priv-key-9058', 'Solo Key', 1),
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->listKeys();

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('shared_keys', $captured);
        $this->assertArrayHasKey('private_keys', $captured);
        $this->assertCount(1, $captured['shared_keys']);
        $this->assertCount(1, $captured['private_keys']);
        $this->assertSame('shared-key-9058', $captured['shared_keys'][0]['key']);
        $this->assertSame('priv-key-9058', $captured['private_keys'][0]['key']);
    }

    /**
     * No keys for the user -> empty payload, success branch still emits JSON.
     *
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \ReflectionException
     * @throws \Exception
     * @throws \TypeError
     */
    #[Test]
    public function listKeys_returns_empty_payload_when_user_has_no_keys(): void
    {
        $this->setUser(self::TEST_UID);
        $this->installDb([]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->listKeys();

        $this->assertSame([], $captured);
    }

    // ─── listKeys() failure path ───

    /**
     * Missing user uid -> the null-coalescing throw fires before any DB access.
     *
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \ReflectionException
     * @throws \Exception
     * @throws \TypeError
     */
    #[Test]
    public function listKeys_throws_when_user_not_authenticated(): void
    {
        $this->setUser(null);

        $this->responseMock->expects($this->never())->method('json');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User not authenticated');

        $this->controller->listKeys();
    }

    // ─── registerValidators() ───

    /**
     * @throws \ReflectionException
     */
    #[Test]
    public function registerValidators_appends_a_login_validator(): void
    {
        $this->reflector->getMethod('registerValidators')->invoke($this->controller);

        $validatorsProp = $this->reflector->getProperty('validators');
        /** @var array<object> $validators */
        $validators = $validatorsProp->getValue($this->controller);

        $this->assertCount(1, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
    }
}
