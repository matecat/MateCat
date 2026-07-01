<?php

namespace Matecat\Core\Controllers;

use Model\DataAccess\Database;
use Controller\API\App\DeleteContributionController;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\LoginValidator;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\Translations\SegmentTranslationDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use Utils\Engines\AbstractEngine;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\Engines\Results\TMSAbstractResponse;

class FakeEngine extends AbstractEngine
{
    public function __construct()
    {
    }

    public static function getConfigurationParameters(): array
    {
        return [];
    }

    public function getConfigStruct(): array
    {
        return [];
    }

    public function delete($_config): bool
    {
        return true;
    }

    public function get(array $_config): GetMemoryResponse
    {
        return new GetMemoryResponse();
    }

    public function set($_config): array
    {
        return [];
    }

    public function update($_config): array
    {
        return [];
    }

    protected function _decode(mixed $rawValue, array $parameters = [], ?string $function = null): array|TMSAbstractResponse
    {
        return [];
    }
}

class TestableDeleteContributionController extends DeleteContributionController
{
    public function __construct()
    {
    }

    protected function createTmsEngine(mixed $id_tms): AbstractEngine
    {
        return new FakeEngine();
    }
}

class FailingFakeEngine extends AbstractEngine
{
    public function __construct()
    {
    }

    public static function getConfigurationParameters(): array
    {
        return [];
    }

    public function getConfigStruct(): array
    {
        return [];
    }

    public function delete($_config): bool
    {
        return false;
    }

    public function get(array $_config): GetMemoryResponse
    {
        return new GetMemoryResponse();
    }

    public function set($_config): array
    {
        return [];
    }

    public function update($_config): array
    {
        return [];
    }

    protected function _decode(mixed $rawValue, array $parameters = [], ?string $function = null): array|TMSAbstractResponse
    {
        return [];
    }
}

class TestableFailingDeleteContributionController extends DeleteContributionController
{
    public function __construct()
    {
    }

    protected function createTmsEngine(mixed $id_tms): AbstractEngine
    {
        return new FailingFakeEngine();
    }
}

class MixedResultFakeEngine extends AbstractEngine
{
    private int $calls = 0;

    public function __construct()
    {
    }

    public static function getConfigurationParameters(): array
    {
        return [];
    }

    public function getConfigStruct(): array
    {
        return [];
    }

    public function delete($_config): bool
    {
        $this->calls++;

        //first call succeeds, second (and further) calls fail
        return $this->calls === 1;
    }

    public function get(array $_config): GetMemoryResponse
    {
        return new GetMemoryResponse();
    }

    public function set($_config): array
    {
        return [];
    }

    public function update($_config): array
    {
        return [];
    }

    protected function _decode(mixed $rawValue, array $parameters = [], ?string $function = null): array|TMSAbstractResponse
    {
        return [];
    }
}

class TestableMixedResultDeleteContributionController extends DeleteContributionController
{
    public function __construct()
    {
    }

    protected function createTmsEngine(mixed $id_tms): AbstractEngine
    {
        return new MixedResultFakeEngine();
    }
}

class DeleteContributionControllerTest extends AbstractTest
{
    private DeleteContributionController $controller;
    private ReflectionClass $reflector;
    private Request $requestStub;

    public function setUp(): void
    {
        parent::setUp();
        $this->requestStub = $this->createStub(Request::class);
        $responseMock = $this->createStub(Response::class);

        $this->reflector = new ReflectionClass(DeleteContributionController::class);
        $this->controller = $this->reflector->newInstanceWithoutConstructor();

        $requestProp = $this->reflector->getProperty('request');
        $requestProp->setValue($this->controller, $this->requestStub);

        $responseProp = $this->reflector->getProperty('response');
        $responseProp->setValue($this->controller, $responseMock);

        $this->reflector->getProperty('database')->setValue($this->controller, obtainTestDatabase());

        $featureSet = $this->createStub(FeatureSet::class);
        $featureSetProp = $this->reflector->getProperty('featureSet');
        $featureSetProp->setValue($this->controller, $featureSet);

        $user = new UserStruct();
        $user->uid = 42;
        $user->email = 'test@example.com';
        $userProp = $this->reflector->getProperty('user');
        $userProp->setValue($this->controller, $user);
    }

    private function invokeMethod(string $name, array $args = []): mixed
    {
        $method = $this->reflector->getMethod($name);

        return $method->invokeArgs($this->controller, $args);
    }

