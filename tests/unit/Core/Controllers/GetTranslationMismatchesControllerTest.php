<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\GetTranslationMismatchesController;
use Controller\API\Commons\Validators\Base;
use Controller\API\Commons\Validators\JobPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Logger\MatecatLogger;

/**
 * Real-DB suite for GetTranslationMismatchesController.
 *
 * Reserved ID block (Playbook §4): base = 9019000
 *   base+1 project (9019001), base+2 job (9019002),
 *   base+3 segment (9019003), base+4 file (9019004), base+5 team (9019005).
 * Per-suite owner email: ctrltest_9019000@example.org.
 * Clean ONLY by reserved id.
 */
class TestableGetTranslationMismatchesController extends GetTranslationMismatchesController
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
 * Runs the REAL registerValidators() while capturing the appended validators,
 * so the registration wiring can be asserted without a mock generator.
 */
class RecordingGetTranslationMismatchesController extends GetTranslationMismatchesController
{
    /** @var array<int, Base> */
    public array $appended = [];

    public function __construct()
    {
    }

    protected function appendValidator(Base $validator): \Controller\Abstracts\KleinController
    {
        $this->appended[] = $validator;

        return $this;
    }

    public function runRegisterValidators(): void
    {
        $this->registerValidators();
    }
}

#[AllowMockObjectsWithoutExpectations]
class GetTranslationMismatchesControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9019000;

    /** @var ReflectionClass<GetTranslationMismatchesController> */
    private ReflectionClass $reflector;
    private TestableGetTranslationMismatchesController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \Exception
     * @throws \TypeError
     * @throws \PHPUnit\Event\NoPreviousThrowableException
     * @throws \PHPUnit\Framework\InvalidArgumentException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedTestData();

        $this->controller = new TestableGetTranslationMismatchesController();
        $this->reflector = new ReflectionClass(GetTranslationMismatchesController::class);

        $this->requestStub = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);

        $user = new UserStruct();
        $user->uid = $this->userId(self::BASE);
        $user->email = $this->ownerEmail(self::BASE);
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

    private function seedTestData(): void
    {
        $owner = $this->ownerEmail(self::BASE);
        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner, 'jobpw');
        $this->seedSegment(self::BASE);
        $this->seedSegmentTranslation(self::BASE);
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
        $serverParams = ['REQUEST_URI' => '/api/app/mismatches', 'REQUEST_METHOD' => 'GET'];
        $this->requestStub = new Request($params, [], [], $serverParams);
        $this->setProp('request', $this->requestStub);
    }

    /**
     * The validators populate $this->params and $this->chunk at runtime; the
     * Testable subclass skips them, so we set them directly for the action.
     *
     * @throws ReflectionException
     */
    private function prepareAuthenticatedRequest(int $idSegment): void
    {
        $this->setRequestParams(['id_segment' => (string) $idSegment]);

        $this->setProp('params', [
            'id_job'   => $this->jobId(self::BASE),
            'password' => 'jobpw',
        ]);

        $chunk = new JobStruct();
        $chunk->id = $this->jobId(self::BASE);
        $chunk->password = 'jobpw';
        $this->setProp('chunk', $chunk);
    }

    // ─── get() public action ───

    /**
     * @throws \Exception
     * @throws ReflectionException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\Exception
     */
    #[Test]
    public function get_returns_mismatches_view_payload_for_valid_chunk(): void
    {
        $this->prepareAuthenticatedRequest($this->segmentId(self::BASE));

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->get();

        $this->assertIsArray($captured);
        $this->assertSame(1, $captured['code']);
        $this->assertSame([], $captured['errors']);
        $this->assertIsArray($captured['data']);
        $this->assertArrayHasKey('editable', $captured['data']);
        $this->assertArrayHasKey('not_editable', $captured['data']);
        $this->assertArrayHasKey('prop_available', $captured['data']);
    }

    /**
     * @throws \Exception
     * @throws ReflectionException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\Exception
     */
    #[Test]
    public function get_defaults_id_segment_to_zero_when_param_missing(): void
    {
        // id_segment param absent -> parseIdSegment yields '' -> coerced to '0'
        $this->setRequestParams([]);
        $this->setProp('params', [
            'id_job'   => $this->jobId(self::BASE),
            'password' => 'jobpw',
        ]);
        $chunk = new JobStruct();
        $chunk->id = $this->jobId(self::BASE);
        $chunk->password = 'jobpw';
        $this->setProp('chunk', $chunk);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->get();

        $this->assertIsArray($captured);
        $this->assertSame(1, $captured['code']);
        $this->assertSame([], $captured['data']['editable']);
        $this->assertSame([], $captured['data']['not_editable']);
    }

    /**
     * @throws \Exception
     * @throws ReflectionException
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \PHPUnit\Framework\Exception
     */
    #[Test]
    public function get_returns_empty_data_when_chunk_password_does_not_match_job(): void
    {
        // params password mismatches the seeded job -> DAO returns [] mismatches
        $this->setRequestParams(['id_segment' => (string) $this->segmentId(self::BASE)]);
        $this->setProp('params', [
            'id_job'   => $this->jobId(self::BASE),
            'password' => 'wrong_password_xyz',
        ]);
        $chunk = new JobStruct();
        $chunk->id = $this->jobId(self::BASE);
        $chunk->password = 'wrong_password_xyz';
        $this->setProp('chunk', $chunk);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->get();

        $this->assertIsArray($captured);
        $this->assertSame([], $captured['data']['editable']);
        $this->assertSame([], $captured['data']['not_editable']);
        $this->assertSame(0, $captured['data']['prop_available']);
    }

    // ─── registerValidators() ───

    /**
     * @throws ReflectionException
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \Exception
     * @throws \TypeError
     * @throws \PHPUnit\Event\NoPreviousThrowableException
     * @throws \PHPUnit\Framework\InvalidArgumentException
     * @throws \PHPUnit\Framework\Exception
     */
    #[Test]
    public function registerValidators_appends_login_and_job_password_validators(): void
    {
        $controller = new RecordingGetTranslationMismatchesController();

        $reflector = new ReflectionClass(GetTranslationMismatchesController::class);
        // Validators read request/response/user on construction.
        $reflector->getProperty('request')->setValue($controller, new Request());
        $reflector->getProperty('response')->setValue($controller, $this->createMock(Response::class));
        $user = new UserStruct();
        $user->uid = $this->userId(self::BASE);
        $reflector->getProperty('user')->setValue($controller, $user);

        $controller->runRegisterValidators();

        $this->assertCount(2, $controller->appended);
        $this->assertInstanceOf(LoginValidator::class, $controller->appended[0]);
        $this->assertInstanceOf(JobPasswordValidator::class, $controller->appended[1]);
    }
}
