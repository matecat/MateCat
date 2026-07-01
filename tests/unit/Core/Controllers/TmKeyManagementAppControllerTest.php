<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\TmKeyManagementController;
use Controller\API\Commons\Validators\LoginValidator;
use Klein\HttpStatus;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Logger\MatecatLogger;
use Utils\TmKeyManagement\ClientTmKeyStruct;

/**
 * Real-DB suite for API/App/TmKeyManagementController.
 *
 * Reserved ID block (Playbook §4): base 9004000 (task N=4).
 *   base+1 project (9004001), base+2 job (9004002), base+3 segment (9004003),
 *   base+4 file (9004004), base+5 team (9004005), base+6 user/uid (9004006).
 * Per-suite owner email: ctrltest_9004000@example.org.
 * Clean ONLY by reserved id; never by shared keys.
 */
class TestableTmKeyManagementAppController extends TmKeyManagementController
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
class TmKeyManagementAppControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9004000;

    /** @var ReflectionClass<TmKeyManagementController> */
    private ReflectionClass $reflector;
    private TestableTmKeyManagementAppController $controller;
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

        $this->controller = new TestableTmKeyManagementAppController();
        $this->reflector = new ReflectionClass(TmKeyManagementController::class);

        $this->requestStub = new Request();
        $this->responseMock = $this->createMock(Response::class);
        // code() is used on the getByUserAndKey 404 branch; make it chainable
        $this->responseMock->method('code')->willReturnSelf();

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);
        $this->setProp('database', obtainTestDatabase());
        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));

        $user = new UserStruct();
        $user->uid = $this->userId(self::BASE);
        $user->email = $this->ownerEmail(self::BASE);
        $user->first_name = 'Ctrl';
        $user->last_name = 'Tester';
        $this->setProp('user', $user);
        $this->setProp('userIsLogged', true);
    }

    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    private function seedData(): void
    {
        $owner = $this->ownerEmail(self::BASE);
        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        // job owned by the suite owner; tm_keys empty JSON array
        $this->seedJob(self::BASE, $owner);
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

    /**
     * @param array<string, string> $params
     *
     * @throws ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $serverParams = ['REQUEST_URI' => '/api/app/tmkeymanagement', 'REQUEST_METHOD' => 'GET'];
        $this->requestStub = new Request($params, [], [], $serverParams);
        $this->setProp('request', $this->requestStub);
    }

    /**
     * @param array<int, mixed> $args
     *
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        $m = $this->reflector->getMethod($method);

        return $m->invoke($this->controller, ...$args);
    }

    // ─── registerValidators (protected) ───

    /**
     * The Testable subclass overrides registerValidators() as a no-op so the
     * other tests don't require an authenticated Klein request; exercise the
     * real method on a raw (non-overridden) instance instead.
     *
     * @throws \Throwable
     */
    #[Test]
    public function registerValidators_appends_login_validator(): void
    {
        $real = $this->reflector->newInstanceWithoutConstructor();

        $requestProp = $this->reflector->getProperty('request');
        $requestProp->setValue($real, $this->requestStub);

        $validatorsProp = $this->reflector->getProperty('validators');
        $validatorsProp->setValue($real, []);

        $method = $this->reflector->getMethod('registerValidators');
        $method->invoke($real);

        $this->assertCount(1, $validatorsProp->getValue($real));
        $this->assertInstanceOf(LoginValidator::class, $validatorsProp->getValue($real)[0]);
    }

    // ─── sortKeysInTheRightOrder (private) ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function sortKeysInTheRightOrder_returns_empty_when_no_job_keys(): void
    {
        $key = new ClientTmKeyStruct(['key' => 'abcdef1234567890']);

        $result = $this->invokePrivate('sortKeysInTheRightOrder', [[$key], []]);

        $this->assertSame([], $result);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function sortKeysInTheRightOrder_matches_by_exact_key(): void
    {
        $key = new ClientTmKeyStruct(['key' => 'abcdef1234567890', 'name' => 'MyKey']);
        $jobKeyList = [['key' => 'abcdef1234567890']];

        $result = $this->invokePrivate('sortKeysInTheRightOrder', [[$key], $jobKeyList]);

        $this->assertCount(1, $result);
        $this->assertSame('abcdef1234567890', $result[0]->key);
        $this->assertSame('MyKey', $result[0]->name);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function sortKeysInTheRightOrder_matches_by_last_five_chars_when_key_differs(): void
    {
        // hidden key (last 5 shared) — controller falls back to suffix match
        $key = new ClientTmKeyStruct(['key' => 'XXXXXXXXXXX67890']);
        $jobKeyList = [['key' => 'abcdef1234567890']];

        $result = $this->invokePrivate('sortKeysInTheRightOrder', [[$key], $jobKeyList]);

        $this->assertCount(1, $result);
        $this->assertSame('XXXXXXXXXXX67890', $result[0]->key);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function sortKeysInTheRightOrder_skips_job_key_with_no_matching_user_key(): void
    {
        $key = new ClientTmKeyStruct(['key' => 'aaaaaaaaaaa11111']);
        $jobKeyList = [['key' => 'bbbbbbbbbbb22222']];

        $result = $this->invokePrivate('sortKeysInTheRightOrder', [[$key], $jobKeyList]);

        $this->assertSame([], $result);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function sortKeysInTheRightOrder_skips_user_key_with_null_key_value(): void
    {
        $nullKey = new ClientTmKeyStruct(['name' => 'NoKey']);
        $jobKeyList = [['key' => 'ccccccccccc33333']];

        $result = $this->invokePrivate('sortKeysInTheRightOrder', [[$nullKey], $jobKeyList]);

        $this->assertSame([], $result);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function sortKeysInTheRightOrder_html_decodes_matched_key_name(): void
    {
        $key = new ClientTmKeyStruct(['key' => 'ddddddddddd44444', 'name' => 'Foo &amp; Bar']);
        $jobKeyList = [['key' => 'ddddddddddd44444']];

        $result = $this->invokePrivate('sortKeysInTheRightOrder', [[$key], $jobKeyList]);

        $this->assertCount(1, $result);
        $this->assertSame('Foo & Bar', $result[0]->name);
    }

    // ─── getByJob (public action) ───

    // NOTE (corrected): the not-found branch (lines 47-53) and anonymous branch
    // (lines 58-73) both return via response->json()/return; — the previous note
    // claiming they terminate with exit() was stale/incorrect for the current
    // source (verified: no exit() call exists in this method). Both are exercised
    // below with the real IDatabase.

    /**
     * @throws \Throwable
     */
    #[Test]
    public function getByJob_returns_404_when_job_not_found(): void
    {
        $this->setRequestParams([
            'id_job' => (string) ($this->jobId(self::BASE) + 500), // no such job seeded
            'password' => 'jobpw',
        ]);

        $status = new HttpStatus(200);
        $this->responseMock->method('status')->willReturn($status);
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('errors', $data);
                $this->assertSame(['The job was not found'], $data['errors']);
                return true;
            }));

        $this->controller->getByJob();

        $this->assertSame(404, $status->getCode());
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function getByJob_anonymous_user_returns_hidden_tm_keys(): void
    {
        $keyValue = 'anonvisiblekey9004000ab';
        $this->seedConnection()->exec(
            "UPDATE jobs SET tm_keys = '[{\"key\":\"$keyValue\",\"name\":\"AnonKey\"}]' WHERE id = " . $this->jobId(self::BASE)
        );

        $this->setProp('userIsLogged', false);

        $this->setRequestParams([
            'id_job' => (string) $this->jobId(self::BASE),
            'password' => 'jobpw',
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use ($keyValue): bool {
                $this->assertArrayHasKey('tm_keys', $data);
                $this->assertCount(1, $data['tm_keys']);
                $hidden = $data['tm_keys'][0];
                // hideKey(-1) masks the key; owner/r/w flags forced by the anon branch
                $this->assertNotSame($keyValue, $hidden->key);
                $this->assertTrue($hidden->r);
                $this->assertTrue($hidden->w);
                $this->assertFalse($hidden->owner);
                return true;
            }));

        $this->controller->getByJob();

        // revert so subsequent tests in this class see the default '[]' tm_keys
        $this->seedConnection()->exec(
            "UPDATE jobs SET tm_keys = '[]' WHERE id = " . $this->jobId(self::BASE)
        );
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function getByJob_owner_returns_tm_keys_payload(): void
    {
        // status_owner is a job STATUS column (default 'active'), not the job
        // owner's email — CatUtils::getJobFromIdAndAnyPassword() returns it
        // verbatim as $chunk->status_owner, and the controller compares it
        // against the logged-in user's email to detect the OWNER role. Force
        // it to the test user's email to reach that branch (controller line 77).
        $this->seedConnection()->exec(
            "UPDATE jobs SET status_owner = '" . $this->ownerEmail(self::BASE) . "' WHERE id = " . $this->jobId(self::BASE)
        );

        $this->setRequestParams([
            'id_job' => (string) $this->jobId(self::BASE),
            'password' => 'jobpw',
        ]);

        // user email == job status_owner → OWNER role; tm_keys is '[]' → empty sorted list
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('tm_keys', $data);
                $this->assertSame([], $data['tm_keys']);
                return true;
            }));

        $this->controller->getByJob();

        $this->seedConnection()->exec(
            "UPDATE jobs SET status_owner = 'active' WHERE id = " . $this->jobId(self::BASE)
        );
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function getByJob_revisor_role_returns_tm_keys_payload(): void
    {
        // logged-in user whose email != job status_owner; seed a chunk-review
        // row so isRevisionFromIdJobAndPassword() resolves the R1 password to
        // true (controller line 79, via IsJobRevisionValidator/ChunkReviewDao).
        $this->seedChunkReview(self::BASE, 'jobpw', 'revpw');

        $user = new UserStruct();
        $user->uid = $this->userId(self::BASE);
        $user->email = 'someone_else_9004000@example.org';
        $this->setProp('user', $user);

        $this->setRequestParams([
            'id_job' => (string) $this->jobId(self::BASE),
            'password' => 'revpw',
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('tm_keys', $data);
                $this->assertSame([], $data['tm_keys']);
                return true;
            }));

        $this->controller->getByJob();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function getByJob_translator_role_returns_tm_keys_payload(): void
    {
        // logged-in user whose email != job owner and not a revisor → TRANSLATOR
        $user = new UserStruct();
        $user->uid = $this->userId(self::BASE);
        $user->email = 'someone_else_9004000@example.org';
        $this->setProp('user', $user);

        $this->setRequestParams([
            'id_job' => (string) $this->jobId(self::BASE),
            'password' => 'jobpw',
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('tm_keys', $data);
                $this->assertSame([], $data['tm_keys']);
                return true;
            }));

        $this->controller->getByJob();
    }

    // ─── getByUserAndKey (public action) ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function getByUserAndKey_returns_404_when_key_absent(): void
    {
        $this->setRequestParams([
            'key' => 'nonexistent_key_for_suite_9004000',
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame([], $data);
                return true;
            }));

        $this->controller->getByUserAndKey();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function getByUserAndKey_returns_array_when_key_present(): void
    {
        $keyValue = 'ctrlkey9004000abc';
        $this->seedJobKey(self::BASE, $keyValue);

        $this->setRequestParams(['key' => $keyValue]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (mixed $data): bool {
                // _checkForAdaptiveEngines returns a list<string> of engine types;
                // with no adaptive engine licences seeded it is an empty array.
                $this->assertIsArray($data);
                return true;
            }));

        $this->controller->getByUserAndKey();
    }
}
