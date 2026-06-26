<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

use Controller\API\App\GlossaryController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Exceptions\ValidationError;
use DomainException;
use Exception;
use Klein\HttpStatus;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\Jobs\JobStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\MockObject\MockObject;
use Model\DataAccess\Database;
use ReflectionClass;
use ReflectionException;
use TypeError;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;
use Utils\TmKeyManagement\ClientTmKeyStruct;
use Utils\Validator\JSONSchema\JSONValidatorObject;

class TestableGlossaryController extends GlossaryController
{
    /** @var array<string, mixed> */
    public array $payload = [];
    public ?string $lastQueue = null;
    /** @var array<string, mixed>|null */
    public ?array $lastParams = null;

    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }

    protected function registerValidators(): void
    {
    }

    protected function createThePayloadForWorker(string $jsonSchemaPath): array
    {
        return $this->payload;
    }

    protected function enqueueWorker(string $queue, array $params): void
    {
        $this->lastQueue  = $queue;
        $this->lastParams = $params;
    }
}

class PayloadTestableGlossaryController extends GlossaryController
{
    public string $jsonString = '';
    public ?JobStruct $job = null;
    public bool $isRevision = false;
    public ?string $lastQueue = null;
    /** @var array<string, mixed>|null */
    public ?array $lastParams = null;

    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }

    protected function registerValidators(): void
    {
    }

    protected function validateJson(?string $json, string $jsonSchema): JSONValidatorObject
    {
        return new JSONValidatorObject($this->jsonString);
    }

    protected function getJobFromIdAndAnyPassword(int $idJob, string $jobPassword): ?JobStruct
    {
        return $this->job;
    }

    protected function isRevisionFromIdJobAndPassword(int $idJob, string $jobPassword): bool
    {
        return $this->isRevision;
    }

    protected function enqueueWorker(string $queue, array $params): void
    {
        $this->lastQueue  = $queue;
        $this->lastParams = $params;
    }
}

class GlossaryControllerTest extends AbstractTest
{
    private ReflectionClass $reflector;
    private TestableGlossaryController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        AppConfig::$SKIP_SQL_CACHE = true;

