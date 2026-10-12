<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\CopyAllSourceToTargetController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use DomainException;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Projects\MetadataDao;
use Model\Users\UserStruct;
use PDOException;
use PHPUnit\Event\NoPreviousThrowableException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Exception as PHPUnitException;
use PHPUnit\Framework\InvalidArgumentException as PHPUnitInvalidArgumentException;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use TypeError;
use Utils\Constants\SourcePages;
use Utils\Logger\MatecatLogger;

/**
 * Real-DB suite for {@see CopyAllSourceToTargetController}.
 *
 * Reserved ID block (Playbook §4): base = 9_007_000 (task N=7).
 *   9007001 project, 9007002 job, 9007003 segment, 9007004 file.
 * Owner email: ctrltest_9007000@example.org (never the shared test@example.org).
 * Clean ONLY by reserved id; clean-then-seed in setUp(); parent::tearDown() last.
 */
class TestableCopyAllSourceToTargetController extends CopyAllSourceToTargetController
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

#[AllowMockObjectsWithoutExpectations]
class CopyAllSourceToTargetControllerTest extends AbstractTest
{
    use ControllerSeedFragments {
        cleanFragments as private cleanReservedFragments;
    }

    private const int BASE = 9_007_000;
    private const string JOB_PASSWORD = 'jobpw';

    /** @var ReflectionClass<CopyAllSourceToTargetController> */
    private ReflectionClass $reflector;
    private TestableCopyAllSourceToTargetController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws NoPreviousThrowableException
     * @throws PHPUnitInvalidArgumentException
     * @throws MockObjectException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedTestData();

        $this->controller = new TestableCopyAllSourceToTargetController();
        $this->reflector  = new ReflectionClass(CopyAllSourceToTargetController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);
        $this->setProp('database', obtainTestDatabase());