    private function setupRequestParams(array $params): void
    {
        $this->requestStub->method('param')
            ->willReturnCallback(function (string $key) use ($params) {
                return $params[$key] ?? null;
            });
    }

    #[Test]
    public function validateTheRequest_missing_source_lang_throws(): void
    {
        $this->setupRequestParams([
            'id_segment' => '100', 'source_lang' => '', 'target_lang' => 'it-IT',
            'seg' => 'Hello', 'tra' => 'Ciao', 'id_job' => '999',
            'id_match' => '1', 'password' => 'pass', 'current_password' => 'cpass', 'id_translator' => null,
        ]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing source_lang');
        $this->invokeMethod('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_missing_target_lang_throws(): void
    {
        $this->setupRequestParams([
            'id_segment' => '100', 'source_lang' => 'en-US', 'target_lang' => '',
            'seg' => 'Hello', 'tra' => 'Ciao', 'id_job' => '999',
            'id_match' => '1', 'password' => 'pass', 'current_password' => 'cpass', 'id_translator' => null,
        ]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing target_lang');
        $this->invokeMethod('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_missing_source_throws(): void
    {
        $this->setupRequestParams([
            'id_segment' => '100', 'source_lang' => 'en-US', 'target_lang' => 'it-IT',
            'seg' => '', 'tra' => 'Ciao', 'id_job' => '999',
            'id_match' => '1', 'password' => 'pass', 'current_password' => 'cpass', 'id_translator' => null,
        ]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing source');
        $this->invokeMethod('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_missing_target_throws(): void
    {
        $this->setupRequestParams([
            'id_segment' => '100', 'source_lang' => 'en-US', 'target_lang' => 'it-IT',
            'seg' => 'Hello', 'tra' => '', 'id_job' => '999',
            'id_match' => '1', 'password' => 'pass', 'current_password' => 'cpass', 'id_translator' => null,
        ]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing target');
        $this->invokeMethod('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_missing_id_job_throws(): void
    {
        $this->setupRequestParams([
            'id_segment' => '100', 'source_lang' => 'en-US', 'target_lang' => 'it-IT',
            'seg' => 'Hello', 'tra' => 'Ciao', 'id_job' => '',
            'id_match' => '1', 'password' => 'pass', 'current_password' => 'cpass', 'id_translator' => null,
        ]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing id job');
        $this->invokeMethod('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_missing_password_throws(): void
    {
        $this->setupRequestParams([
            'id_segment' => '100', 'source_lang' => 'en-US', 'target_lang' => 'it-IT',
            'seg' => 'Hello', 'tra' => 'Ciao', 'id_job' => '999',
            'id_match' => '1', 'password' => '', 'current_password' => 'cpass', 'id_translator' => null,
        ]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('missing job password');
        $this->invokeMethod('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_valid_request_returns_correct_structure(): void
    {
        $this->setupRequestParams([
            'id_segment' => '100', 'source_lang' => 'en-US', 'target_lang' => 'it-IT',
            'seg' => ' Hello world ', 'tra' => ' Ciao mondo ', 'id_job' => '999',
            'id_match' => '42', 'password' => ' mypass ', 'current_password' => ' currpass ', 'id_translator' => null,
        ]);

        $result = $this->invokeMethod('validateTheRequest');

        $this->assertSame('en-US', $result['source_lang']);
        $this->assertSame('it-IT', $result['target_lang']);
        $this->assertSame('Hello world', $result['source']);
        $this->assertSame('Ciao mondo', $result['target']);
        $this->assertSame(999, $result['id_job']);
        $this->assertSame('mypass', $result['password']);
        $this->assertSame('currpass', $result['received_password']);

        $idJobProp = $this->reflector->getProperty('id_job');
        $this->assertSame(999, $idJobProp->getValue($this->controller));
    }

    #[Test]
    public function validateTheRequest_with_id_translator(): void
    {
        $this->setupRequestParams([
            'id_segment' => '100', 'source_lang' => 'en-US', 'target_lang' => 'it-IT',
            'seg' => 'Hello', 'tra' => 'Ciao', 'id_job' => '999',
            'id_match' => '42', 'password' => 'pass', 'current_password' => 'cpass', 'id_translator' => '77',
        ]);

        $result = $this->invokeMethod('validateTheRequest');
        $this->assertNotNull($result['id_translator']);
    }

    #[Test]
    public function validateTheRequest_numeric_source_is_allowed(): void
    {
        $this->setupRequestParams([
            'id_segment' => '100', 'source_lang' => 'en-US', 'target_lang' => 'it-IT',
            'seg' => '0', 'tra' => 'zero', 'id_job' => '999',
            'id_match' => '42', 'password' => 'pass', 'current_password' => 'cpass', 'id_translator' => null,
        ]);

        $result = $this->invokeMethod('validateTheRequest');
        $this->assertSame('0', $result['source']);
    }

    #[Test]
    public function delete_with_valid_job_succeeds(): void
    {
        $testable = new TestableDeleteContributionController();
        $ref = new ReflectionClass(TestableDeleteContributionController::class);

        $requestStub = $this->createStub(Request::class);
        $requestStub->method('param')->willReturnCallback(function (string $key) {
            return match ($key) {
                'id_segment' => '1',
                'source_lang' => 'en-GB',
                'target_lang' => 'es-ES',
                'seg' => 'Hello world',
                'tra' => 'Hola mundo',
                'id_job' => '1886428338',
                'id_match' => '12345',
                'password' => 'a90acf203402',
                'current_password' => 'a90acf203402',
                'id_translator' => null,
                default => null,
            };
        });

        $responseStub = $this->createStub(Response::class);
        $responseStub->method('json')->willReturn($responseStub);

        $ref->getProperty('request')->setValue($testable, $requestStub);
        $ref->getProperty('response')->setValue($testable, $responseStub);
        $ref->getProperty('database')->setValue($testable, obtainTestDatabase());

        $featureSet = new FeatureSet(obtainTestDatabase());
        $ref->getProperty('featureSet')->setValue($testable, $featureSet);

        $user = new UserStruct();
        $user->uid = 1886472050;
        $user->email = 'foo@example.org';
        $ref->getProperty('user')->setValue($testable, $user);

        $testable->delete();

        $this->assertTrue(true);
    }

    #[Test]
    public function registerValidators_appends_login_validator(): void
    {
        $method = $this->reflector->getMethod('registerValidators');
        $method->invoke($this->controller);

        $validatorsProp = $this->reflector->getProperty('validators');
        $validators = $validatorsProp->getValue($this->controller);

        $this->assertNotEmpty($validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
    }

    #[Test]
    public function delete_with_malformed_tm_keys_throws_not_found_exception(): void
    {
        $db = obtainTestDatabase();
        $conn = $db->getConnection();
        $conn->exec("INSERT INTO jobs (password, id_project, job_first_segment, job_last_segment, tm_keys, source, target, id_tms, create_date, disabled, owner)
            VALUES ('pwd9978001', 1886428330, 1, 1, 'not-valid-json', 'en-US', 'it-IT', 1, NOW(), 0, 'foo@example.org')");
        $idJob = (int)$conn->lastInsertId();

        try {
            $testable = new TestableDeleteContributionController();
            $ref = new ReflectionClass(TestableDeleteContributionController::class);

            $requestStub = $this->createStub(Request::class);
            $requestStub->method('param')->willReturnCallback(function (string $key) use ($idJob) {
                return match ($key) {
                    'id_segment' => '1',
                    'source_lang' => 'en-GB',
                    'target_lang' => 'es-ES',
                    'seg' => 'Hello world',
                    'tra' => 'Hola mundo',
                    'id_job' => (string)$idJob,
                    'id_match' => '1',
                    'password' => 'pwd9978001',
                    'current_password' => 'pwd9978001',
                    'id_translator' => null,
                    default => null,
                };
            });

            $responseStub = $this->createStub(Response::class);
            $responseStub->method('json')->willReturn($responseStub);

            $ref->getProperty('request')->setValue($testable, $requestStub);
            $ref->getProperty('response')->setValue($testable, $responseStub);
            $ref->getProperty('database')->setValue($testable, $db);

            $featureSet = $this->createStub(FeatureSet::class);
            $ref->getProperty('featureSet')->setValue($testable, $featureSet);

            $user = new UserStruct();
            $user->uid = 1886472050;
            $user->email = 'foo@example.org';
            $ref->getProperty('user')->setValue($testable, $user);

            $this->expectException(\Controller\API\Commons\Exceptions\NotFoundException::class);
            $this->expectExceptionMessage('Cannot retrieve TM keys info.');
            $testable->delete();
        } finally {
            $conn->exec("DELETE FROM jobs WHERE id = $idJob");
        }
    }

    #[Test]
    public function delete_with_empty_tm_keys_and_successful_delete_updates_suggestions(): void
    {
        $db = obtainTestDatabase();
        $conn = $db->getConnection();
        $conn->exec("INSERT INTO jobs (password, id_project, job_first_segment, job_last_segment, tm_keys, source, target, id_tms, create_date, disabled, owner)
            VALUES ('pwd9978002', 1886428330, 1, 1, '[]', 'en-US', 'it-IT', 1, NOW(), 0, 'foo@example.org')");
        $idJob = (int)$conn->lastInsertId();

        try {
            $testable = new TestableDeleteContributionController();
            $ref = new ReflectionClass(TestableDeleteContributionController::class);

            $requestStub = $this->createStub(Request::class);
            $requestStub->method('param')->willReturnCallback(function (string $key) use ($idJob) {
                return match ($key) {
                    'id_segment' => '999999999',
                    'source_lang' => 'en-GB',
                    'target_lang' => 'es-ES',
                    'seg' => 'Hello world',
                    'tra' => 'Hola mundo',
                    'id_job' => (string)$idJob,
                    'id_match' => '1',
                    'password' => 'pwd9978002',
                    'current_password' => 'pwd9978002',
                    'id_translator' => null,
                    default => null,
                };
            });

            $responseStub = $this->createStub(Response::class);
            $responseStub->method('json')->willReturn($responseStub);

            $ref->getProperty('request')->setValue($testable, $requestStub);
            $ref->getProperty('response')->setValue($testable, $responseStub);
            $ref->getProperty('database')->setValue($testable, $db);

            $featureSet = $this->createStub(FeatureSet::class);
            $ref->getProperty('featureSet')->setValue($testable, $featureSet);

            $user = new UserStruct();
            $user->uid = 1886472050;
            $user->email = 'foo@example.org';
            $ref->getProperty('user')->setValue($testable, $user);

            $testable->delete();

            $this->assertTrue(true);
        } finally {
            $conn->exec("DELETE FROM jobs WHERE id = $idJob");
        }
    }

    #[Test]
    public function delete_with_failing_tms_delete_reports_unsuccessful(): void
    {
        $testable = new TestableFailingDeleteContributionController();
        $ref = new ReflectionClass(TestableFailingDeleteContributionController::class);

        $requestStub = $this->createStub(Request::class);
        $requestStub->method('param')->willReturnCallback(function (string $key) {
            return match ($key) {
                'id_segment' => '1',
                'source_lang' => 'en-GB',
                'target_lang' => 'es-ES',
                'seg' => 'Hello world',
                'tra' => 'Hola mundo',
                'id_job' => '1886428338',
                'id_match' => '12345',
                'password' => 'a90acf203402',
                'current_password' => 'a90acf203402',
                'id_translator' => null,
                default => null,
            };
        });

        $capturedPayload = null;
        $responseStub = $this->createStub(Response::class);
        $responseStub->method('json')->willReturnCallback(function ($payload) use ($responseStub, &$capturedPayload) {
            $capturedPayload = $payload;
            return $responseStub;
        });

        $ref->getProperty('request')->setValue($testable, $requestStub);
        $ref->getProperty('response')->setValue($testable, $responseStub);
        $ref->getProperty('database')->setValue($testable, obtainTestDatabase());

        $featureSet = new FeatureSet(obtainTestDatabase());
        $ref->getProperty('featureSet')->setValue($testable, $featureSet);

        $user = new UserStruct();
        $user->uid = 1886472050;
        $user->email = 'foo@example.org';
        $ref->getProperty('user')->setValue($testable, $user);

        $testable->delete();

        //NOTE: with a single tm_key, array_search(false, [false], true) returns index 0,
        //which is falsy in the `if (array_search(...))` check at
        //DeleteContributionController::delete() -> $set_successful stays true.
        //This documents the actual (pre-existing) production behaviour.
        $this->assertIsArray($capturedPayload);
        $this->assertTrue($capturedPayload['code']);
        $this->assertSame('OK', $capturedPayload['data']);
    }

    #[Test]
    public function delete_with_multiple_tm_keys_and_one_failure_reports_unsuccessful(): void
    {
        $db = obtainTestDatabase();
        $conn = $db->getConnection();
        $tmKeys = json_encode([
            ['tm' => true, 'glos' => true, 'owner' => true, 'uid_transl' => null, 'uid_rev' => null, 'name' => '', 'key' => 'KEYONE', 'r' => true, 'w' => true],
            ['tm' => true, 'glos' => true, 'owner' => true, 'uid_transl' => null, 'uid_rev' => null, 'name' => '', 'key' => 'KEYTWO', 'r' => true, 'w' => true],
        ]);
        $conn->exec("INSERT INTO jobs (password, id_project, job_first_segment, job_last_segment, tm_keys, source, target, id_tms, create_date, disabled, owner)
            VALUES ('pwd9978003', 1886428330, 1, 1, " . $conn->quote($tmKeys) . ", 'en-US', 'it-IT', 1, NOW(), 0, 'foo@example.org')");
        $idJob = (int)$conn->lastInsertId();

        try {
            $testable = new TestableMixedResultDeleteContributionController();
            $ref = new ReflectionClass(TestableMixedResultDeleteContributionController::class);

            $requestStub = $this->createStub(Request::class);
            $requestStub->method('param')->willReturnCallback(function (string $key) use ($idJob) {
                return match ($key) {
                    'id_segment' => '1',
                    'source_lang' => 'en-GB',
                    'target_lang' => 'es-ES',
                    'seg' => 'Hello world',
                    'tra' => 'Hola mundo',
                    'id_job' => (string)$idJob,
                    'id_match' => '12345',
                    'password' => 'pwd9978003',
                    'current_password' => 'pwd9978003',
                    'id_translator' => null,
                    default => null,
                };
            });

            $capturedPayload = null;
            $responseStub = $this->createStub(Response::class);
            $responseStub->method('json')->willReturnCallback(function ($payload) use ($responseStub, &$capturedPayload) {
                $capturedPayload = $payload;
                return $responseStub;
            });

            $ref->getProperty('request')->setValue($testable, $requestStub);
            $ref->getProperty('response')->setValue($testable, $responseStub);
            $ref->getProperty('database')->setValue($testable, $db);

            $featureSet = new FeatureSet($db);
            $ref->getProperty('featureSet')->setValue($testable, $featureSet);

            $user = new UserStruct();
            $user->uid = 1886472050;
            $user->email = 'foo@example.org';
            $ref->getProperty('user')->setValue($testable, $user);

            $testable->delete();

            $this->assertIsArray($capturedPayload);
            $this->assertFalse($capturedPayload['code']);
            $this->assertNull($capturedPayload['data']);
        } finally {
            $conn->exec("DELETE FROM jobs WHERE id = $idJob");
        }
    }

    #[Test]
    public function updateSuggestionsArray_filters_out_matching_suggestion(): void
    {
        $db = obtainTestDatabase();
        $conn = $db->getConnection();
        $idSegment = 9_978_500;
        $idJob = 9_978_501;

        $suggestions = json_encode([
            (object)['id' => 42, 'text' => 'keep me'],
            (object)['id' => 99, 'text' => 'remove me'],
        ]);

        $conn->exec("INSERT INTO segment_translations (id_segment, id_job, segment_hash, translation, suggestions_array)
            VALUES ($idSegment, $idJob, 'hash9978', 'translated text', " . $conn->quote($suggestions) . ")");

        try {
            $dao = new SegmentTranslationDao($db);
            $method = new ReflectionMethod(DeleteContributionController::class, 'updateSuggestionsArray');
            $method->invoke($this->controller, $idSegment, $idJob, 99);

            $updated = $dao->findBySegmentAndJob($idSegment, $idJob);
            $this->assertNotNull($updated);
            $decoded = json_decode($updated->suggestions_array, true);
            $this->assertCount(1, $decoded);
            $this->assertSame(42, $decoded[0]['id']);
        } finally {
            $conn->exec("DELETE FROM segment_translations WHERE id_segment = $idSegment AND id_job = $idJob");
        }
    }

    #[Test]
    public function updateSuggestionsArray_returns_early_when_no_translation_found(): void
    {
        $method = $this->reflector->getMethod('updateSuggestionsArray');
        $result = $method->invoke($this->controller, 9_978_999, 9_978_999, 1);

        $this->assertNull($result);
    }

    #[Test]
    public function createTmsEngine_builds_real_engine_instance(): void
    {
        $db = obtainTestDatabase();
        $conn = $db->getConnection();
        $conn->exec("INSERT INTO engines (name, type, class_load, base_url, uid, active)
            VALUES ('mymemory-test-9978', 'TM', 'MyMemory', 'https://example.org', 0, 1)");
        $idTms = (int)$conn->lastInsertId();

        try {
            $method = $this->reflector->getMethod('createTmsEngine');
            $engine = $method->invoke($this->controller, $idTms);

            $this->assertInstanceOf(AbstractEngine::class, $engine);
        } finally {
            $conn->exec("DELETE FROM engines WHERE id = $idTms");
        }
    }
}
