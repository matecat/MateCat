<?php

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\V3\TmKeyManagementController;
use Klein\HttpStatus;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Error;
use Utils\Logger\MatecatLogger;
use Utils\TmKeyManagement\TmKeyStruct;

/**
 * Real-DB suite for API/V3/TmKeyManagementController.
 *
 * Reserved ID block (Playbook §4): base 9054000 (task N=54).
 *   base+1 project (9054001), base+2 job (9054002), base+3 segment (9054003),
 *   base+4 file (9054004), base+5 team (9054005), base+6 user/uid (9054006).
 * Per-suite owner email: ctrltest_9054000@example.org.
 * Clean ONLY by reserved id; never by shared keys.
 */
class TestableTmKeyManagementV3Controller extends TmKeyManagementController
{
    public function __construct()
    {
    }

    protected function registerValidators(): void
    {
    }

    protected function initDependencies(): void
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class TmKeyManagementV3ControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9054000;

    /** @var ReflectionClass<TmKeyManagementController> */
    private ReflectionClass $reflector;
    private TestableTmKeyManagementV3Controller $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws \Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedData();

        $this->controller = new TestableTmKeyManagementV3Controller();
        $this->reflector = new ReflectionClass(TmKeyManagementController::class);

        $this->requestStub = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);
        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));

        $user = new UserStruct();
        $user->uid = $this->userId(self::BASE);
        $user->email = $this->ownerEmail(self::BASE);
        $user->first_name = 'Ctrl';
        $user->last_name = 'Tester';
        $this->setProp('user', $user);
    }

    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    private function seedData(): void
    {
        $owner = $this->ownerEmail(self::BASE);
        $this->seedUser(self::BASE, $owner);
    }

    /**
     * @throws ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $prop = $this->reflector->getProperty($name);
        $prop->setValue($this->controller, $value);
    }

    // ─── getByUser (public action) ───
    //
    // getByUser() terminates BOTH its success branch (line 35) and its catch
    // branch (line 43) with a bare exit(). exit() is not catchable and would
    // kill the PHPUnit worker. To exercise the action we make the mocked
    // Response::json() capture the payload and then throw, so control never
    // reaches exit(); we assert on the captured payload afterwards.

    /**
     * @throws \Throwable
     */
    #[Test]
    public function getByUser_returns_seeded_key_value_in_tm_keys(): void
    {
        $keyValue = 'ctrlkey9054000aaa';
        $this->seedJobKey(self::BASE, $keyValue);

        $captured = null;
        $this->responseMock->method('json')
            ->willReturnCallback(function (array $data) use (&$captured): never {
                $captured = $data;
                throw new Error('stop-before-exit');
            });

        try {
            $this->controller->getByUser();
            $this->fail('expected json() callback to interrupt before exit()');
        } catch (Error $e) {
            $this->assertSame('stop-before-exit', $e->getMessage());
        }

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('tm_keys', $captured);
        $this->assertCount(1, $captured['tm_keys']);
        $this->assertInstanceOf(TmKeyStruct::class, $captured['tm_keys'][0]);
        $this->assertSame($keyValue, $captured['tm_keys'][0]->key);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function getByUser_returns_empty_tm_keys_when_user_has_no_keys(): void
    {
        $captured = null;
        $this->responseMock->method('json')
            ->willReturnCallback(function (array $data) use (&$captured): never {
                $captured = $data;
                throw new Error('stop-before-exit');
            });

        try {
            $this->controller->getByUser();
            $this->fail('expected json() callback to interrupt before exit()');
        } catch (Error $e) {
            $this->assertSame('stop-before-exit', $e->getMessage());
        }

        $this->assertIsArray($captured);
        $this->assertSame(['tm_keys' => []], $captured);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function getByUser_returns_all_seeded_keys_for_user(): void
    {
        $this->seedJobKey(self::BASE, 'ctrlkey9054000aaa');
        // second key for the same uid (distinct key_value) — read() groups by key_value
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO memory_keys (uid, key_value, key_name, key_tm, key_glos, creation_date) "
            . "VALUES (" . $this->userId(self::BASE) . ", 'ctrlkey9054000bbb', 'SecondKey', 1, 1, NOW())"
        );

        $captured = null;
        $this->responseMock->method('json')
            ->willReturnCallback(function (array $data) use (&$captured): never {
                $captured = $data;
                throw new Error('stop-before-exit');
            });

        try {
            $this->controller->getByUser();
            $this->fail('expected json() callback to interrupt before exit()');
        } catch (Error $e) {
            $this->assertSame('stop-before-exit', $e->getMessage());
        }

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('tm_keys', $captured);
        $this->assertCount(2, $captured['tm_keys']);

        $keys = array_map(static fn (TmKeyStruct $k): string => (string) $k->key, $captured['tm_keys']);
        $this->assertContains('ctrlkey9054000aaa', $keys);
        $this->assertContains('ctrlkey9054000bbb', $keys);
    }

    /**
     * Exercises the catch branch (controller lines 36-42): the first json()
     * call (success payload) throws a caught Exception, routing the controller
     * into status()->setCode(500) + json(['errors' => ...]); the second json()
     * call (errors payload) throws an Error to escape before the trailing
     * exit(). We assert on the captured 500 error payload.
     *
     * @throws \Throwable
     */
    #[Test]
    public function getByUser_catch_branch_returns_errors_payload_with_500_status(): void
    {
        $status = $this->createMock(HttpStatus::class);
        $this->responseMock->method('status')->willReturn($status);

        $capturedError = null;
        $callCount = 0;
        $this->responseMock->method('json')
            ->willReturnCallback(function (array $data) use (&$capturedError, &$callCount): never {
                $callCount++;
                if ($callCount === 1) {
                    // success payload — simulate a downstream failure the controller catches
                    throw new \RuntimeException('boom-during-success-json');
                }
                $capturedError = $data;
                throw new Error('stop-before-exit');
            });

        try {
            $this->controller->getByUser();
            $this->fail('expected the errors json() callback to interrupt before exit()');
        } catch (Error $e) {
            $this->assertSame('stop-before-exit', $e->getMessage());
        }

        $this->assertIsArray($capturedError);
        $this->assertArrayHasKey('errors', $capturedError);
        $this->assertSame(['boom-during-success-json'], $capturedError['errors']);
    }

    // ─── registerValidators (covers the production hook body) ───

    /**
     * The Testable subclass overrides registerValidators() to a no-op, so the
     * production body is never run via the controller under test. Invoke the
     * REAL controller's registerValidators() directly to cover the
     * appendValidator(LoginValidator) statement.
     *
     * @throws \Throwable
     */
    #[Test]
    public function registerValidators_appends_login_validator(): void
    {
        $real = (new ReflectionClass(TmKeyManagementController::class))->newInstanceWithoutConstructor();

        // LoginValidator's ctor reads the controller's request via getRequest()
        $reqProp = $this->reflector->getProperty('request');
        $reqProp->setValue($real, new Request());

        $register = $this->reflector->getMethod('registerValidators');
        $register->invoke($real);

        $validatorsProp = $this->reflector->getProperty('validators');
        /** @var array<int, object> $validators */
        $validators = $validatorsProp->getValue($real);

        $this->assertCount(1, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
    }
}
