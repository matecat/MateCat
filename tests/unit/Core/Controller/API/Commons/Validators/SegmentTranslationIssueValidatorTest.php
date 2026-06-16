<?php

declare(strict_types=1);

namespace Matecat\Core\Controller\API\Commons\Validators;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\ValidationError;
use Controller\API\Commons\Validators\SegmentTranslationIssueValidator;
use Exception;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\Exceptions\NotFoundException;
use Model\LQA\ChunkReviewStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

/**
 * Minimal controller exposing the seam SegmentTranslationIssueValidator (and the
 * SegmentTranslation validator it instantiates) touches: getRequest().
 * Base ctor reads $controller->getRequest(); empty ctor avoids the full
 * KleinController boot.
 */
class SegmentTranslationIssueValidatorTestController extends KleinController
{
    public function __construct()
    {
    }
}

/**
 * Real-DB suite. Reserved ID block base = 9_926_000 (1000-block reserved).
 * Per-suite unique owner email: ctrltest_9926000@example.org.
 */
class SegmentTranslationIssueValidatorTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_926_000;
    private const int QA_CATEGORY_ID = self::BASE + 100;
    private const int QA_ENTRY_ID = self::BASE + 101;
    private const int EVENT_ID = self::BASE + 102;

    private const string JOB_PASSWORD = 'jobpw';

    private SegmentTranslationIssueValidatorTestController $controller;
    private ReflectionClass $ctrlRef;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanTestData();
        $this->seedTestData();

        $this->controller = new SegmentTranslationIssueValidatorTestController();
        $this->ctrlRef    = new ReflectionClass(KleinController::class);
    }

    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

    // ─── seeding ───

    private function seedTestData(): void
    {
        $owner = $this->ownerEmail(self::BASE);

        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner, self::JOB_PASSWORD);
        $this->seedSegment(self::BASE);
        $this->seedQaModel(self::BASE);

        $conn = $this->seedConnection();

        // ModelStruct::$hash is a non-nullable string; the fragment leaves the column NULL.
        $conn->exec('UPDATE qa_models SET hash = 1 WHERE id = ' . $this->qaModelId(self::BASE));

        // wire the qa_model onto the project so EntryValidator::validateCategoryId() resolves it
        $conn->exec('UPDATE projects SET id_qa_model = ' . $this->qaModelId(self::BASE) . ' WHERE id = ' . $this->projectId(self::BASE));

        // qa_category belonging to the seeded model (EntryValidator checks id_model == qa_model->id)
        $conn->exec(
            'INSERT IGNORE INTO qa_categories (id, id_model, label, severities) '
            . 'VALUES (' . self::QA_CATEGORY_ID . ', ' . $this->qaModelId(self::BASE) . ", 'CtrlTestCategory', '[]')"
        );
    }

    private function cleanTestData(): void
    {
        $conn = $this->seedConnection();
        $conn->exec('DELETE FROM segment_translation_events WHERE id = ' . self::EVENT_ID);
        $conn->exec('DELETE FROM qa_entries WHERE id = ' . self::QA_ENTRY_ID);
        $conn->exec('DELETE FROM qa_categories WHERE id = ' . self::QA_CATEGORY_ID);
        $this->cleanFragments(self::BASE);
    }

    /**
     * Insert the segment_translations row the SegmentTranslation validator requires.
     * $iceLocked seeds a locked ICE (match_type=ICE, locked=1, tm_analysis_status not SKIPPED)
     * so SegmentTranslationStruct::isICE() returns true.
     */
    private function seedTranslation(bool $iceLocked = false): void
    {
        $segmentId = $this->segmentId(self::BASE);
        $jobId     = $this->jobId(self::BASE);
        $hash      = 'ctrltest_hash_' . self::BASE;
        $match     = $iceLocked ? 'ICE' : 'TM';
        $locked    = $iceLocked ? 1 : 0;
        $this->seedConnection()->exec(
            'INSERT IGNORE INTO segment_translations '
            . '(id_segment, id_job, segment_hash, translation, status, version_number, match_type, locked, translation_date) '
            . "VALUES ($segmentId, $jobId, '$hash', 'Ciao mondo', 'TRANSLATED', 0, '$match', $locked, NOW())"
        );
    }

    /**
     * Insert a valid qa_entry. $sourcePage controls qa_entries.source_page (used by the DELETE branch).
     */
    private function seedQaEntry(int $sourcePage = 1): void
    {
        $segmentId = $this->segmentId(self::BASE);
        $jobId     = $this->jobId(self::BASE);
        $this->seedConnection()->exec(
            'INSERT IGNORE INTO qa_entries '
            . '(id, uid, id_segment, id_job, id_category, severity, translation_version, '
            . 'start_node, start_offset, end_node, end_offset, target_text, is_full_segment, '
            . 'penalty_points, source_page, create_date) '
            . 'VALUES (' . self::QA_ENTRY_ID . ', 1, ' . $segmentId . ', ' . $jobId . ', '
            . self::QA_CATEGORY_ID . ", 'CtrlTestCategory', 0, 0, 0, 0, 5, 'Ciao mondo', 1, 1.00, $sourcePage, NOW())"
        );
    }

    private function seedSegmentEvent(int $sourcePage): void
    {
        $segmentId = $this->segmentId(self::BASE);
        $jobId     = $this->jobId(self::BASE);
        $uid       = $this->userId(self::BASE);
        $this->seedConnection()->exec(
            'INSERT IGNORE INTO segment_translation_events '
            . '(id, id_job, id_segment, uid, version_number, source_page, status, create_date) '
            . "VALUES (" . self::EVENT_ID . ", $jobId, $segmentId, $uid, 0, $sourcePage, 'translated', NOW())"
        );
    }

    // ─── reflection / request seams ───

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
     * @param array<string,string> $params
     */
    private function setRequest(array $params, string $method = 'GET'): void
    {
        $isPost = strtoupper($method) === 'POST';
        $get    = $isPost ? [] : $params;
        $post   = $isPost ? $params : [];
        $this->setCtrlProp('request', new Request(
            $get,
            $post,
            [],
            ['REQUEST_URI' => '/api/v2/jobs', 'REQUEST_METHOD' => strtoupper($method)]
        ));
    }

    private function makeValidator(): SegmentTranslationIssueValidator
    {
        return new SegmentTranslationIssueValidator($this->controller);
    }

    private function makeChunkReview(int $sourcePage): ChunkReviewStruct
    {
        $cr             = new ChunkReviewStruct();
        $cr->id_job     = $this->jobId(self::BASE);
        $cr->source_page = $sourcePage;

        return $cr;
    }

    /**
     * @param array<string,string> $extra
     */
    private function baseParams(array $extra = []): array
    {
        return array_merge([
            'id_segment' => (string) $this->segmentId(self::BASE),
            'id_job'     => (string) $this->jobId(self::BASE),
        ], $extra);
    }

    // ─── SegmentTranslation gate ───

    #[Test]
    public function throws_not_found_when_segment_translation_missing(): void
    {
        // no segment_translations row seeded
        $this->setRequest($this->baseParams());

        $this->expectException(NotFoundException::class);
        $this->makeValidator()->_validate();
    }

    #[Test]
    public function validates_happy_path_get_without_issue(): void
    {
        $this->seedTranslation();
        $this->setRequest($this->baseParams());

        $validator = $this->makeValidator();
        $validator->_validate();

        $this->assertSame($this->segmentId(self::BASE), $validator->translation->id_segment);
    }

    // ─── __ensureIssueIsInScope ───

    #[Test]
    public function throws_validation_error_when_issue_not_found(): void
    {
        $this->seedTranslation();
        $this->setRequest($this->baseParams(['id_issue' => (string) self::QA_ENTRY_ID]));

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('issue not found');
        $this->makeValidator()->_validate();
    }

    #[Test]
    public function throws_validation_error_when_issue_segment_mismatch(): void
    {
        $this->seedTranslation();
        // entry exists but on a different segment than the translation
        $jobId = $this->jobId(self::BASE);
        $this->seedConnection()->exec(
            'INSERT IGNORE INTO qa_entries '
            . '(id, uid, id_segment, id_job, id_category, severity, translation_version, '
            . 'start_node, start_offset, end_node, end_offset, target_text, is_full_segment, '
            . 'penalty_points, source_page, create_date) '
            . 'VALUES (' . self::QA_ENTRY_ID . ', 1, ' . (self::BASE + 999) . ', ' . $jobId . ', '
            . self::QA_CATEGORY_ID . ", 'CtrlTestCategory', 0, 0, 0, 0, 5, 'Ciao mondo', 1, 1.00, 1, NOW())"
        );

        $this->setRequest($this->baseParams(['id_issue' => (string) self::QA_ENTRY_ID]));

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('issue not found');
        $this->makeValidator()->_validate();
    }

    #[Test]
    public function validates_issue_in_scope_happy_path(): void
    {
        $this->seedTranslation();
        $this->seedQaEntry(1);

        // GET with id_issue: __ensureIssueIsInScope runs and ensureValid() must pass.
        $this->setRequest($this->baseParams(['id_issue' => (string) self::QA_ENTRY_ID]));

        $validator = $this->makeValidator();
        $validator->_validate();

        $this->assertNotNull($validator->issue);
        $this->assertSame(self::QA_ENTRY_ID, $validator->issue->id);
    }

    // ─── __ensureSegmentRevisionIsCompatibleWithIssueRevisionNumber (POST) ───

    #[Test]
    public function throws_validation_error_minus_2000_on_unmodified_ice(): void
    {
        $this->seedTranslation(true); // locked ICE, no segment event
        $this->setRequest($this->baseParams(['revision_number' => '1']), 'POST');

        $validator = $this->makeValidator();
        $validator->setChunkReview($this->makeChunkReview(2));

        try {
            $validator->_validate();
            $this->fail('Expected ValidationError');
        } catch (ValidationError $e) {
            $this->assertSame(-2000, $e->getCode());
            $this->assertStringContainsString('unmodified ICE', $e->getMessage());
        }
    }

    #[Test]
    public function throws_validation_error_on_revision_state_mismatch(): void
    {
        $this->seedTranslation();
        // event source_page (3) != revisionNumberToSourcePage(1) == 2
        $this->seedSegmentEvent(3);
        $this->setRequest($this->baseParams(['revision_number' => '1']), 'POST');

        $validator = $this->makeValidator();
        $validator->setChunkReview($this->makeChunkReview(2));

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('not in same revision state');
        $validator->_validate();
    }

    #[Test]
    public function validates_post_revision_when_state_matches(): void
    {
        $this->seedTranslation();
        // revisionNumberToSourcePage(1) == 2, so event source_page must be 2
        $this->seedSegmentEvent(2);
        $this->setRequest($this->baseParams(['revision_number' => '1']), 'POST');

        $validator = $this->makeValidator();
        $validator->setChunkReview($this->makeChunkReview(2));
        $validator->_validate();

        $this->assertSame($this->segmentId(self::BASE), $validator->translation->id_segment);
    }

    // ─── __ensureRevisionPasswordAllowsDeleteForIssue (DELETE) ───

    #[Test]
    public function throws_validation_error_when_delete_not_enough_privileges(): void
    {
        $this->seedTranslation();
        $this->seedQaEntry(3); // issue.source_page = 3 > chunkReview.source_page = 2
        $this->setRequest($this->baseParams(['id_issue' => (string) self::QA_ENTRY_ID]), 'DELETE');

        $validator = $this->makeValidator();
        $validator->setChunkReview($this->makeChunkReview(2));

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Not enough privileges to delete this issue');
        $validator->_validate();
    }

    #[Test]
    public function validates_delete_when_privileges_allow(): void
    {
        $this->seedTranslation();
        $this->seedQaEntry(1); // issue.source_page = 1 <= chunkReview.source_page = 2
        $this->setRequest($this->baseParams(['id_issue' => (string) self::QA_ENTRY_ID]), 'DELETE');

        $validator = $this->makeValidator();
        $validator->setChunkReview($this->makeChunkReview(2));
        $validator->_validate();

        $this->assertSame(self::QA_ENTRY_ID, $validator->issue->id);
    }
}
