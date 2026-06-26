<?php

namespace Matecat\Core\Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\JobPasswordValidator;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\Jobs\JobStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

/**
 * Minimal controller exposing the seams the Base validator ctor touches:
 * getRequest() (read in Base::__construct) and the public $params array
 * (read directly by JobPasswordValidator::_validate()).
 */
class JobPasswordValidatorTestController extends KleinController
{
    public function __construct()
    {
    }
}

/**
 * Real-DB suite. Reserved ID block base = 9_923_000 (job id, project id +1).
 */
class JobPasswordValidatorTest extends AbstractTest
{
    private const int B = 9_923_000;
    private const int JOB_ID = self::B;
    private const int PROJECT_ID = self::B + 1;
    private const string PASSWORD = 'ctrlpass9923000';
    private const string EMAIL = 'ctrltest_9923000@example.org';

    private JobPasswordValidatorTestController $controller;
    private ReflectionClass $ctrlRef;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanTestData();
        $this->seedTestData();

        $this->controller = new JobPasswordValidatorTestController();
        $this->ctrlRef = new ReflectionClass(KleinController::class);

        $user = new UserStruct();
        $user->uid = self::B;
        $user->email = self::EMAIL;
        $this->setCtrlProp('user', $user);
        $this->setCtrlProp('database', obtainTestDatabase());
        $this->setRequest();
    }

    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

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

    private function setRequest(): void
    {
        $this->setCtrlProp('request', new Request([], [], [], ['REQUEST_URI' => '/api/v2/jobs', 'REQUEST_METHOD' => 'GET']));
    }

    private function seedTestData(): void
    {
        $conn = obtainTestDatabase()->getConnection();
        $conn->exec(
            "INSERT INTO jobs (id, password, id_project, job_first_segment, job_last_segment, tm_keys, create_date, disabled, source, target, owner) "
            . "VALUES (" . self::JOB_ID . ", '" . self::PASSWORD . "', " . self::PROJECT_ID . ", 1, 1, '', NOW(), 0, 'en-US', 'it-IT', '" . self::EMAIL . "')"
        );
    }

    private function cleanTestData(): void
    {
        $conn = obtainTestDatabase()->getConnection();
        $conn->exec("DELETE FROM jobs WHERE id = " . self::JOB_ID);
    }

    // ─── happy path: valid id + password → JobStruct returned, params normalized ───

    #[Test]
    public function validates_and_returns_job_for_correct_id_and_password(): void
    {
        $this->controller->params = [
            'id_job'   => (string) self::JOB_ID,
            'password' => self::PASSWORD,
        ];

        $validator = new JobPasswordValidator($this->controller);
        $validator->validate();

        $job = $validator->getJob();
        $this->assertInstanceOf(JobStruct::class, $job);
        $this->assertSame(self::JOB_ID, (int) $job->id);
        $this->assertSame(self::PASSWORD, $job->password);

        // _validate() rewrites the sanitized values back onto the controller params.
        $this->assertSame((string) self::JOB_ID, $this->controller->params['id_job']);
        $this->assertSame(self::PASSWORD, $this->controller->params['password']);
    }

    // ─── failure: wrong password → NotFoundException ───

    #[Test]
    public function throws_not_found_for_wrong_password(): void
    {
        $this->controller->params = [
            'id_job'   => (string) self::JOB_ID,
            'password' => 'wrongpassword',
        ];

        $validator = new JobPasswordValidator($this->controller);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Job not found');

        $validator->validate();
    }

    // ─── failure: unknown id → NotFoundException ───

    #[Test]
    public function throws_not_found_for_unknown_id(): void
    {
        $this->controller->params = [
            'id_job'   => '99999999',
            'password' => self::PASSWORD,
        ];

        $validator = new JobPasswordValidator($this->controller);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Job not found');

        $validator->validate();
    }
}