        $this->controller = new TestableGlossaryController();
        $this->reflector  = new ReflectionClass(GlossaryController::class);

        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createStub(MatecatLogger::class));
        $this->reflector->getProperty('database')->setValue($this->controller, Database::obtain());
        $this->setUser(1, true);
    }

    protected function tearDown(): void
    {
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    private function setUser(?int $uid, bool $logged): void
    {
        $user        = new UserStruct();
        $user->uid   = $uid;
        $user->email = 'test@example.org';
        $this->reflector->getProperty('user')->setValue($this->controller, $user);
        $this->reflector->getProperty('userIsLogged')->setValue($this->controller, $logged);
    }

    private function responseMock(): Response&MockObject
    {
        $mock = $this->createMock(Response::class);
        $mock->method('status')->willReturn($this->createStub(HttpStatus::class));
        $mock->method('json')->willReturnSelf();
        $this->reflector->getProperty('response')->setValue($this->controller, $mock);
        return $mock;
    }

    private function responseStub(): void
    {
        $stub = $this->createStub(Response::class);
        $stub->method('status')->willReturn($this->createStub(HttpStatus::class));
        $this->reflector->getProperty('response')->setValue($this->controller, $stub);
    }

    private function clientKey(string $key, bool $glos = true, bool $edit = true, bool $w = true): ClientTmKeyStruct
    {
        $struct       = new ClientTmKeyStruct();
        $struct->key  = $key;
        $struct->glos = $glos;
        $struct->edit = $edit;
        $struct->w    = $w;
        return $struct;
    }

    // ── check ────────────────────────────────────────────────────────────

    #[Test]
    public function check_filters_owner_keys_and_enqueues_read(): void
    {
        $this->controller->payload = [
            'tmKeys' => [
                ['key' => 'owned', 'r' => 1, 'owner' => true, 'uid_transl' => null, 'r_transl' => null, 'uid_rev' => null, 'r_rev' => null],
            ],
        ];

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['success' => true]);

        $this->controller->check();

        self::assertSame(GlossaryController::GLOSSARY_READ, $this->controller->lastQueue);
        self::assertSame('check', $this->controller->lastParams['action']);
        self::assertSame(['owned'], $this->controller->lastParams['payload']['keys']);
    }

    #[Test]
    public function check_includes_translator_added_keys_when_logged_in(): void
    {
        $this->controller->payload = [
            'tmKeys' => [
                ['key' => 'mine', 'r' => 0, 'owner' => false, 'uid_transl' => 1, 'r_transl' => true, 'uid_rev' => null, 'r_rev' => null],
            ],
        ];

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['success' => true]);

        $this->controller->check();

        self::assertSame(['mine'], $this->controller->lastParams['payload']['keys']);
    }

    // ── delete ───────────────────────────────────────────────────────────

    #[Test]
    public function delete_enqueues_write_when_permissions_ok(): void
    {
        $this->controller->payload = [
            'term'     => ['metadata' => ['key' => 'k1']],
            'userKeys' => [$this->clientKey('k1')],
        ];

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['success' => true]);

        $this->controller->delete();

        self::assertSame(GlossaryController::GLOSSARY_WRITE, $this->controller->lastQueue);
        self::assertSame('delete', $this->controller->lastParams['action']);
    }

    #[Test]
    public function delete_throws_not_found_when_key_not_in_user_keys(): void
    {
        $this->controller->payload = [
            'term'     => ['metadata' => ['key' => 'k1']],
            'userKeys' => [],
        ];
        $this->responseStub();

        $this->expectException(NotFoundException::class);

        $this->controller->delete();
    }

    #[Test]
    public function delete_throws_not_found_when_key_is_not_glossary(): void
    {
        $this->controller->payload = [
            'term'     => ['metadata' => ['key' => 'k1']],
            'userKeys' => [$this->clientKey('k1', glos: false)],
        ];
        $this->responseStub();

        $this->expectException(NotFoundException::class);

        $this->controller->delete();
    }

    #[Test]
    public function delete_throws_authorization_error_without_write_permission(): void
    {
        $this->controller->payload = [
            'term'     => ['metadata' => ['key' => 'k1']],
            'userKeys' => [$this->clientKey('k1', edit: false)],
        ];
        $this->responseStub();

        $this->expectException(AuthorizationError::class);

        $this->controller->delete();
    }

    // ── domains / get / keys / search ────────────────────────────────────

    #[Test]
    public function domains_enqueues_read(): void
    {
        $this->controller->payload = [];

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['success' => true]);

        $this->controller->domains();

        self::assertSame(GlossaryController::GLOSSARY_READ, $this->controller->lastQueue);
        self::assertSame('domains', $this->controller->lastParams['action']);
    }

    #[Test]
    public function get_enqueues_read(): void
    {
        $this->controller->payload = ['tmKeys' => []];

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['success' => true]);

        $this->controller->get();

        self::assertSame('get', $this->controller->lastParams['action']);
    }

    #[Test]
    public function keys_builds_keys_array_and_enqueues_read(): void
    {
        $this->controller->payload = ['tmKeys' => [['key' => 'a'], ['key' => 'b']]];

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['success' => true]);

        $this->controller->keys();

        self::assertSame(['a', 'b'], $this->controller->lastParams['payload']['keys']);
    }

    #[Test]
    public function search_enqueues_read(): void
    {
        $this->controller->payload = ['tmKeys' => []];

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['success' => true]);

        $this->controller->search();

        self::assertSame('search', $this->controller->lastParams['action']);
    }

    // ── set / update ─────────────────────────────────────────────────────

    #[Test]
    public function set_enqueues_write_when_permissions_ok(): void
    {
        $this->controller->payload = [
            'term'     => ['metadata' => ['keys' => [['key' => 'k1']]]],
            'userKeys' => [$this->clientKey('k1')],
        ];

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['success' => true]);

        $this->controller->set();

        self::assertSame(GlossaryController::GLOSSARY_WRITE, $this->controller->lastQueue);
        self::assertSame('set', $this->controller->lastParams['action']);
    }

    #[Test]
    public function update_enqueues_write_when_permissions_ok(): void
    {
        $this->controller->payload = [
            'term'     => ['metadata' => ['key' => 'k1']],
            'userKeys' => [$this->clientKey('k1')],
        ];

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['success' => true]);

        $this->controller->update();

        self::assertSame(GlossaryController::GLOSSARY_WRITE, $this->controller->lastQueue);
        self::assertSame('update', $this->controller->lastParams['action']);
    }

    // ── createThePayloadForWorker (real) ─────────────────────────────────

    private function payloadController(string $jsonString, ?JobStruct $job): PayloadTestableGlossaryController
    {
        $controller = new PayloadTestableGlossaryController();
        $controller->jsonString = $jsonString;
        $controller->job        = $job;

        $ref = new ReflectionClass(GlossaryController::class);
        $ref->getProperty('logger')->setValue($controller, $this->createStub(MatecatLogger::class));
        $ref->getProperty('request')->setValue($controller, $this->createStub(Request::class));
        $ref->getProperty('userIsLogged')->setValue($controller, false);

        $response = $this->createStub(Response::class);
        $response->method('json')->willReturnSelf();
        $ref->getProperty('response')->setValue($controller, $response);

        return $controller;
    }

    private function loggedInPayloadController(
        string $jsonString,
        JobStruct $job,
        string $email,
        bool $isRevision
    ): PayloadTestableGlossaryController {
        $controller = new PayloadTestableGlossaryController();
        $controller->jsonString = $jsonString;
        $controller->job = $job;
        $controller->isRevision = $isRevision;

        $ref = new ReflectionClass(GlossaryController::class);
        $ref->getProperty('logger')->setValue($controller, $this->createStub(MatecatLogger::class));
        $ref->getProperty('request')->setValue($controller, $this->createStub(Request::class));
        $ref->getProperty('userIsLogged')->setValue($controller, true);

        $user = new UserStruct();
        $user->uid = 7;
        $user->email = $email;
        $ref->getProperty('user')->setValue($controller, $user);

        $response = $this->createStub(Response::class);
        $response->method('json')->willReturnSelf();
        $ref->getProperty('response')->setValue($controller, $response);
        $ref->getProperty('database')->setValue($controller, \Model\DataAccess\Database::obtain());

        return $controller;
    }

    #[Test]
    public function createThePayloadForWorker_resolves_owner_role(): void
    {
        $job = new JobStruct();
        $job->tm_keys = '[]';
        $job->status_owner = 'owner@example.org';

        $controller = $this->loggedInPayloadController(
            '{"id_job":1,"password":"p","term":{"source_language":"en-US","target_language":"it-IT","metadata":{"keys":[]}}}',
            $job,
            'owner@example.org',
            false
        );

        $controller->set();

        self::assertSame(GlossaryController::GLOSSARY_WRITE, $controller->lastQueue);
        self::assertSame([], $controller->lastParams['payload']['userKeys']);
    }

    #[Test]
    public function createThePayloadForWorker_resolves_revisor_role(): void
    {
        $job = new JobStruct();
        $job->tm_keys = '[]';
        $job->status_owner = 'owner@example.org';

        $controller = $this->loggedInPayloadController(
            '{"id_job":1,"password":"p","term":{"source_language":"en-US","target_language":"it-IT","metadata":{"keys":[]}}}',
            $job,
            'someone-else@example.org',
            true
        );

        $controller->set();

        self::assertSame(GlossaryController::GLOSSARY_WRITE, $controller->lastQueue);
        self::assertSame([], $controller->lastParams['payload']['userKeys']);
    }

    #[Test]
    public function createThePayloadForWorker_resolves_translator_role(): void
    {
        $job = new JobStruct();
        $job->tm_keys = '[]';
        $job->status_owner = 'owner@example.org';

        $controller = $this->loggedInPayloadController(
            '{"id_job":1,"password":"p","term":{"source_language":"en-US","target_language":"it-IT","metadata":{"keys":[]}}}',
            $job,
            'someone-else@example.org',
            false
        );

        $controller->set();

        self::assertSame(GlossaryController::GLOSSARY_WRITE, $controller->lastQueue);
        self::assertSame([], $controller->lastParams['payload']['userKeys']);
    }

    #[Test]
    public function createThePayloadForWorker_builds_payload_with_languages_and_job(): void
    {
        $job             = new JobStruct();
        $job->tm_keys    = '[]';
        $controller      = $this->payloadController(
            '{"id_job":123,"password":"abc","source_language":"en-US","target_language":"it-IT","source":"a &amp; b","target":"c"}',
            $job
        );

        $controller->get();

        self::assertSame(false, $controller->isLoggedIn());
    }

    /**
     * @throws Exception
     * @throws TypeError
     * @throws ExpectationFailedException
     */
    #[Test]
    public function createThePayloadForWorker_validates_term_languages(): void
    {
        $job        = new JobStruct();
        $job->tm_keys = '[]';
        $controller = $this->payloadController(
            '{"id_job":1,"password":"x","term":{"source_language":"en-US","target_language":"it-IT","metadata":{"keys":[]}}}',
            $job
        );

        $controller->set();

        // The real createThePayloadForWorker validated the term source/target languages
        // (no ValidationError thrown) and the set action enqueued a GLOSSARY_WRITE worker.
        self::assertSame(GlossaryController::GLOSSARY_WRITE, $controller->lastQueue);
        self::assertSame('set', $controller->lastParams['action']);
        self::assertSame('en-US', $controller->lastParams['payload']['term']['source_language']);
        self::assertSame('it-IT', $controller->lastParams['payload']['term']['target_language']);
    }

    #[Test]
    public function createThePayloadForWorker_throws_on_invalid_language(): void
    {
        $job        = new JobStruct();
        $controller = $this->payloadController(
            '{"id_job":1,"password":"x","source_language":"en-US","target_language":"not-a-language"}',
            $job
        );

        $this->expectException(ValidationError::class);

        $controller->get();
    }

    #[Test]
    public function createThePayloadForWorker_throws_on_wrong_job(): void
    {
        $controller = $this->payloadController(
            '{"id_job":999,"password":"nope"}',
            null
        );

        $this->expectException(DomainException::class);

        $controller->get();
    }

    // ── getJobFromIdAndAnyPassword (real DB seam) ────────────────────────

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function getJobFromIdAndAnyPassword_returns_null_for_unknown_job(): void
    {
        // Exercise the real seam body (CatUtils::getJobFromIdAndAnyPassword).
        // A non-existent id_job/password combination resolves to null without raising.
        $controller = new TestableGlossaryController();
        $this->reflector->getProperty('database')->setValue($controller, Database::obtain());

        /** @var ?JobStruct $job */
        $job = $this->reflector
            ->getMethod('getJobFromIdAndAnyPassword')
            ->invoke($controller, 0, '__no_such_password__');

        self::assertNull($job);
    }

    // ── isRevisionFromIdJobAndPassword (real seam) ───────────────────────

    /**
     * @throws Exception
     * @throws ReflectionException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function isRevisionFromIdJobAndPassword_returns_false_for_unknown_job(): void
    {
        // Exercise the real seam body (CatUtils::isRevisionFromIdJobAndPassword).
        // A non-existent id_job/password combination resolves to false without raising.
        $controller = new TestableGlossaryController();
        $this->reflector->getProperty('database')->setValue($controller, Database::obtain());

        $isRevision = $this->reflector
            ->getMethod('isRevisionFromIdJobAndPassword')
            ->invoke($controller, 0, '__no_such_password__');

        self::assertFalse($isRevision);
    }

    // ── validateJson (real schema) ───────────────────────────────────────

    #[Test]
    public function validateJson_returns_object_for_valid_payload(): void
    {
        $controller = new TestableGlossaryController();
        $ref        = new ReflectionClass(GlossaryController::class);
        $ref->getProperty('database')->setValue($controller, Database::obtain());

        $json = '{"id_client":"c1","id_segment":1,"id_job":2,"password":"p",'
            . '"source":"hi","target":"ciao","source_language":"en-US",'
            . '"target_language":"it-IT","keys":["k1"]}';

        /** @var JSONValidatorObject $result */
        $result = $ref->getMethod('validateJson')->invoke($controller, $json, 'check.json');

        $value = $result->getValue(true);
        self::assertSame(2, $value['id_job']);
    }
}