        $user        = new UserStruct();
        $user->uid   = $this->userId(self::BASE);
        $user->email = $this->ownerEmail(self::BASE);
        $this->setProp('user', $user);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));

        // In production the ChunkPasswordValidator loads $this->chunk before copy() runs; the
        // TestableController skips the validator chain, so seed the same chunk (translate source_page).
        $this->seedChunk();
    }

    /**
     * @throws PDOException
     */
    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    /**
     * @throws PDOException
     */
    private function seedTestData(): void
    {
        $owner = $this->ownerEmail(self::BASE);

        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner, self::JOB_PASSWORD);
        $this->seedSegment(self::BASE);
        // STATUS_NEW so copy() promotes it to DRAFT and counts it.
        $this->seedSegmentTranslation(self::BASE, 'NEW', 'Hello world');

        // getByChunkId joins files_job; link file <-> job.
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO files_job (id_job, id_file) VALUES ("
            . $this->jobId(self::BASE) . ", " . $this->fileId(self::BASE) . ")"
        );
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
     * @throws ReflectionException
     */
    private function seedChunk(): void
    {
        $chunk = (new JobDao(obtainTestDatabase()))->getByIdAndPassword($this->jobId(self::BASE), self::JOB_PASSWORD);
        $chunk->setSourcePage(SourcePages::SOURCE_PAGE_TRANSLATE);
        $this->setProp('chunk', $chunk);
    }

    /**
     * @param array<string, string> $params
     *
     * @throws ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $serverParams       = ['REQUEST_URI' => '/api/app/copyAllSource2Target', 'REQUEST_METHOD' => 'POST'];
        $this->requestStub  = new Request($params, [], [], $serverParams);
        $this->setProp('request', $this->requestStub);
    }

    /**
     * @throws PDOException
     */
    private function cleanFilesJob(): void
    {
        $this->seedConnection()->exec(
            "DELETE FROM files_job WHERE id_job = " . $this->jobId(self::BASE)
        );
        $this->seedConnection()->exec(
            "DELETE FROM project_metadata WHERE id_project = " . $this->projectId(self::BASE)
        );
        // The shared fragment cleaner removes only the single reserved segment id. The
        // multi-segment characterisation below seeds extras against the same reserved file, so
        // they are cleaned by file - still only rows this block owns.
        $this->seedConnection()->exec(
            "DELETE FROM segment_translation_events WHERE id_job = " . $this->jobId(self::BASE)
        );
        $this->seedConnection()->exec(
            "DELETE FROM segment_translations WHERE id_job = " . $this->jobId(self::BASE)
        );
        $this->seedConnection()->exec(
            "DELETE FROM segments WHERE id_file = " . $this->fileId(self::BASE)
        );
    }

    /**
     * @throws PDOException
     */
    private function cleanFragments(int $base): void
    {
        $this->cleanFilesJob();
        $this->cleanReservedFragments($base);
    }

    /**
     * Enable the feature via the DAO so its metadata cache is busted (a raw
     * INSERT would leave a stale cached read from an earlier in-process test).
     *
     * @throws ReflectionException
     * @throws PDOException
     */
    private function enableTranslationVersionsFeature(): void
    {
        (new MetadataDao(obtainTestDatabase()))->set($this->projectId(self::BASE), 'features', 'translation_versions');
    }


    // ─── multi-segment characterisation helpers ───

    /**
     * Seed extra segments against the reserved file and widen the job's segment window so
     * SegmentDao::getByChunkId() returns them.
     *
     * getByChunkId() selects `segments.id BETWEEN jobs.job_first_segment AND jobs.job_last_segment`,
     * and seedJob() sets both bounds to the single reserved segment id, so without widening the
     * window every extra segment is invisible and a multi-segment test would silently assert on one
     * row.
     *
     * @param list<array{source: string, status: string|null, translation?: string}> $specs
     *        A null status seeds the segment with NO segment_translations row at all.
     *
     * @return list<int> the seeded segment ids, in the order given
     *
     * @throws PDOException
     */
    private function seedExtraSegments(array $specs): array
    {
        $conn   = $this->seedConnection();
        $fileId = $this->fileId(self::BASE);
        $jobId  = $this->jobId(self::BASE);
        $first  = $this->segmentId(self::BASE);

        $ids = [];
        foreach ($specs as $offset => $spec) {
            $id     = $first + 1 + $offset;
            $ids[]  = $id;
            $source = $conn->quote($spec['source']);
            $hash   = 'ctrltest_hash_' . self::BASE . '_x' . $offset;

            $conn->exec(
                "INSERT IGNORE INTO segments (id, id_file, internal_id, segment, segment_hash, raw_word_count, show_in_cattool) "
                . "VALUES ($id, $fileId, '" . ($offset + 2) . "', $source, '$hash', 2, 1)"
            );

            if ($spec['status'] !== null) {
                $translation = $conn->quote($spec['translation'] ?? '');
                $conn->exec(
                    "INSERT IGNORE INTO segment_translations (id_segment, id_job, segment_hash, translation, status, version_number, translation_date) "
                    . "VALUES ($id, $jobId, '$hash', $translation, '{$spec['status']}', 0, '2020-01-01 00:00:00')"
                );
            }
        }

        $conn->exec("UPDATE jobs SET job_last_segment = " . (int)max($ids) . " WHERE id = $jobId");

        return $ids;
    }

    /**
     * Every translation row of the reserved job, keyed by segment id.
     *
     * @return array<int, array{translation: string, status: string, translation_date: string}>
     *
     * @throws PDOException
     */
    private function translationRows(): array
    {
        $stmt = $this->seedConnection()->query(
            "SELECT id_segment, translation, status, translation_date FROM segment_translations "
            . "WHERE id_job = " . $this->jobId(self::BASE)
        );

        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $out[(int)$row['id_segment']] = [
                'translation'      => (string)$row['translation'],
                'status'           => (string)$row['status'],
                'translation_date' => (string)$row['translation_date'],
            ];
        }

        return $out;
    }

    /**
     * @return list<int> segment ids that have an event row, ascending
     *
     * @throws PDOException
     */
    private function eventSegmentIds(): array
    {
        $stmt = $this->seedConnection()->query(
            "SELECT id_segment FROM segment_translation_events WHERE id_job = "
            . $this->jobId(self::BASE) . " ORDER BY id_segment"
        );

        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    // ─── copy() public action ───

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function copy_promotes_new_segment_and_reports_modified_count(): void
    {
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('code', $data);
                $this->assertArrayHasKey('segments_modified', $data);
                $this->assertSame(1, $data['code']);
                $this->assertSame(1, $data['segments_modified']);
                return true;
            }));

        $this->controller->copy();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws PDOException
     */
    #[Test]
    public function copy_skips_already_translated_segments_and_reports_zero(): void
    {
        // Flip the seeded translation away from STATUS_NEW so copy() skips it.
        $this->seedConnection()->exec(
            "UPDATE segment_translations SET status = 'TRANSLATED' WHERE id_job = "
            . $this->jobId(self::BASE)
        );

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame(1, $data['code']);
                $this->assertSame(0, $data['segments_modified']);
                return true;
            }));

        $this->controller->copy();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function copy_creates_translation_event_when_versions_feature_enabled(): void
    {
        $this->enableTranslationVersionsFeature();

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame(1, $data['code']);
                $this->assertSame(1, $data['segments_modified']);
                return true;
            }));

        $this->controller->copy();
    }

    // ─── registerValidators ───

    /**
     * @throws ReflectionException
     * @throws PHPUnitException
     */
    #[Test]
    public function registerValidators_appends_login_and_chunk_password_validators(): void
    {
        $controller = $this->reflector->newInstanceWithoutConstructor();

        $this->reflector->getProperty('request')->setValue($controller, new Request());

        $this->reflector->getMethod('registerValidators')->invoke($controller);

        /** @var array<int, object> $validators */
        $validators = $this->reflector->getProperty('validators')->getValue($controller);

        $this->assertCount(2, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
        $this->assertInstanceOf(ChunkPasswordValidator::class, $validators[1]);
    }

    /**
     * The ChunkPasswordValidator onSuccess callback rejects a chunk opened with a REVIEW password:
     * copying source→target is not allowed during the revision phase.
     *
     * @throws ReflectionException
     */
    #[Test]
    public function registerValidators_onSuccess_rejects_review_chunk(): void
    {
        $chunkValidator = $this->buildChunkPasswordValidatorWithChunk($isReview = true);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('The source cannot be fully copied to the target while in the revision phase.');

        $this->executeValidatorCallbacks($chunkValidator);
    }

    /**
     * For a translate-password chunk the same callback stores the chunk on the controller and does
     * not throw.
     *
     * @throws ReflectionException
     */
    #[Test]
    public function registerValidators_onSuccess_stores_chunk_for_translate_password(): void
    {
        $chunkValidator = $this->buildChunkPasswordValidatorWithChunk($isReview = false);

        $this->executeValidatorCallbacks($chunkValidator);

        $chunk = $this->reflector->getProperty('chunk')->getValue($this->registeredController);
        $this->assertInstanceOf(JobStruct::class, $chunk);
        $this->assertFalse($chunk->isReview());
    }

    private ?CopyAllSourceToTargetController $registeredController = null;

    /**
     * Runs the real registerValidators() on a fresh controller and returns its ChunkPasswordValidator
     * with a stubbed chunk (review or translate), so the onSuccess closure can be exercised in isolation.
     *
     * @throws ReflectionException
     */
    private function buildChunkPasswordValidatorWithChunk(bool $isReview): ChunkPasswordValidator
    {
        $controller = $this->reflector->newInstanceWithoutConstructor();
        $this->reflector->getProperty('request')->setValue($controller, new Request());
        $this->reflector->getMethod('registerValidators')->invoke($controller);
        $this->registeredController = $controller;

        /** @var array<int, object> $validators */
        $validators     = $this->reflector->getProperty('validators')->getValue($controller);
        $chunkValidator = $validators[1];
        $this->assertInstanceOf(ChunkPasswordValidator::class, $chunkValidator);

        $chunk           = new JobStruct();
        $chunk->id       = $this->jobId(self::BASE);
        $chunk->password = self::JOB_PASSWORD;
        $chunk->setIsReview($isReview);

        (new ReflectionClass(ChunkPasswordValidator::class))
            ->getProperty('chunk')
            ->setValue($chunkValidator, $chunk);

        return $chunkValidator;
    }

    /**
     * @throws ReflectionException
     */
    private function executeValidatorCallbacks(ChunkPasswordValidator $validator): void
    {
        (new ReflectionClass(\Controller\API\Commons\Validators\Base::class))
            ->getMethod('_executeCallbacks')
            ->invoke($validator);
    }

    // ─── multi-segment characterisation (pins behaviour before the batching change) ───

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws PDOException
     */
    #[Test]
    public function copy_promotes_every_new_segment_and_writes_each_segments_own_source(): void
    {
        // Distinct source text per segment: a batched rewrite that reused one value, or that paired
        // a segment with the wrong translation row, would still produce the right *count*.
        $ids = $this->seedExtraSegments([
            ['source' => 'Alpha source', 'status' => 'NEW', 'translation' => ''],
            ['source' => 'Beta source', 'status' => 'NEW', 'translation' => ''],
            ['source' => 'Gamma source', 'status' => 'NEW', 'translation' => ''],
        ]);

        $this->responseMock->method('json');
        $this->controller->copy();

        $rows = $this->translationRows();

        self::assertSame('Alpha source', $rows[$ids[0]]['translation']);
        self::assertSame('Beta source', $rows[$ids[1]]['translation']);
        self::assertSame('Gamma source', $rows[$ids[2]]['translation']);

        foreach ($ids as $id) {
            self::assertSame('DRAFT', $rows[$id]['status'], "segment $id should be DRAFT");
        }
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws PDOException
     */
    #[Test]
    public function copy_reports_the_number_of_segments_it_promoted(): void
    {
        $this->seedExtraSegments([
            ['source' => 'One', 'status' => 'NEW', 'translation' => ''],
            ['source' => 'Two', 'status' => 'NEW', 'translation' => ''],
        ]);

        // 2 extras + the segment seeded by setUp(), all NEW.
        $seen = null;
        $this->responseMock->method('json')->willReturnCallback(
            function (array $data) use (&$seen): Response {
                $seen = $data;

                return $this->responseMock;
            }
        );

        $this->controller->copy();

        self::assertSame(1, $seen['code']);
        self::assertSame(3, $seen['segments_modified']);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws PDOException
     */
    #[Test]
    public function copy_touches_only_new_segments_and_leaves_the_others_byte_identical(): void
    {
        $ids = $this->seedExtraSegments([
            ['source' => 'Draft source', 'status' => 'DRAFT', 'translation' => 'existing draft'],
            ['source' => 'Translated source', 'status' => 'TRANSLATED', 'translation' => 'existing translated'],
            ['source' => 'Approved source', 'status' => 'APPROVED', 'translation' => 'existing approved'],
            ['source' => 'New source', 'status' => 'NEW', 'translation' => ''],
        ]);

        $before = $this->translationRows();

        $this->responseMock->method('json');
        $this->controller->copy();

        $after = $this->translationRows();

        foreach ([0, 1, 2] as $untouched) {
            self::assertSame(
                $before[$ids[$untouched]],
                $after[$ids[$untouched]],
                "segment {$ids[$untouched]} must be untouched, including translation_date"
            );
        }

        self::assertSame('New source', $after[$ids[3]]['translation']);
        self::assertSame('DRAFT', $after[$ids[3]]['status']);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws PDOException
     */
    #[Test]
    public function copy_does_not_create_a_translation_row_for_a_segment_that_has_none(): void
    {
        // findBySegmentAndJob() returns empty and the loop `continue`s. A batched rewrite that
        // built its update set from the segment list rather than from existing translation rows
        // would insert here.
        $ids = $this->seedExtraSegments([
            ['source' => 'Orphan source', 'status' => null],
        ]);

        $this->responseMock->method('json');
        $this->controller->copy();

        self::assertArrayNotHasKey($ids[0], $this->translationRows());
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws PDOException
     */
    #[Test]
    public function copy_moves_translation_date_forward_only_for_promoted_segments(): void
    {
        $ids = $this->seedExtraSegments([
            ['source' => 'Fresh', 'status' => 'NEW', 'translation' => ''],
            ['source' => 'Stale', 'status' => 'TRANSLATED', 'translation' => 'left alone'],
        ]);

        $this->responseMock->method('json');
        $this->controller->copy();

        $rows = $this->translationRows();

        self::assertGreaterThan('2020-01-01 00:00:00', $rows[$ids[0]]['translation_date']);
        self::assertSame('2020-01-01 00:00:00', $rows[$ids[1]]['translation_date']);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws PDOException
     */
    #[Test]
    public function copy_records_one_event_per_promoted_segment_when_the_feature_is_enabled(): void
    {
        $this->enableTranslationVersionsFeature();

        $ids = $this->seedExtraSegments([
            ['source' => 'Ev one', 'status' => 'NEW', 'translation' => ''],
            ['source' => 'Ev two', 'status' => 'NEW', 'translation' => ''],
            ['source' => 'Skipped', 'status' => 'TRANSLATED', 'translation' => 'nope'],
        ]);

        $this->responseMock->method('json');
        $this->controller->copy();

        $expected = [$this->segmentId(self::BASE), $ids[0], $ids[1]];
        sort($expected);

        self::assertSame($expected, $this->eventSegmentIds());
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws PDOException
     */
    #[Test]
    public function copy_records_no_events_when_the_feature_is_disabled(): void
    {
        $this->seedExtraSegments([
            ['source' => 'No event', 'status' => 'NEW', 'translation' => ''],
        ]);

        $this->responseMock->method('json');
        $this->controller->copy();

        self::assertSame([], $this->eventSegmentIds());
    }

}
