<?php

namespace Matecat\Core\Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Validators\EngineOwnershipValidator;
use DomainException;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Utils\Engines\MyMemory;

/**
 * Minimal controller stub: empty constructor, exposes getRequest()/getUser().
 */
class EngineOwnershipValidatorTestController extends KleinController
{
    public function __construct()
    {
    }
}

/**
 * Real-DB suite for EngineOwnershipValidator.
 * Reserved ID block base = 9_920_000 (engine IDs 9920001, 9920002, 9920003; uid 9920000).
 * Owner email: ctrltest_9920000@example.org
 */
class EngineOwnershipValidatorTest extends AbstractTest
{
    private const int B           = 9_920_000;
    private const int UID         = self::B;
    private const int ENGINE_ID   = self::B + 1;   // active, owned by UID
    private const int ENGINE_ID2  = self::B + 2;   // inactive, owned by UID
    private const int ENGINE_ID3  = self::B + 3;   // active, owned by a different UID
    private const string EMAIL    = 'ctrltest_9920000@example.org';

    private EngineOwnershipValidatorTestController $controller;
    private ReflectionClass $ctrlRef;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanTestData();
        $this->seedTestData();

        $this->controller = new EngineOwnershipValidatorTestController();
        $this->ctrlRef    = new ReflectionClass(KleinController::class);

        $user        = new UserStruct();
        $user->uid   = self::UID;
        $user->email = self::EMAIL;
        $this->setCtrlProp('user', $user);

        // Provide a minimal request so Base::__construct() can read it
        $this->setCtrlProp('request', new Request([], [], [], ['REQUEST_URI' => '/test', 'REQUEST_METHOD' => 'GET']));
        $this->setCtrlProp('database', \Model\DataAccess\Database::obtain());
    }

    protected function tearDown(): void
    {
        $this->cleanTestData();
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

    private function seedTestData(): void
    {
        $conn = Database::obtain()->getConnection();

        // Engine 1: active, owned by UID
        $conn->exec(
            "INSERT INTO engines (id, name, type, base_url, class_load, active, uid)
             VALUES (" . self::ENGINE_ID . ", 'TestMyMemory9920001', 'TM', 'http://localhost', 'MyMemory', 1, " . self::UID . ")"
        );

        // Engine 2: inactive, owned by UID
        $conn->exec(
            "INSERT INTO engines (id, name, type, base_url, class_load, active, uid)
             VALUES (" . self::ENGINE_ID2 . ", 'TestMyMemory9920002', 'TM', 'http://localhost', 'MyMemory', 0, " . self::UID . ")"
        );

        // Engine 3: active, owned by a different user
        $conn->exec(
            "INSERT INTO engines (id, name, type, base_url, class_load, active, uid)
             VALUES (" . self::ENGINE_ID3 . ", 'TestMyMemory9920003', 'TM', 'http://localhost', 'MyMemory', 1, " . (self::UID + 999) . ")"
        );
    }

    private function cleanTestData(): void
    {
        $conn = Database::obtain()->getConnection();
        $conn->exec("DELETE FROM engines WHERE id IN (" . self::ENGINE_ID . "," . self::ENGINE_ID2 . "," . self::ENGINE_ID3 . ")");
    }

    // ─── tests ─────────────────────────────────────────────────────────────────

    /**
     * Happy path: active engine owned by the current user validates and
     * getEngine() returns the correct AbstractEngine instance.
     */
    #[Test]
    public function validates_owned_active_engine_and_returns_engine_instance(): void
    {
        $validator = new EngineOwnershipValidator($this->controller, self::ENGINE_ID, MyMemory::class);
        $validator->validate();

        $engine = $validator->getEngine();
        $this->assertInstanceOf(MyMemory::class, $engine);
        $this->assertSame(self::ENGINE_ID, $engine->getEngineRecord()->id);
    }

    /**
     * Engine not found at all → generic Exception thrown.
     */
    #[Test]
    public function throws_exception_when_engine_not_found(): void
    {
        $validator = new EngineOwnershipValidator($this->controller, 9_999_999, MyMemory::class);

        $this->expectException(\Exception::class);

        $validator->validate();
    }

    /**
     * Engine belongs to a different user → AuthorizationError.
     */
    #[Test]
    public function throws_authorization_error_when_engine_owned_by_different_user(): void
    {
        $validator = new EngineOwnershipValidator($this->controller, self::ENGINE_ID3, MyMemory::class);

        $this->expectException(AuthorizationError::class);

        $validator->validate();
    }

    /**
     * Engine is inactive → DomainException.
     */
    #[Test]
    public function throws_domain_exception_when_engine_is_inactive(): void
    {
        $validator = new EngineOwnershipValidator($this->controller, self::ENGINE_ID2, MyMemory::class);

        $this->expectException(DomainException::class);

        $validator->validate();
    }

    /**
     * validate() calls the onFailure callback instead of re-throwing.
     */
    #[Test]
    public function validate_calls_on_failure_callback(): void
    {
        $caught    = null;
        $validator = new EngineOwnershipValidator($this->controller, self::ENGINE_ID3, MyMemory::class);
        $validator->onFailure(function (\Throwable $e) use (&$caught) {
            $caught = $e;
        });

        $validator->validate();   // must NOT throw

        $this->assertInstanceOf(AuthorizationError::class, $caught);
    }

    /**
     * validate() calls onSuccess callbacks after a successful validation.
     */
    #[Test]
    public function validate_calls_on_success_callback(): void
    {
        $called    = false;
        $validator = new EngineOwnershipValidator($this->controller, self::ENGINE_ID, MyMemory::class);
        $validator->onSuccess(function () use (&$called) {
            $called = true;
        });

        $validator->validate();

        $this->assertTrue($called);
    }

    /**
     * User has no uid (null) → AuthorizationError before any DB call.
     */
    #[Test]
    public function throws_authorization_error_when_user_uid_is_null(): void
    {
        $user        = new UserStruct();
        $user->uid   = null;
        $user->email = self::EMAIL;
        $this->setCtrlProp('user', $user);

        $validator = new EngineOwnershipValidator($this->controller, self::ENGINE_ID, MyMemory::class);

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionCode(401);

        $validator->validate();
    }
}
