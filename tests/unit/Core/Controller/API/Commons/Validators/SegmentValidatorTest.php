<?php

namespace Matecat\Core\Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Validators\SegmentValidator;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

/**
 * Minimal controller stub exposing the seams SegmentValidator touches:
 * getRequest() (from KleinController) and getParams().
 */
class SegmentValidatorTestController extends KleinController
{
    public function __construct()
    {
        // intentionally empty — skip parent wiring
    }
}

/**
 * Real-DB suite for SegmentValidator.
 * Reserved ID block base = 9_927_000.
 * Owner e-mail:          ctrltest_9927000@example.org
 */
class SegmentValidatorTest extends AbstractTest
{
    private const int    B          = 9_927_000;
    private const int    PROJECT_ID = self::B;
    private const int    FILE_ID    = self::B + 1;
    private const int    JOB_ID     = self::B + 2;
    private const int    SEGMENT_ID = self::B + 3;
    private const string PASSWORD   = 'testpass9927000';
    private const string EMAIL      = 'ctrltest_9927000@example.org';

    private SegmentValidatorTestController $controller;
    private ReflectionClass $ctrlRef;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanTestData();
        $this->seedTestData();

        $this->controller = new SegmentValidatorTestController();
        $this->ctrlRef    = new ReflectionClass(KleinController::class);

        // Provide a minimal request so Base::__construct() does not fail.
        $this->setCtrlProp(
            'request',
            new Request([], [], [], ['REQUEST_URI' => '/api/v2/jobs/' . self::JOB_ID . '/segments/' . self::SEGMENT_ID, 'REQUEST_METHOD' => 'GET'])
        );
        $this->setCtrlProp('database', Database::obtain());
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

        // project
        $conn->exec(
            "INSERT INTO projects (id, password, id_customer, create_date) VALUES ("
            . self::PROJECT_ID . ", 'projpass9927', '" . self::EMAIL . "', NOW())"
        );

        // file
        $conn->exec(
            "INSERT INTO files (id, id_project, source_language) VALUES ("
            . self::FILE_ID . ", " . self::PROJECT_ID . ", 'en')"
        );

        // job — job_first_segment / job_last_segment bracket the segment id
        $conn->exec(
            "INSERT INTO jobs (id, password, id_project, job_first_segment, job_last_segment, tm_keys, create_date, disabled) VALUES ("
            . self::JOB_ID . ", '" . self::PASSWORD . "', " . self::PROJECT_ID . ", "
            . self::SEGMENT_ID . ", " . self::SEGMENT_ID . ", '[]', NOW(), 0)"
        );

        // files_job join
        $conn->exec(
            "INSERT INTO files_job (id_job, id_file) VALUES ("
            . self::JOB_ID . ", " . self::FILE_ID . ")"
        );

        // segment — internal_id, segment, raw_word_count are non-nullable in SegmentStruct
        $conn->exec(
            "INSERT INTO segments (id, id_file, internal_id, segment, segment_hash, raw_word_count) VALUES ("
            . self::SEGMENT_ID . ", " . self::FILE_ID . ", 'seg9927000', 'Test segment text', MD5('test_segment_9927000'), 3)"
        );
    }

    private function cleanTestData(): void
    {
        $conn = Database::obtain()->getConnection();
        $conn->exec("DELETE FROM segments  WHERE id      = " . self::SEGMENT_ID);
        $conn->exec("DELETE FROM files_job WHERE id_job  = " . self::JOB_ID);
        $conn->exec("DELETE FROM jobs      WHERE id      = " . self::JOB_ID);
        $conn->exec("DELETE FROM files     WHERE id      = " . self::FILE_ID);
        $conn->exec("DELETE FROM projects  WHERE id      = " . self::PROJECT_ID);
    }

    // ─── happy path ────────────────────────────────────────────────────────────

    #[Test]
    public function validates_when_segment_belongs_to_chunk(): void
    {
        $this->controller->params = [
            'id_job'     => self::JOB_ID,
            'password'   => self::PASSWORD,
            'id_segment' => self::SEGMENT_ID,
        ];

        $validator = new SegmentValidator($this->controller);

        // Must not throw
        $validator->validate();

        $this->assertTrue(true);
    }

    // ─── failure: segment not found ────────────────────────────────────────────

    #[Test]
    public function throws_not_found_when_segment_missing(): void
    {
        $this->controller->params = [
            'id_job'     => self::JOB_ID,
            'password'   => self::PASSWORD,
            'id_segment' => 9999999999, // non-existent
        ];

        $validator = new SegmentValidator($this->controller);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionCode(404);

        $validator->validate();
    }

    // ─── failure: wrong password ────────────────────────────────────────────────

    #[Test]
    public function throws_not_found_when_password_is_wrong(): void
    {
        $this->controller->params = [
            'id_job'     => self::JOB_ID,
            'password'   => 'wrong_password',
            'id_segment' => self::SEGMENT_ID,
        ];

        $validator = new SegmentValidator($this->controller);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionCode(404);

        $validator->validate();
    }

    // ─── failure: wrong job id ─────────────────────────────────────────────────

    #[Test]
    public function throws_not_found_when_job_id_is_wrong(): void
    {
        $this->controller->params = [
            'id_job'     => 9999999998,
            'password'   => self::PASSWORD,
            'id_segment' => self::SEGMENT_ID,
        ];

        $validator = new SegmentValidator($this->controller);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionCode(404);

        $validator->validate();
    }

    // ─── validate() public wrapper (success path + callback) ──────────────────

    #[Test]
    public function validate_public_wrapper_invokes_success_callback(): void
    {
        $this->controller->params = [
            'id_job'     => self::JOB_ID,
            'password'   => self::PASSWORD,
            'id_segment' => self::SEGMENT_ID,
        ];

        $validator = new SegmentValidator($this->controller);

        $called = false;
        $validator->onSuccess(function () use (&$called) {
            $called = true;
        });

        $validator->validate();

        $this->assertTrue($called);
    }

    // ─── validate() public wrapper (failure + onFailure callback) ─────────────

    #[Test]
    public function validate_public_wrapper_invokes_failure_callback(): void
    {
        $this->controller->params = [
            'id_job'     => self::JOB_ID,
            'password'   => 'bad',
            'id_segment' => self::SEGMENT_ID,
        ];

        $validator = new SegmentValidator($this->controller);

        $caught = null;
        $validator->onFailure(function (\Throwable $e) use (&$caught) {
            $caught = $e;
        });

        $validator->validate(); // must not re-throw

        $this->assertInstanceOf(NotFoundException::class, $caught);
        $this->assertSame(404, $caught->getCode());
    }
}
