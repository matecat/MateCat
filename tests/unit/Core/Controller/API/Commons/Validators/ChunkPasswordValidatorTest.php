<?php

namespace Matecat\Core\Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

/**
 * Minimal controller exposing the seams ChunkPasswordValidator touches:
 * getRequest() and getParams() (the validator ctor reads getParams()).
 */
class ChunkPasswordValidatorTestController extends KleinController
{
    public function __construct()
    {
    }
}

/**
 * Real-DB suite. Reserved ID block base = 9_932_000 (1000 ids reserved).
 */
class ChunkPasswordValidatorTest extends AbstractTest
{
    private const int B = 9_932_000;
    private const int PROJECT_ID = self::B;
    private const int JOB_ID = self::B + 1;
    private const string JOB_PASSWORD = 'cpw9932000pwd';
    private const string REVIEW_PASSWORD = 'cpw9932000rev';
    private const string EMAIL = 'ctrltest_9932000@example.org';

    private ChunkPasswordValidatorTestController $controller;
    private ReflectionClass $ctrlRef;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanTestData();
        $this->seedTestData();

        $this->controller = new ChunkPasswordValidatorTestController();
        $this->ctrlRef = new ReflectionClass(KleinController::class);
        $this->setCtrlProp('database', obtainTestDatabase());
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

    /**
     * Configure both the request (param() reads) and params (getParams() reads in ctor).
     *
     * @param array<string,string> $params
     */
    private function configureRequest(array $params): void
    {
        $this->setCtrlProp('request', new Request($params, [], [], ['REQUEST_URI' => '/api/v2/jobs', 'REQUEST_METHOD' => 'GET']));
        $this->setCtrlProp('params', $params);
    }

    private function seedTestData(): void
    {
        $conn = obtainTestDatabase()->getConnection();
        $conn->exec(
            "INSERT INTO projects (id, password, id_customer, name, create_date, status_analysis)
             VALUES (" . self::PROJECT_ID . ", 'cpwproj', '" . self::EMAIL . "', 'CtrlTestProject9932000', NOW(), 'DONE')"
        );
        $conn->exec(
            "INSERT INTO jobs (id, password, id_project, source, target, owner)
             VALUES (" . self::JOB_ID . ", '" . self::JOB_PASSWORD . "', " . self::PROJECT_ID . ", 'en-US', 'it-IT', '" . self::EMAIL . "')"
        );
        $conn->exec(
            "INSERT INTO qa_chunk_reviews (id_job, id_project, password, review_password, source_page)
             VALUES (" . self::JOB_ID . ", " . self::PROJECT_ID . ", '" . self::JOB_PASSWORD . "', '" . self::REVIEW_PASSWORD . "', 2)"
        );
    }

    private function cleanTestData(): void
    {
        $conn = obtainTestDatabase()->getConnection();
        $conn->exec("DELETE FROM qa_chunk_reviews WHERE id_job = " . self::JOB_ID);
        $conn->exec("DELETE FROM jobs WHERE id = " . self::JOB_ID);
        $conn->exec("DELETE FROM projects WHERE id = " . self::PROJECT_ID);
    }

    // ─── translate-password happy path: job + chunkReview resolved ───

    #[Test]
    public function validates_with_translate_password(): void
    {
        $this->configureRequest([
            'id_job' => (string) self::JOB_ID,
            'password' => self::JOB_PASSWORD,
        ]);

        $validator = new ChunkPasswordValidator($this->controller);
        $validator->validate();

        $this->assertInstanceOf(JobStruct::class, $validator->getChunk());
        $this->assertSame(self::JOB_ID, $validator->getChunk()->id);
        $this->assertSame(self::JOB_ID, $validator->getJobId());
        $this->assertInstanceOf(ChunkReviewStruct::class, $validator->getChunkReview());
        $this->assertSame(self::REVIEW_PASSWORD, $validator->getChunkReview()->review_password);
    }

    // ─── revision_number present is parsed in ctor (translate path still resolves) ───

    #[Test]
    public function validates_with_revision_number_present(): void
    {
        $this->configureRequest([
            'id_job' => (string) self::JOB_ID,
            'password' => self::JOB_PASSWORD,
            'revision_number' => '2',
        ]);

        $validator = new ChunkPasswordValidator($this->controller);
        $validator->validate();

        $this->assertSame(self::JOB_ID, $validator->getChunk()->id);
    }

    // ─── review-password happy path: translate fails, review password resolves ───

    #[Test]
    public function validates_with_review_password(): void
    {
        $this->configureRequest([
            'id_job' => (string) self::JOB_ID,
            'password' => self::REVIEW_PASSWORD,
        ]);

        $validator = new ChunkPasswordValidator($this->controller);
        $validator->validate();

        $chunk = $validator->getChunk();
        $this->assertInstanceOf(JobStruct::class, $chunk);
        $this->assertSame(self::JOB_ID, $chunk->id);
        $this->assertTrue($chunk->getIsReview());
        $this->assertFalse($chunk->isSecondPassReview());
        $this->assertInstanceOf(ChunkReviewStruct::class, $validator->getChunkReview());
    }

    // ─── neither translate nor review password matches => NotFoundException ───

    #[Test]
    public function throws_not_found_when_no_password_matches(): void
    {
        $this->configureRequest([
            'id_job' => (string) self::JOB_ID,
            'password' => 'wrong-password-xyz',
        ]);

        $validator = new ChunkPasswordValidator($this->controller);

        $this->expectException(NotFoundException::class);

        $validator->validate();
    }
}
