<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\JobMetadataController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Exceptions\NotFoundException;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\ExpectationFailedException;
use ReflectionClass;
use ReflectionException;
use Throwable;
use Utils\Logger\MatecatLogger;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;

class TestableJobMetadataController extends JobMetadataController
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
 * Keeps the real registerValidators(), because for this endpoint the authorization lives in the
 * validator chain rather than in the action body.
 */
class ValidatingJobMetadataController extends JobMetadataController
{
    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }

    public function runValidators(): void
    {
        // registerValidators() is normally called by the base constructor, which this double skips, so
        // it has to be invoked here or the chain would be empty and every assertion would pass for the
        // wrong reason.
        $this->registerValidators();
        $this->validateRequest();
    }
}

/**
 * Real-DB controller suite (Playbook §1/§4).
 * Reserved ID block base = 9_000_000 + (14 * 1000) = 9014000.
 *   base+1 project 9014001, base+2 job 9014002, base+3 segment 9014003, base+4 file 9014004.
 * Per-suite owner email: ctrltest_9014000@example.org.
 * Clean ONLY by reserved id.
 */
#[AllowMockObjectsWithoutExpectations]
class JobMetadataControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_000_000 + (14 * 1000);
    private const string JOB_PASSWORD = 'jobpw';

    /** @var ReflectionClass<JobMetadataController> */
    private ReflectionClass $reflector;
    private TestableJobMetadataController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;
    private string $owner;

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanTestData();
        $this->seedTestData();

        $this->controller = new TestableJobMetadataController();
        $this->reflector = new ReflectionClass(JobMetadataController::class);

        $this->requestStub = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);

        $user = new UserStruct();
        $user->uid = $this->userId(self::BASE);
        $user->email = $this->owner;
        $user->first_name = 'Ctrl';
        $user->last_name = 'Tester';
        $this->reflector->getProperty('user')->setValue($this->controller, $user);

        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createMock(MatecatLogger::class));
        $this->reflector->getProperty('featureSet')->setValue($this->controller, new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
        $this->reflector->getProperty('database')->setValue($this->controller, obtainTestDatabase());
    }

    /**
     * @throws Throwable
     */
    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

    /**
     * @throws \PDOException
     */
    private function seedTestData(): void
    {
        $this->owner = $this->ownerEmail(self::BASE);
        $this->seedProject(self::BASE, $this->owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $this->owner, self::JOB_PASSWORD);

        // seedProject points the project at teamId(BASE); the team, the user and the membership row
        // have to exist for the team authorization on this endpoint to be exercised for real.
        $this->seedTeam(self::BASE);
        $this->seedUser(self::BASE, $this->owner);
        $this->seedMembership(self::BASE);
    }

    /**
     * @throws \PDOException
     */
    private function cleanTestData(): void
    {
        $this->owner = $this->ownerEmail(self::BASE);
        $conn = $this->seedConnection();
        $conn->exec("DELETE FROM job_metadata WHERE id_job = " . $this->jobId(self::BASE));
        $this->cleanFragments(self::BASE);
    }

    /**
     * Build a request carrying both params and a JSON body with the correct content type.
     *
     * @param array<string, string> $params
     *
     * @throws ReflectionException
     */
    private function setRequest(array $params, ?string $body = null, bool $jsonContentType = false): void
    {
        $server = ['REQUEST_URI' => '/api/app/job/metadata', 'REQUEST_METHOD' => 'POST'];
        $server['CONTENT_TYPE'] = $jsonContentType ? 'application/json' : 'text/plain';
        $this->requestStub = new Request($params, [], [], $server, [], $body);
        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
    }

    /**
     * @throws Exception
     */
    private function insertMetadata(string $key, string $value): void
    {
        $dao = new MetadataDao(obtainTestDatabase());
        $dao->set($this->jobId(self::BASE), self::JOB_PASSWORD, $key, $value);
    }

    // ─── delete() ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function delete_returns_json_with_deleted_struct_id(): void
    {
        $this->insertMetadata('tm_prioritization', 'true');

        $existing = (new MetadataDao(obtainTestDatabase()))
            ->get($this->jobId(self::BASE), self::JOB_PASSWORD, 'tm_prioritization');
        $this->assertNotNull($existing);
        $expectedId = $existing->id;

        $this->setRequest([
            'id_job' => (string)$this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
            'key' => 'tm_prioritization',
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use ($expectedId): bool {
                $this->assertArrayHasKey('id', $data);
                $this->assertSame($expectedId, $data['id']);
                return true;
            }));

        $this->controller->delete();

        // row really removed
        $after = (new MetadataDao(obtainTestDatabase()))
            ->get($this->jobId(self::BASE), self::JOB_PASSWORD, 'tm_prioritization');
        $this->assertNull($after);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function delete_throws_not_found_when_metadata_absent(): void
    {
        $this->setRequest([
            'id_job' => (string)$this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
            'key' => 'tm_prioritization',
        ]);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionCode(404);

        $this->controller->delete();
    }

    // ─── save() ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function save_throws_when_request_is_not_json(): void
    {
        $this->setRequest([
            'id_job' => (string)$this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ], '[]', false);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);

        $this->controller->save();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function save_upserts_metadata_and_returns_json_with_persisted_struct(): void
    {
        $body = (string)json_encode([
            ['key' => 'tm_prioritization', 'value' => true],
        ]);

        $this->setRequest([
            'id_job' => (string)$this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ], $body, true);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertCount(1, $data);
                $struct = $data[0];
                $this->assertInstanceOf(\Model\Jobs\MetadataStruct::class, $struct);
                $this->assertSame('tm_prioritization', $struct->key);
                $this->assertSame($this->jobId(self::BASE), $struct->id_job);
                return true;
            }));

        $this->controller->save();

        // persisted in DB
        $stored = (new MetadataDao(obtainTestDatabase()))
            ->get($this->jobId(self::BASE), self::JOB_PASSWORD, 'tm_prioritization');
        $this->assertNotNull($stored);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function save_persists_array_value_as_json_encoded_string(): void
    {
        $body = (string)json_encode([
            ['key' => 'subfiltering_handlers', 'value' => []],
        ]);

        $this->setRequest([
            'id_job' => (string)$this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ], $body, true);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertCount(1, $data);
                $this->assertSame('subfiltering_handlers', $data[0]->key);
                return true;
            }));

        $this->controller->save();
    }

    // ─── save(): MT settings ───

    /**
     * The MT settings moved from project metadata to job metadata so the project owner can change
     * them after creation, and this endpoint is what writes them. The JSON Schema is the gate: a key
     * missing from it is rejected before the DAO is reached, so every one of them needs a branch.
     *
     * @throws Throwable
     */
    #[Test]
    #[DataProvider('mtSettingPayloadProvider')]
    public function save_persists_mt_setting(string $key, mixed $value, string $expectedStored): void
    {
        $body = (string)json_encode([['key' => $key, 'value' => $value]]);

        $this->setRequest([
            'id_job' => (string)$this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ], $body, true);

        $this->responseMock->expects($this->once())->method('json');

        $this->controller->save();

        $persisted = (new MetadataDao(obtainTestDatabase()))
            ->get($this->jobId(self::BASE), self::JOB_PASSWORD, $key);

        $this->assertNotNull($persisted, "'$key' was accepted but not persisted");
        $this->assertSame($expectedStored, $persisted->value);
    }

    public static function mtSettingPayloadProvider(): array
    {
        return [
            'threshold'               => ['mt_quality_value_in_editor', 90, '90'],
            'threshold zero'          => ['mt_quality_value_in_editor', 0, '0'],
            'deepl formality'         => ['deepl_formality', 'prefer_more', 'prefer_more'],
            'deepl engine type'       => ['deepl_engine_type', 'latency_optimized', 'latency_optimized'],
            'deepl glossary'          => ['deepl_id_glossary', 'gl-abc', 'gl-abc'],
            'lara style'              => ['lara_style', 'creative', 'creative'],
            'lara style guideline'    => ['lara_style_guideline_id', 'guideline-3', 'guideline-3'],
            // Arrays are JSON-encoded by the controller before they reach the DAO.
            'lara glossaries'         => ['lara_glossaries', ['a', 'b'], '["a","b"]'],
            'mmt glossaries'          => ['mmt_glossaries', [1, 2], '[1,2]'],
            'intento provider'        => ['intento_provider', 'ai.text.translate.google', 'ai.text.translate.google'],
            'intento routing'         => ['intento_routing', 'best_quality', 'best_quality'],
        ];
    }

    /**
     * @throws Throwable
     */
    #[Test]
    #[DataProvider('rejectedMtSettingPayloadProvider')]
    public function save_rejects_invalid_mt_setting(string $key, mixed $value): void
    {
        $body = (string)json_encode([['key' => $key, 'value' => $value]]);

        $this->setRequest([
            'id_job' => (string)$this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ], $body, true);

        $this->expectException(JSONValidatorException::class);

        $this->controller->save();
    }

    public static function rejectedMtSettingPayloadProvider(): array
    {
        return [
            // The engines only accept these three formalities; storing anything else would make the
            // MT call fail at request time instead of here.
            'unknown formality'   => ['deepl_formality', 'very_formal'],
            'unknown lara style'  => ['lara_style', 'formal'],
            'unknown engine type' => ['deepl_engine_type', 'fastest'],
            'threshold above 100' => ['mt_quality_value_in_editor', 101],
            'threshold below 0'   => ['mt_quality_value_in_editor', -1],
            'threshold as string' => ['mt_quality_value_in_editor', '90'],
            // An empty string is an answer, not an absence: it would shadow the project-metadata
            // fallback with a value the engine cannot use. Clearing a setting is what delete() is for.
            'empty glossary id'   => ['deepl_id_glossary', ''],
            'empty lara style guideline' => ['lara_style_guideline_id', ''],
            // mmt_glossaries holds MyMemory numeric ids, lara_glossaries opaque string ids.
            'mmt glossary strings' => ['mmt_glossaries', ['12']],
            'lara glossary ints'   => ['lara_glossaries', [12]],
            // Only the engine-tunable settings are job-scoped: the analysis was priced on this one.
            'enable_mt_analysis'   => ['enable_mt_analysis', true],
            // Consumed once at creation by MMT::syncMemories(), which has no job context.
            'context analyzer'     => ['mmt_activate_context_analyzer', true],
        ];
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function save_throws_validation_exception_for_invalid_payload(): void
    {
        $body = (string)json_encode([
            ['key' => 'not_a_valid_key', 'value' => 'whatever'],
        ]);

        $this->setRequest([
            'id_job' => (string)$this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ], $body, true);

        $this->expectException(JSONValidatorException::class);

        $this->controller->save();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function save_persists_full_payload_including_mandatory_issues(): void
    {
        $body = (string)json_encode([
            ['key' => 'character_counter_count_tags', 'value' => false],
            ['key' => 'character_counter_mode', 'value' => 'google_ads'],
            ['key' => 'subfiltering_handlers', 'value' => []],
            ['key' => 'mandatory_issues', 'value' => ['r1', 'r2']],
        ]);

        $this->setRequest([
            'id_job' => (string)$this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ], $body, true);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertCount(4, $data);
                return true;
            }));

        $this->controller->save();

        $stored = (new MetadataDao(obtainTestDatabase()))
            ->get($this->jobId(self::BASE), self::JOB_PASSWORD, 'mandatory_issues');
        $this->assertNotNull($stored);
        $this->assertSame('["r1","r2"]', $stored->value);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function save_persists_empty_mandatory_issues_as_json_array(): void
    {
        $body = (string)json_encode([
            ['key' => 'mandatory_issues', 'value' => []],
        ]);

        $this->setRequest([
            'id_job' => (string)$this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ], $body, true);

        $this->responseMock->expects($this->once())->method('json');

        $this->controller->save();

        $stored = (new MetadataDao(obtainTestDatabase()))
            ->get($this->jobId(self::BASE), self::JOB_PASSWORD, 'mandatory_issues');
        $this->assertNotNull($stored);
        $this->assertSame('[]', $stored->value);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function save_throws_validation_exception_for_mandatory_issues_with_unknown_value(): void
    {
        $body = (string)json_encode([
            ['key' => 'mandatory_issues', 'value' => ['not_a_valid_issue']],
        ]);

        $this->setRequest([
            'id_job' => (string)$this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ], $body, true);

        $this->expectException(JSONValidatorException::class);

        $this->controller->save();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function save_throws_validation_exception_for_mandatory_issues_with_additional_property(): void
    {
        $body = (string)json_encode([
            ['key' => 'mandatory_issues', 'value' => ['r1'], 'unexpected' => 'x'],
        ]);

        $this->setRequest([
            'id_job' => (string)$this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ], $body, true);

        $this->expectException(JSONValidatorException::class);

        $this->controller->save();
    }

    // ─── sanitizeRequestParams() ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function sanitizeRequestParams_returns_expected_keys(): void
    {
        $this->setRequest([
            'id_job' => '  9014002  ',
            'password' => 'pw',
            'key' => 'tm_prioritization',
        ]);

        $m = $this->reflector->getMethod('sanitizeRequestParams');
        $result = $m->invoke($this->controller);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id_job', $result);
        $this->assertArrayHasKey('password', $result);
        $this->assertArrayHasKey('key', $result);
        $this->assertSame('tm_prioritization', $result['key']);
    }

    // ─── team authorization on the validator chain ────────────────────

    /**
     * A controller carrying the real validator chain, authenticated as $uid and addressing the seeded
     * job with its correct password. Everything an ordinary share-link holder would have.
     */
    private function validatingController(int $uid): ValidatingJobMetadataController
    {
        $controller = new ValidatingJobMetadataController();
        $ref = new ReflectionClass(JobMetadataController::class);

        $user = new UserStruct();
        $user->uid = $uid;
        $user->email = $this->owner;

        $ref->getProperty('database')->setValue($controller, obtainTestDatabase());
        $ref->getProperty('request')->setValue($controller, new Request());
        $ref->getProperty('response')->setValue($controller, new Response());
        $ref->getProperty('user')->setValue($controller, $user);
        $ref->getProperty('userIsLogged')->setValue($controller, true);
        $ref->getProperty('params')->setValue($controller, [
            'id_job' => (string)$this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ]);

        return $controller;
    }

    #[Test]
    public function validators_allow_a_member_of_the_project_team(): void
    {
        $this->validatingController($this->userId(self::BASE))->runValidators();

        // Getting here means the chain resolved the job, read projects.id_team from it and matched the
        // caller's membership.
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function validators_reject_a_job_link_holder_outside_the_project_team(): void
    {
        // The job id and password are entirely valid: this is exactly what someone handed a share link
        // possesses. It is not enough, because these settings change how the job behaves for everybody
        // working on it, and the editor only offers them to the owning team.
        $this->expectException(AuthorizationError::class);

        $this->validatingController($this->userId(self::BASE) + 999999)->runValidators();
    }

    #[Test]
    public function resolveTeamId_rejects_a_project_with_no_team(): void
    {
        // A dedicated project id inside the reserved block, never read by another test: findById caches
        // for a day and offers no invalidation for that key, so mutating the shared project would be
        // invisible here and the assertion would pass for the wrong reason.
        $orphanProjectId = self::BASE + 900;
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO projects (id, id_customer, password, name, create_date, status_analysis, id_team) "
            . "VALUES ($orphanProjectId, '{$this->owner}', 'projpw', 'CtrlTestOrphan', NOW(), 'DONE', NULL)"
        );

        $chunk = new JobStruct();
        $chunk->id = $this->jobId(self::BASE);
        $chunk->id_project = $orphanProjectId;

        try {
            // Must deny rather than hand a null to a team lookup that is typed to take an int.
            $this->expectException(AuthorizationError::class);

            $m = $this->reflector->getMethod('resolveTeamId');
            $m->invoke($this->controller, $chunk);
        } finally {
            $this->seedConnection()->exec("DELETE FROM projects WHERE id = $orphanProjectId");
        }
    }
}
