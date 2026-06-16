<?php

namespace Matecat\Core\Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\SegmentTranslation;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\Translations\SegmentTranslationStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

/**
 * Minimal controller stub exposing only the seams SegmentTranslation validator touches:
 * getRequest() (inherited from KleinController, reads $this->request).
 */
class SegmentTranslationTestController extends KleinController
{
    public function __construct()
    {
        // intentionally empty — skip the real Klein bootstrap
    }
}

/**
 * Real-DB suite.
 *
 * Reserved ID block base = 9_925_000.
 * Owner e-mail: ctrltest_9925000@example.org
 */
class SegmentTranslationTest extends AbstractTest
{
    private const int B            = 9_925_000;
    private const int ID_SEGMENT   = self::B;
    private const int ID_JOB       = self::B + 1;
    private const string SEG_HASH  = 'testhash9925000';

    private SegmentTranslationTestController $controller;
    private ReflectionClass $ctrlRef;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanTestData();
        $this->seedTestData();

        $this->controller = new SegmentTranslationTestController();
        $this->ctrlRef    = new ReflectionClass(KleinController::class);
    }

    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

    // ── helpers ─────────────────────────────────────────────────────────────

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

    private function setRequest(array $params): void
    {
        $this->setCtrlProp(
            'request',
            new Request(
                $params,
                [],
                [],
                ['REQUEST_URI' => '/api/v2/segments', 'REQUEST_METHOD' => 'GET']
            )
        );
    }

    private function seedTestData(): void
    {
        $conn = Database::obtain()->getConnection();
        $conn->exec(
            "INSERT IGNORE INTO segment_translations
                (id_segment, id_job, segment_hash, status, translation, translation_date, time_to_edit)
             VALUES
                (" . self::ID_SEGMENT . ", " . self::ID_JOB . ", '" . self::SEG_HASH . "', 'DRAFT', 'hello world', NOW(), 0)"
        );
    }

    private function cleanTestData(): void
    {
        $conn = Database::obtain()->getConnection();
        $conn->exec(
            "DELETE FROM segment_translations WHERE id_segment = " . self::ID_SEGMENT . " AND id_job = " . self::ID_JOB
        );
    }

    // ── happy path ──────────────────────────────────────────────────────────

    #[Test]
    public function validates_when_translation_exists(): void
    {
        $this->setRequest([
            'id_segment' => (string) self::ID_SEGMENT,
            'id_job'     => (string) self::ID_JOB,
        ]);

        $validator = new SegmentTranslation($this->controller);
        $validator->validate();

        $this->assertInstanceOf(SegmentTranslationStruct::class, $validator->translation);
        $this->assertSame(self::ID_SEGMENT, $validator->translation->id_segment);
        $this->assertSame(self::ID_JOB,     $validator->translation->id_job);
    }

    #[Test]
    public function getTranslation_returns_struct_after_validate(): void
    {
        $this->setRequest([
            'id_segment' => (string) self::ID_SEGMENT,
            'id_job'     => (string) self::ID_JOB,
        ]);

        $validator = new SegmentTranslation($this->controller);
        $validator->validate();

        $result = $validator->getTranslation();

        $this->assertInstanceOf(SegmentTranslationStruct::class, $result);
        $this->assertSame('hello world', $result->translation);
    }

    #[Test]
    public function getTranslation_returns_null_before_validate(): void
    {
        $this->setRequest([
            'id_segment' => (string) self::ID_SEGMENT,
            'id_job'     => (string) self::ID_JOB,
        ]);

        $validator = new SegmentTranslation($this->controller);

        $this->assertNull($validator->getTranslation());
    }

    // ── failure path ─────────────────────────────────────────────────────────

    #[Test]
    public function throws_not_found_when_segment_does_not_exist(): void
    {
        $this->setRequest([
            'id_segment' => '99999991',
            'id_job'     => '99999992',
        ]);

        $validator = new SegmentTranslation($this->controller);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('translation not found');

        $validator->validate();
    }

    #[Test]
    public function throws_not_found_when_job_does_not_match(): void
    {
        $this->setRequest([
            'id_segment' => (string) self::ID_SEGMENT,
            'id_job'     => '99999993',  // wrong job
        ]);

        $validator = new SegmentTranslation($this->controller);

        $this->expectException(NotFoundException::class);

        $validator->validate();
    }

    #[Test]
    public function throws_not_found_when_segment_does_not_match(): void
    {
        $this->setRequest([
            'id_segment' => '99999994',  // wrong segment
            'id_job'     => (string) self::ID_JOB,
        ]);

        $validator = new SegmentTranslation($this->controller);

        $this->expectException(NotFoundException::class);

        $validator->validate();
    }
}
