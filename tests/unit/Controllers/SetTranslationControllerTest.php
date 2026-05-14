<?php

namespace unit\Controllers;

use Controller\API\App\SetTranslationController;
use Exception;
use InvalidArgumentException;
use Klein\Request;
use Matecat\SubFiltering\MateCatFilter;
use Model\Analysis\Constants\InternalMatchesConstants;
use Model\DataAccess\Database;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\FeaturesBase\FeatureSet;
use Model\FeaturesBase\Hook\Event\Run\PostAddSegmentTranslationEvent;
use Model\FeaturesBase\Hook\Event\Run\SetTranslationCommittedEvent;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\Projects\ProjectStruct;
use Model\Segments\SegmentStruct;
use Model\Translations\SegmentTranslationStruct;
use PHPUnit\Framework\Attributes\Test;
use Predis\Client;
use TestHelpers\AbstractTest;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Utils\Constants\EngineConstants;
use Utils\Constants\TranslationStatus;
use Utils\LQA\QA;
use Utils\Logger\LoggerFactory;
use Utils\Registry\AppConfig;

class LocalFakeRedisClient extends Client
{
    private array $hashStore = [];

    public function __construct()
    {
    }

    public function __call($commandID, $arguments)
    {
        return match (strtolower($commandID)) {
            'hget' => $this->hashStore[$arguments[0]][$arguments[1]] ?? null,
            'hset' => $this->doHset($arguments[0], $arguments[1], $arguments[2]),
            'expire' => true,
            'del' => 1,
            default => null,
        };
    }

    private function doHset($key, $field, $value): int
    {
        $this->hashStore[$key][$field] = $value;

        return 1;
    }
}

class TestableSetTranslationController extends SetTranslationController
{
    private SegmentTranslationStruct $stubbedOldTranslation;

    public function setOldTranslation(SegmentTranslationStruct $old): void
    {
        $this->stubbedOldTranslation = $old;
    }

    protected function getOldTranslation(): SegmentTranslationStruct
    {
        return $this->stubbedOldTranslation;
    }
}

/**
 * Structural regression guard + unit tests for SetTranslationController::translate()
 *
 * Strategy B+C:
 * - Task 1: Lock the current call sequence via source-code assertions
 * - Tasks 2-5: Unit-test each extracted private method
 * - Task 6: Update the guard to match the refactored orchestrator
 */
class SetTranslationControllerTest extends AbstractTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Database::obtain()->begin();
    }

    protected function tearDown(): void
    {
        $conn = Database::obtain()->getConnection();
        if ($conn->inTransaction()) {
            Database::obtain()->rollback();
        }

        parent::tearDown();
    }

    /**
     * @throws ReflectionException
     */
    private function createControllerWithoutConstructor(): SetTranslationController
    {
        $reflection = new ReflectionClass(SetTranslationController::class);

        return $reflection->newInstanceWithoutConstructor();
    }

    /**
     * @throws ReflectionException
     */
    private function setProperty(object $object, mixed $value): void
    {
        $ref = new ReflectionProperty($object, 'data');
        $ref->setValue($object, $value);
    }

    /**
     * @throws ReflectionException
     */
    private function getAccessibleMethod(string $methodName): ReflectionMethod
    {
        $reflection = new ReflectionClass(SetTranslationController::class);

        return $reflection->getMethod($methodName);
    }

    /**
     * @throws ReflectionException
     */
    private function setNamedProperty(object $object, string $propertyName, mixed $value): void
    {
        $ref = new ReflectionProperty($object, $propertyName);
        $ref->setValue($object, $value);
    }

    private function seedMinimalProjectJobAndSegment(
        int $projectId = 881001,
        int $jobId = 881002,
        string $jobPassword = 'pw881002',
        int $segmentId = 881003,
        int $fileId = 881004,
        string $segmentText = 'Hello world',
        int $rawWordCount = 2
    ): void {
        $conn = Database::obtain()->getConnection();

        $conn->exec("INSERT IGNORE INTO projects (id, id_customer, password, name, create_date, status_analysis)
                     VALUES ({$projectId}, 'test@example.org', 'projectpw', 'SetTranslationTestProject', NOW(), 'DONE')");
        $conn->exec("INSERT IGNORE INTO files (id, id_project, filename, source_language, mime_type)
                     VALUES ({$fileId}, {$projectId}, 'set-translation-test.xliff', 'en-US', 'application/xliff+xml')");
        $conn->exec("INSERT IGNORE INTO jobs (id, password, id_project, source, target, job_first_segment, job_last_segment, owner, tm_keys, create_date, disabled, status)
                     VALUES ({$jobId}, '{$jobPassword}', {$projectId}, 'en-US', 'it-IT', {$segmentId}, {$segmentId}, 'test@example.org', '[]', NOW(), 0, 'active')");
        $conn->exec("INSERT IGNORE INTO segments (id, id_file, internal_id, segment, segment_hash, raw_word_count)
                     VALUES ({$segmentId}, {$fileId}, '1', '" . addslashes($segmentText) . "', 'hash_{$segmentId}', {$rawWordCount})");
    }

    /**
     * @throws ReflectionException
     */
    private function injectFakeCacheClient(SetTranslationController $controller, LocalFakeRedisClient $client): void
    {
        $ref = new ReflectionClass($controller);
        $prop = $ref->getProperty('cache_con');
        $prop->setValue(null, $client);
    }

    // ──────────────────────────────────────────────────────────────
    // SECTION 1: Structural guards (existing)
    // ──────────────────────────────────────────────────────────────

    /**
     * Structural guard: asserts that translate() delegates ALL phases to extracted methods
     * in a defined order. This is the FINAL form — all phases are now delegated.
     *
     * Call order:
     * 1. prepareTranslation()    — Phase 1-3
     * 2. $db->begin()            — Transaction start
     * 3. buildNewTranslation()   — Phase 4-6
     * 4. persistTranslation()    — Phase 7-15
     * 5. $db->commit()           — Transaction commit
     * 6. buildResult()           — Phase 16-18
     * 7. finalizeTranslation()   — Phase 19-20
     */
    #[Test]
    public function translateCallsInternalMethodsInExpectedOrder(): void
    {
        $reflection = new ReflectionClass(SetTranslationController::class);
        $method = $reflection->getMethod('translate');
        $source = $this->getMethodSource($method);

        self::assertMethodCallOrder($source);
    }

    #[Test]
    public function prepareTranslationMethodExists(): void
    {
        $reflection = new ReflectionClass(SetTranslationController::class);

        self::assertTrue(
            $reflection->hasMethod('prepareTranslation'),
            'SetTranslationController must have a prepareTranslation() method'
        );

        $method = $reflection->getMethod('prepareTranslation');
        self::assertTrue($method->isPrivate(), 'prepareTranslation() must be private');
        self::assertSame(
            'array',
            (string) $method->getReturnType(),
            'prepareTranslation() must return array'
        );
    }

    #[Test]
    public function buildNewTranslationMethodExists(): void
    {
        $reflection = new ReflectionClass(SetTranslationController::class);

        self::assertTrue(
            $reflection->hasMethod('buildNewTranslation'),
            'SetTranslationController must have a buildNewTranslation() method'
        );

        $method = $reflection->getMethod('buildNewTranslation');
        self::assertTrue($method->isPrivate(), 'buildNewTranslation() must be private');
        self::assertSame(
            'array',
            (string) $method->getReturnType(),
            'buildNewTranslation() must return array'
        );

        $params = $method->getParameters();
        self::assertCount(3, $params, 'buildNewTranslation() must accept 3 parameters');
        self::assertSame('translation', $params[0]->getName());
        self::assertSame('errJson', $params[1]->getName());
        self::assertSame('check', $params[2]->getName());
    }

    #[Test]
    public function persistTranslationMethodExists(): void
    {
        $reflection = new ReflectionClass(SetTranslationController::class);

        self::assertTrue(
            $reflection->hasMethod('persistTranslation'),
            'SetTranslationController must have a persistTranslation() method'
        );

        $method = $reflection->getMethod('persistTranslation');
        self::assertTrue($method->isPrivate(), 'persistTranslation() must be private');
        self::assertSame(
            'array',
            (string) $method->getReturnType(),
            'persistTranslation() must return array'
        );

        $params = $method->getParameters();
        self::assertCount(5, $params, 'persistTranslation() must accept 5 parameters');
        self::assertSame('newTranslation', $params[0]->getName());
        self::assertSame('oldTranslation', $params[1]->getName());
        self::assertSame('translation', $params[2]->getName());
        self::assertSame('errJson', $params[3]->getName());
        self::assertSame('check', $params[4]->getName());
    }

    #[Test]
    public function buildResultMethodExists(): void
    {
        $reflection = new ReflectionClass(SetTranslationController::class);

        self::assertTrue(
            $reflection->hasMethod('buildResult'),
            'SetTranslationController must have a buildResult() method'
        );

        $method = $reflection->getMethod('buildResult');
        self::assertTrue($method->isPrivate(), 'buildResult() must be private');
        self::assertSame(
            'array',
            (string) $method->getReturnType(),
            'buildResult() must return array'
        );

        $params = $method->getParameters();
        self::assertCount(4, $params, 'buildResult() must accept 4 parameters');
        self::assertSame('newTranslation', $params[0]->getName());
        self::assertSame('oldTranslation', $params[1]->getName());
        self::assertSame('propagationTotal', $params[2]->getName());
        self::assertSame('check', $params[3]->getName());
    }

    #[Test]
    public function finalizeTranslationMethodExists(): void
    {
        $reflection = new ReflectionClass(SetTranslationController::class);

        self::assertTrue(
            $reflection->hasMethod('finalizeTranslation'),
            'SetTranslationController must have a finalizeTranslation() method'
        );

        $method = $reflection->getMethod('finalizeTranslation');
        self::assertTrue($method->isPrivate(), 'finalizeTranslation() must be private');
        self::assertSame(
            'void',
            (string) $method->getReturnType(),
            'finalizeTranslation() must return void'
        );

        $params = $method->getParameters();
        self::assertCount(4, $params, 'finalizeTranslation() must accept 4 parameters');
        self::assertSame('newTranslation', $params[0]->getName());
        self::assertSame('oldTranslation', $params[1]->getName());
        self::assertSame('propagationTotal', $params[2]->getName());
        self::assertSame('result', $params[3]->getName());
    }

    // ──────────────────────────────────────────────────────────────
    // SECTION 2: canUpdateSuggestion() — pure logic unit tests
    // ──────────────────────────────────────────────────────────────

    /**
     * canUpdateSuggestion() returns true only when:
     * 1. status is NEW, DRAFT, or TRANSLATED
     * 2. suggestion has raw_translation, match, AND created_by
     * @throws ReflectionException
     */
    #[Test]
    public function canUpdateSuggestionReturnsTrueForTranslatedStatusWithFullSuggestion(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $method = $this->getAccessibleMethod('canUpdateSuggestion');

        $translation = new SegmentTranslationStruct();
        $translation->status = TranslationStatus::STATUS_TRANSLATED;

        $suggestion = new ShapelessConcreteStruct([
            'raw_translation' => 'Traduzione di prova',
            'match'           => '85%',
            'created_by'      => 'MT',
        ]);

        self::assertTrue($method->invoke($controller, $translation, $suggestion));
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function canUpdateSuggestionReturnsTrueForDraftStatusWithFullSuggestion(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $method = $this->getAccessibleMethod('canUpdateSuggestion');

        $translation = new SegmentTranslationStruct();
        $translation->status = TranslationStatus::STATUS_DRAFT;

        $suggestion = new ShapelessConcreteStruct([
            'raw_translation' => 'Bozza',
            'match'           => '100%',
            'created_by'      => 'TM',
        ]);

        self::assertTrue($method->invoke($controller, $translation, $suggestion));
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function canUpdateSuggestionReturnsTrueForNewStatusWithFullSuggestion(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $method = $this->getAccessibleMethod('canUpdateSuggestion');

        $translation = new SegmentTranslationStruct();
        $translation->status = TranslationStatus::STATUS_NEW;

        $suggestion = new ShapelessConcreteStruct([
            'raw_translation' => 'Nuovo',
            'match'           => '75%',
            'created_by'      => 'TM',
        ]);

        self::assertTrue($method->invoke($controller, $translation, $suggestion));
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function canUpdateSuggestionReturnsFalseForApprovedStatus(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $method = $this->getAccessibleMethod('canUpdateSuggestion');

        $translation = new SegmentTranslationStruct();
        $translation->status = TranslationStatus::STATUS_APPROVED;

        $suggestion = new ShapelessConcreteStruct([
            'raw_translation' => 'Approvata',
            'match'           => '100%',
            'created_by'      => 'TM',
        ]);

        self::assertFalse(
            $method->invoke($controller, $translation, $suggestion),
            'APPROVED status must block suggestion updates'
        );
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function canUpdateSuggestionReturnsFalseForApproved2Status(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $method = $this->getAccessibleMethod('canUpdateSuggestion');

        $translation = new SegmentTranslationStruct();
        $translation->status = TranslationStatus::STATUS_APPROVED2;

        $suggestion = new ShapelessConcreteStruct([
            'raw_translation' => 'Approvata L2',
            'match'           => '100%',
            'created_by'      => 'TM',
        ]);

        self::assertFalse(
            $method->invoke($controller, $translation, $suggestion),
            'APPROVED2 status must block suggestion updates'
        );
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function canUpdateSuggestionReturnsFalseForRejectedStatus(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $method = $this->getAccessibleMethod('canUpdateSuggestion');

        $translation = new SegmentTranslationStruct();
        $translation->status = TranslationStatus::STATUS_REJECTED;

        $suggestion = new ShapelessConcreteStruct([
            'raw_translation' => 'Rifiutata',
            'match'           => '100%',
            'created_by'      => 'TM',
        ]);

        self::assertFalse(
            $method->invoke($controller, $translation, $suggestion),
            'REJECTED status must block suggestion updates'
        );
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function canUpdateSuggestionReturnsFalseWhenRawTranslationMissing(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $method = $this->getAccessibleMethod('canUpdateSuggestion');

        $translation = new SegmentTranslationStruct();
        $translation->status = TranslationStatus::STATUS_TRANSLATED;

        $suggestion = new ShapelessConcreteStruct([
            'match'      => '85%',
            'created_by' => 'MT',
        ]);

        self::assertFalse(
            $method->invoke($controller, $translation, $suggestion),
            'Suggestion without raw_translation must not be updatable'
        );
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function canUpdateSuggestionReturnsFalseWhenMatchMissing(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $method = $this->getAccessibleMethod('canUpdateSuggestion');

        $translation = new SegmentTranslationStruct();
        $translation->status = TranslationStatus::STATUS_TRANSLATED;

        $suggestion = new ShapelessConcreteStruct([
            'raw_translation' => 'Test',
            'created_by'      => 'MT',
        ]);

        self::assertFalse(
            $method->invoke($controller, $translation, $suggestion),
            'Suggestion without match must not be updatable'
        );
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function canUpdateSuggestionReturnsFalseWhenCreatedByMissing(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $method = $this->getAccessibleMethod('canUpdateSuggestion');

        $translation = new SegmentTranslationStruct();
        $translation->status = TranslationStatus::STATUS_TRANSLATED;

        $suggestion = new ShapelessConcreteStruct([
            'raw_translation' => 'Test',
            'match'           => '85%',
        ]);

        self::assertFalse(
            $method->invoke($controller, $translation, $suggestion),
            'Suggestion without created_by must not be updatable'
        );
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function canUpdateSuggestionReturnsFalseForEmptySuggestion(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $method = $this->getAccessibleMethod('canUpdateSuggestion');

        $translation = new SegmentTranslationStruct();
        $translation->status = TranslationStatus::STATUS_TRANSLATED;

        $suggestion = new ShapelessConcreteStruct([]);

        self::assertFalse(
            $method->invoke($controller, $translation, $suggestion),
            'Empty suggestion must not be updatable'
        );
    }

    // ──────────────────────────────────────────────────────────────
    // SECTION 3: isSplittedSegment() — pure logic unit tests
    // ──────────────────────────────────────────────────────────────

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function isSplittedSegmentReturnsTrueWhenBothSplitFieldsPresent(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $method = $this->getAccessibleMethod('isSplittedSegment');

        $this->setProperty($controller, [
            'split_statuses' => [TranslationStatus::STATUS_TRANSLATED],
            'split_num' => '1',
        ]);

        self::assertTrue($method->invoke($controller));
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function isSplittedSegmentReturnsFalseWhenSplitStatusesEmpty(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $method = $this->getAccessibleMethod('isSplittedSegment');

        $this->setProperty($controller, [
            'split_statuses' => [],
            'split_num' => '1',
        ]);

        self::assertFalse($method->invoke($controller));
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function isSplittedSegmentReturnsFalseWhenSplitNumNull(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $method = $this->getAccessibleMethod('isSplittedSegment');

        $this->setProperty($controller, [
            'split_statuses' => [TranslationStatus::STATUS_TRANSLATED],
            'split_num' => null,
        ]);

        self::assertFalse($method->invoke($controller));
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function isSplittedSegmentReturnsFalseWhenBothFieldsEmpty(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $method = $this->getAccessibleMethod('isSplittedSegment');

        $this->setProperty($controller, [
            'split_statuses' => [],
            'split_num' => null,
        ]);

        self::assertFalse($method->invoke($controller));
    }

    // ──────────────────────────────────────────────────────────────
    // SECTION 4: setStatusForSplittedSegment() — pure logic unit tests
    // ──────────────────────────────────────────────────────────────

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function setStatusForSplittedSegmentUsesUniformStatusWhenAllChunksMatch(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $method = $this->getAccessibleMethod('setStatusForSplittedSegment');

        $this->setProperty($controller, [
            'split_statuses' => [
                TranslationStatus::STATUS_TRANSLATED,
                TranslationStatus::STATUS_TRANSLATED,
                TranslationStatus::STATUS_TRANSLATED,
            ],
            'status' => TranslationStatus::STATUS_DRAFT,
        ]);

        $method->invoke($controller);

        $dataProp = new ReflectionProperty($controller, 'data');
        $data = $dataProp->getValue($controller);

        self::assertSame(
            TranslationStatus::STATUS_TRANSLATED,
            $data['status'],
            'Uniform split statuses must set the segment status to that status'
        );
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function setStatusForSplittedSegmentSetsDraftWhenChunksHaveMixedStatuses(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $method = $this->getAccessibleMethod('setStatusForSplittedSegment');

        $this->setProperty($controller, [
            'split_statuses' => [
                TranslationStatus::STATUS_TRANSLATED,
                TranslationStatus::STATUS_DRAFT,
            ],
            'status' => TranslationStatus::STATUS_APPROVED,
        ]);

        $method->invoke($controller);

        $dataProp = new ReflectionProperty($controller, 'data');
        $data = $dataProp->getValue($controller);

        self::assertSame(
            TranslationStatus::STATUS_DRAFT,
            $data['status'],
            'Mixed split statuses must reset to DRAFT'
        );
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function setStatusForSplittedSegmentHandlesSingleChunk(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $method = $this->getAccessibleMethod('setStatusForSplittedSegment');

        $this->setProperty($controller, [
            'split_statuses' => [TranslationStatus::STATUS_APPROVED],
            'status' => TranslationStatus::STATUS_DRAFT,
        ]);

        $method->invoke($controller);

        $dataProp = new ReflectionProperty($controller, 'data');
        $data = $dataProp->getValue($controller);

        self::assertSame(
            TranslationStatus::STATUS_APPROVED,
            $data['status'],
            'Single chunk must set status to that chunk\'s status'
        );
    }

    // ──────────────────────────────────────────────────────────────
    // SECTION 5: buildNewTranslation() — struct assembly + suggestion logic
    // ──────────────────────────────────────────────────────────────

    /**
     * @throws ReflectionException
     */
    private function createTestableController(SegmentTranslationStruct $oldTranslation): TestableSetTranslationController
    {
        $reflection = new ReflectionClass(TestableSetTranslationController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $controller->setOldTranslation($oldTranslation);

        return $controller;
    }

    private function makeDefaultOldTranslation(): SegmentTranslationStruct
    {
        $old = new SegmentTranslationStruct();
        $old->suggestion = 'Old suggestion';
        $old->suggestion_source = EngineConstants::TM;
        $old->suggestion_match = '75';
        $old->suggestions_array = '[]';
        $old->suggestion_position = 1;

        return $old;
    }

    private function makeDefaultSegment(): SegmentStruct
    {
        $segment = new SegmentStruct();
        $segment->segment_hash = 'abc123hash';

        return $segment;
    }

    private function makeQAStub(bool $hasWarnings = false): QA
    {
        $qa = $this->createStub(QA::class);
        $qa->method('thereAreWarnings')->willReturn($hasWarnings);

        return $qa;
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function buildNewTranslationAssemblesStructWithNoChosenSuggestion(): void
    {
        $old = $this->makeDefaultOldTranslation();
        $controller = $this->createTestableController($old);

        $this->setProperty($controller, [
            'suggestion_array' => null,
            'chosen_suggestion_index' => null,
            'id_segment' => '42',
            'id_job' => '100',
            'status' => TranslationStatus::STATUS_TRANSLATED,
            'segment' => $this->makeDefaultSegment(),
            'time_to_edit' => 5000,
        ]);

        $method = (new ReflectionClass(SetTranslationController::class))->getMethod('buildNewTranslation');
        $result = $method->invoke($controller, 'Hello world', '', $this->makeQAStub());

        self::assertArrayHasKey('new', $result);
        self::assertArrayHasKey('old', $result);
        self::assertInstanceOf(SegmentTranslationStruct::class, $result['new']);
        self::assertSame($old, $result['old']);

        $new = $result['new'];
        self::assertSame(42, $new->id_segment);
        self::assertSame(100, $new->id_job);
        self::assertSame(TranslationStatus::STATUS_TRANSLATED, $new->status);
        self::assertSame('abc123hash', $new->segment_hash);
        self::assertSame('Hello world', $new->translation);
        self::assertSame(5000, $new->time_to_edit);
        self::assertFalse($new->warning);

        self::assertSame($old->suggestions_array, $new->suggestions_array, 'No chosen index → keep old suggestions_array');
        self::assertSame($old->suggestion_position, $new->suggestion_position, 'No chosen index → keep old suggestion_position');
        self::assertSame('Old suggestion', $new->suggestion, 'Suggestion must come from old translation');
        self::assertSame(EngineConstants::TM, $new->suggestion_source);
        self::assertSame('75', $new->suggestion_match);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function buildNewTranslationWithChosenIndexOverridesSuggestionArrayAndPosition(): void
    {
        $old = $this->makeDefaultOldTranslation();
        $controller = $this->createTestableController($old);

        $suggestionArray = json_encode([
            ['raw_translation' => 'Suggestion 1', 'match' => '85%', 'created_by' => 'TM'],
        ]);

        $this->setProperty($controller, [
            'suggestion_array' => $suggestionArray,
            'chosen_suggestion_index' => 1,
            'id_segment' => '42',
            'id_job' => '100',
            'status' => TranslationStatus::STATUS_TRANSLATED,
            'segment' => $this->makeDefaultSegment(),
            'time_to_edit' => 3000,
        ]);

        $method = (new ReflectionClass(SetTranslationController::class))->getMethod('buildNewTranslation');
        $result = $method->invoke($controller, 'Traduzione', '', $this->makeQAStub());

        $new = $result['new'];
        self::assertSame($suggestionArray, $new->suggestions_array, 'Chosen index → use client suggestion_array');
        self::assertSame(1, $new->suggestion_position, 'Chosen index → use chosen_suggestion_index');
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function buildNewTranslationUpdatesSuggestionForMTMatch(): void
    {
        $old = $this->makeDefaultOldTranslation();
        $controller = $this->createTestableController($old);

        $suggestionArray = json_encode([
            ['raw_translation' => 'MT translation', 'match' => EngineConstants::MT, 'created_by' => 'DeepL'],
        ]);

        $project = $this->createStub(ProjectStruct::class);
        $project->method('getMetadataValue')->willReturn('90');

        $this->setProperty($controller, [
            'suggestion_array' => $suggestionArray,
            'chosen_suggestion_index' => 1,
            'id_segment' => '42',
            'id_job' => '100',
            'status' => TranslationStatus::STATUS_TRANSLATED,
            'segment' => $this->makeDefaultSegment(),
            'time_to_edit' => 2000,
            'project' => $project,
        ]);

        $method = (new ReflectionClass(SetTranslationController::class))->getMethod('buildNewTranslation');
        $result = $method->invoke($controller, 'Traduzione MT', '', $this->makeQAStub());

        $new = $result['new'];
        self::assertSame('MT translation', $new->suggestion, 'MT match → suggestion updated from client');
        self::assertSame('90', $new->suggestion_match, 'MT match → quality from project metadata');
        self::assertSame(EngineConstants::MT, $new->suggestion_source);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function buildNewTranslationUpdatesSuggestionForNoMatch(): void
    {
        $old = $this->makeDefaultOldTranslation();
        $controller = $this->createTestableController($old);

        $suggestionArray = json_encode([
            ['raw_translation' => 'No match text', 'match' => InternalMatchesConstants::NO_MATCH, 'created_by' => 'NONE'],
        ]);

        $this->setProperty($controller, [
            'suggestion_array' => $suggestionArray,
            'chosen_suggestion_index' => 1,
            'id_segment' => '42',
            'id_job' => '100',
            'status' => TranslationStatus::STATUS_TRANSLATED,
            'segment' => $this->makeDefaultSegment(),
            'time_to_edit' => 1000,
        ]);

        $method = (new ReflectionClass(SetTranslationController::class))->getMethod('buildNewTranslation');
        $result = $method->invoke($controller, 'Nessuna corrispondenza', '', $this->makeQAStub());

        $new = $result['new'];
        self::assertSame(InternalMatchesConstants::NO_MATCH, $new->suggestion_source, 'NO_MATCH → suggestion_source set');
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function buildNewTranslationUpdatesSuggestionForTMMatch(): void
    {
        $old = $this->makeDefaultOldTranslation();
        $controller = $this->createTestableController($old);

        $suggestionArray = json_encode([
            ['raw_translation' => 'TM translation', 'match' => '71%', 'created_by' => 'MyMemory'],
        ]);

        $this->setProperty($controller, [
            'suggestion_array' => $suggestionArray,
            'chosen_suggestion_index' => 1,
            'id_segment' => '42',
            'id_job' => '100',
            'status' => TranslationStatus::STATUS_TRANSLATED,
            'segment' => $this->makeDefaultSegment(),
            'time_to_edit' => 1500,
        ]);

        $method = (new ReflectionClass(SetTranslationController::class))->getMethod('buildNewTranslation');
        $result = $method->invoke($controller, 'Traduzione TM', '', $this->makeQAStub());

        $new = $result['new'];
        self::assertSame('TM translation', $new->suggestion, 'TM match → suggestion updated from client');
        self::assertSame('71', $new->suggestion_match, 'TM match → cast "71%" to int 71 then string');
        self::assertSame(EngineConstants::TM, $new->suggestion_source);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function buildNewTranslationSkipsSuggestionUpdateForApprovedStatus(): void
    {
        $old = $this->makeDefaultOldTranslation();
        $controller = $this->createTestableController($old);

        $suggestionArray = json_encode([
            ['raw_translation' => 'Should not update', 'match' => '100%', 'created_by' => 'TM'],
        ]);

        $this->setProperty($controller, [
            'suggestion_array' => $suggestionArray,
            'chosen_suggestion_index' => 1,
            'id_segment' => '42',
            'id_job' => '100',
            'status' => TranslationStatus::STATUS_APPROVED,
            'segment' => $this->makeDefaultSegment(),
            'time_to_edit' => 1000,
        ]);

        $method = (new ReflectionClass(SetTranslationController::class))->getMethod('buildNewTranslation');
        $result = $method->invoke($controller, 'Approvata', '', $this->makeQAStub());

        $new = $result['new'];
        self::assertSame('Old suggestion', $new->suggestion, 'APPROVED status → canUpdateSuggestion=false → old suggestion kept');
        self::assertSame(EngineConstants::TM, $new->suggestion_source, 'APPROVED → old suggestion_source kept');
        self::assertSame('75', $new->suggestion_match, 'APPROVED → old suggestion_match kept');
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function buildNewTranslationSetsWarningFromQACheck(): void
    {
        $old = $this->makeDefaultOldTranslation();
        $controller = $this->createTestableController($old);

        $this->setProperty($controller, [
            'suggestion_array' => null,
            'chosen_suggestion_index' => null,
            'id_segment' => '42',
            'id_job' => '100',
            'status' => TranslationStatus::STATUS_TRANSLATED,
            'segment' => $this->makeDefaultSegment(),
            'time_to_edit' => 1000,
        ]);

        $method = (new ReflectionClass(SetTranslationController::class))->getMethod('buildNewTranslation');
        $result = $method->invoke($controller, 'Warning test', '', $this->makeQAStub(true));

        self::assertTrue($result['new']->warning, 'QA warnings present → warning must be true');
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function buildNewTranslationThrowsWhenSegmentIsNull(): void
    {
        $old = $this->makeDefaultOldTranslation();
        $controller = $this->createTestableController($old);

        $this->setProperty($controller, [
            'suggestion_array' => null,
            'chosen_suggestion_index' => null,
            'id_segment' => '42',
            'id_job' => '100',
            'status' => TranslationStatus::STATUS_TRANSLATED,
            'segment' => null,
            'time_to_edit' => 1000,
        ]);

        $method = (new ReflectionClass(SetTranslationController::class))->getMethod('buildNewTranslation');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Segment must not be null in buildNewTranslation');
        $method->invoke($controller, 'Should fail', '', $this->makeQAStub());
    }

    // ──────────────────────────────────────────────────────────────
    // SECTION 6: Additional private/protected methods coverage
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function updateJobPEEThrowsWhenSegmentIsNull(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $chunk = new \Model\Jobs\JobStruct();
        $chunk->total_time_to_edit = 0;
        $chunk->avg_post_editing_effort = 0;
        $chunk->target = 'it-IT';

        $this->setNamedProperty($controller, 'chunk', $chunk);
        $this->setNamedProperty($controller, 'id_job', 111111);
        $this->setNamedProperty($controller, 'password', 'pw');
        $this->setNamedProperty($controller, 'request_password', 'pw');
        $this->setNamedProperty($controller, 'segment', null);

        $method = $this->getAccessibleMethod('updateJobPEE');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Segment must not be null in updateJobPEE');
        $method->invoke(
            $controller,
            ['suggestion' => '', 'translation' => '', 'time_to_edit' => 0],
            ['translation' => '', 'time_to_edit' => 10]
        );
    }

    #[Test]
    public function updateJobPEEUpdatesOnlyTotalTimeToEditWhenSegmentsAreNotValidForEditLog(): void
    {
        $projectId = 882001;
        $jobId = 882002;
        $jobPassword = 'pw882002';
        $segmentId = 882003;
        $fileId = 882004;
        $this->seedMinimalProjectJobAndSegment($projectId, $jobId, $jobPassword, $segmentId, $fileId, 'Short', 100);

        $controller = $this->createControllerWithoutConstructor();
        $chunk = new \Model\Jobs\JobStruct();
        $chunk->total_time_to_edit = 100;
        $chunk->avg_post_editing_effort = 10;
        $chunk->target = 'it-IT';
        $this->setNamedProperty($controller, 'chunk', $chunk);

        $segment = new SegmentStruct();
        $segment->raw_word_count = 100;
        $this->setNamedProperty($controller, 'segment', $segment);
        $this->setNamedProperty($controller, 'id_job', $jobId);
        $this->setNamedProperty($controller, 'password', $jobPassword);
        $this->setNamedProperty($controller, 'request_password', $jobPassword);

        $method = $this->getAccessibleMethod('updateJobPEE');
        $method->invoke(
            $controller,
            ['suggestion' => '', 'translation' => '', 'time_to_edit' => 1],
            ['translation' => '', 'time_to_edit' => 50]
        );

        $row = Database::obtain()->getConnection()->query("SELECT total_time_to_edit FROM jobs WHERE id = {$jobId}")->fetch();
        self::assertSame('150', (string)$row['total_time_to_edit']);
    }

    #[Test]
    public function updateJobPEEDoesNotAddTimeToEditInRevisionMode(): void
    {
        $originalReferer = $_SERVER['HTTP_REFERER'] ?? null;
        $_SERVER['HTTP_REFERER'] = 'http://localhost/revise/123';

        try {
            $projectId = 883001;
            $jobId = 883002;
            $jobPassword = 'pw883002';
            $segmentId = 883003;
            $fileId = 883004;
            $this->seedMinimalProjectJobAndSegment($projectId, $jobId, $jobPassword, $segmentId, $fileId, 'Short', 100);

            $controller = $this->createControllerWithoutConstructor();
            $chunk = new \Model\Jobs\JobStruct();
            $chunk->total_time_to_edit = 200;
            $chunk->avg_post_editing_effort = 0;
            $chunk->target = 'it-IT';
            $this->setNamedProperty($controller, 'chunk', $chunk);

            $segment = new SegmentStruct();
            $segment->raw_word_count = 100;
            $this->setNamedProperty($controller, 'segment', $segment);
            $this->setNamedProperty($controller, 'id_job', $jobId);
            $this->setNamedProperty($controller, 'password', $jobPassword);
            $this->setNamedProperty($controller, 'request_password', $jobPassword);

            $method = $this->getAccessibleMethod('updateJobPEE');
            $method->invoke(
                $controller,
                ['suggestion' => '', 'translation' => '', 'time_to_edit' => 1],
                ['translation' => '', 'time_to_edit' => 50]
            );

            $row = Database::obtain()->getConnection()->query("SELECT total_time_to_edit FROM jobs WHERE id = {$jobId}")->fetch();
            self::assertSame('200', (string)$row['total_time_to_edit']);
        } finally {
            if ($originalReferer === null) {
                unset($_SERVER['HTTP_REFERER']);
            } else {
                $_SERVER['HTTP_REFERER'] = $originalReferer;
            }
        }
    }

    #[Test]
    public function updateJobPEEUpdatesAvgPeeWhenOldAndNewAreValidForEditLog(): void
    {
        $projectId = 887001;
        $jobId = 887002;
        $jobPassword = 'pw887002';
        $segmentId = 887003;
        $fileId = 887004;
        $this->seedMinimalProjectJobAndSegment($projectId, $jobId, $jobPassword, $segmentId, $fileId, 'Valid PEE', 2);

        $controller = $this->createControllerWithoutConstructor();
        $chunk = new \Model\Jobs\JobStruct();
        $chunk->total_time_to_edit = 10;
        $chunk->avg_post_editing_effort = 50;
        $chunk->target = 'it-IT';
        $this->setNamedProperty($controller, 'chunk', $chunk);

        $segment = new SegmentStruct();
        $segment->raw_word_count = 2;
        $this->setNamedProperty($controller, 'segment', $segment);
        $this->setNamedProperty($controller, 'id_job', $jobId);
        $this->setNamedProperty($controller, 'password', $jobPassword);
        $this->setNamedProperty($controller, 'request_password', $jobPassword);

        $method = $this->getAccessibleMethod('updateJobPEE');
        $method->invoke(
            $controller,
            ['suggestion' => 'abc', 'translation' => 'abc', 'time_to_edit' => 1000],
            ['translation' => 'abcd', 'time_to_edit' => 1000]
        );

        $row = Database::obtain()->getConnection()->query("SELECT avg_post_editing_effort, total_time_to_edit FROM jobs WHERE id = {$jobId}")->fetch();
        self::assertSame('1010', (string)$row['total_time_to_edit']);
        self::assertNotSame('50', (string)$row['avg_post_editing_effort']);
    }

    #[Test]
    public function updateJobPEEAddsWeightedPeeWhenOldWasInvalidAndNewBecomesValid(): void
    {
        $projectId = 888001;
        $jobId = 888002;
        $jobPassword = 'pw888002';
        $segmentId = 888003;
        $fileId = 888004;
        $this->seedMinimalProjectJobAndSegment($projectId, $jobId, $jobPassword, $segmentId, $fileId, 'Valid transition', 2);

        $controller = $this->createControllerWithoutConstructor();
        $chunk = new \Model\Jobs\JobStruct();
        $chunk->total_time_to_edit = 0;
        $chunk->avg_post_editing_effort = 100;
        $chunk->target = 'it-IT';
        $this->setNamedProperty($controller, 'chunk', $chunk);

        $segment = new SegmentStruct();
        $segment->raw_word_count = 2;
        $this->setNamedProperty($controller, 'segment', $segment);
        $this->setNamedProperty($controller, 'id_job', $jobId);
        $this->setNamedProperty($controller, 'password', $jobPassword);
        $this->setNamedProperty($controller, 'request_password', $jobPassword);

        $method = $this->getAccessibleMethod('updateJobPEE');
        $method->invoke(
            $controller,
            ['suggestion' => 'abc', 'translation' => 'abc', 'time_to_edit' => 100],
            ['translation' => 'abx', 'time_to_edit' => 900]
        );

        $row = Database::obtain()->getConnection()->query("SELECT avg_post_editing_effort FROM jobs WHERE id = {$jobId}")->fetch();
        self::assertNotSame('100', (string)$row['avg_post_editing_effort']);
    }

    #[Test]
    public function updateJobPEESubtractsWeightedPeeWhenOldWasValidAndNewBecomesInvalid(): void
    {
        $projectId = 889001;
        $jobId = 889002;
        $jobPassword = 'pw889002';
        $segmentId = 889003;
        $fileId = 889004;
        $this->seedMinimalProjectJobAndSegment($projectId, $jobId, $jobPassword, $segmentId, $fileId, 'Invalid transition', 2);

        $controller = $this->createControllerWithoutConstructor();
        $chunk = new \Model\Jobs\JobStruct();
        $chunk->total_time_to_edit = 0;
        $chunk->avg_post_editing_effort = 200;
        $chunk->target = 'it-IT';
        $this->setNamedProperty($controller, 'chunk', $chunk);

        $segment = new SegmentStruct();
        $segment->raw_word_count = 2;
        $this->setNamedProperty($controller, 'segment', $segment);
        $this->setNamedProperty($controller, 'id_job', $jobId);
        $this->setNamedProperty($controller, 'password', $jobPassword);
        $this->setNamedProperty($controller, 'request_password', $jobPassword);

        $method = $this->getAccessibleMethod('updateJobPEE');
        $method->invoke(
            $controller,
            ['suggestion' => 'abcd', 'translation' => 'abce', 'time_to_edit' => 1000],
            ['translation' => 'abcf', 'time_to_edit' => 100000]
        );

        $row = Database::obtain()->getConnection()->query("SELECT avg_post_editing_effort FROM jobs WHERE id = {$jobId}")->fetch();
        self::assertNotSame('200', (string)$row['avg_post_editing_effort']);
    }

    #[Test]
    public function buildResultReturnsExpectedResponseStructureAndWarningId(): void
    {
        $controller = $this->createControllerWithoutConstructor();

        $chunk = new \Model\Jobs\JobStruct();
        $chunk->id = 9001;
        $chunk->password = 'pw9001';
        $chunk->new_words = 1;
        $chunk->draft_words = 2;
        $chunk->translated_words = 3;
        $chunk->approved_words = 4;
        $chunk->approved2_words = 5;
        $chunk->new_raw_words = 1;
        $chunk->draft_raw_words = 2;
        $chunk->translated_raw_words = 3;
        $chunk->approved_raw_words = 4;
        $chunk->approved2_raw_words = 5;

        $this->setProperty($controller, [
            'chunk' => $chunk,
            'project' => ['status_analysis' => 'DONE'],
            'id_segment' => '42',
            'segment' => new SegmentStruct(),
            'revisionNumber' => 0,
        ]);

        $this->setNamedProperty($controller, 'filter', MateCatFilter::getInstance(new FeatureSet(), 'en-US', 'it-IT', []));
        $this->setNamedProperty($controller, 'user', new \Model\Users\UserStruct());

        $featureSet = $this->createMock(FeatureSet::class);
        $featureSet
            ->expects(self::once())
            ->method('dispatchRun')
            ->with(self::isInstanceOf(SetTranslationCommittedEvent::class));
        $this->setNamedProperty($controller, 'featureSet', $featureSet);

        $newTranslation = new SegmentTranslationStruct();
        $newTranslation->id_segment = 42;
        $newTranslation->status = TranslationStatus::STATUS_TRANSLATED;
        $newTranslation->translation = 'ciao';
        $newTranslation->translation_date = '2025-01-01 00:00:00';
        $newTranslation->version_number = 7;

        $oldTranslation = new SegmentTranslationStruct();
        $oldTranslation->status = TranslationStatus::STATUS_DRAFT;

        $qa = $this->createStub(QA::class);
        $qa->method('getWarnings')->willReturn([(object)['outcome' => 1]]);

        $method = $this->getAccessibleMethod('buildResult');
        $result = $method->invoke($controller, $newTranslation, $oldTranslation, [], $qa);

        self::assertSame(1, $result['code']);
        self::assertSame('OK', $result['data']);
        self::assertSame(1, $result['warning']['cod']);
        self::assertSame('42', (string)$result['warning']['id']);
        self::assertSame(42, $result['translation']['sid']);
        self::assertSame(TranslationStatus::STATUS_TRANSLATED, $result['translation']['status']);
        self::assertArrayHasKey(ProjectsMetadataMarshaller::WORD_COUNT_RAW->value, $result['stats']);
    }

    #[Test]
    public function buildResultSetsWarningIdToZeroWhenNoWarningOutcome(): void
    {
        $controller = $this->createControllerWithoutConstructor();

        $chunk = new \Model\Jobs\JobStruct();
        $chunk->id = 9002;
        $chunk->password = 'pw9002';
        $chunk->new_raw_words = 1;
        $chunk->draft_raw_words = 0;
        $chunk->translated_raw_words = 0;
        $chunk->approved_raw_words = 0;
        $chunk->approved2_raw_words = 0;

        $this->setProperty($controller, [
            'chunk' => $chunk,
            'project' => ['status_analysis' => 'DONE'],
            'id_segment' => '52',
            'segment' => new SegmentStruct(),
            'revisionNumber' => 0,
        ]);

        $this->setNamedProperty($controller, 'filter', MateCatFilter::getInstance(new FeatureSet(), 'en-US', 'it-IT', []));
        $this->setNamedProperty($controller, 'user', new \Model\Users\UserStruct());
        $featureSet = $this->createStub(FeatureSet::class);
        $this->setNamedProperty($controller, 'featureSet', $featureSet);

        $newTranslation = new SegmentTranslationStruct();
        $newTranslation->id_segment = 52;
        $newTranslation->status = TranslationStatus::STATUS_DRAFT;
        $newTranslation->translation = 'ciao';
        $newTranslation->translation_date = '2025-01-01 00:00:00';

        $oldTranslation = new SegmentTranslationStruct();
        $oldTranslation->status = TranslationStatus::STATUS_NEW;

        $qa = $this->createStub(QA::class);
        $qa->method('getWarnings')->willReturn([(object)['outcome' => 0]]);

        $method = $this->getAccessibleMethod('buildResult');
        $result = $method->invoke($controller, $newTranslation, $oldTranslation, [], $qa);

        self::assertSame(0, $result['warning']['id']);
    }

    #[Test]
    public function finalizeTranslationAddsPropagationAndCallsEvalSetContribution(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $this->setProperty($controller, [
            'id_job' => '1',
            'status' => TranslationStatus::STATUS_DRAFT,
            'password' => 'pw',
            'id_segment' => '1',
            'propagate' => false,
            'context_after' => '',
            'context_before' => '',
        ]);

        $new = new SegmentTranslationStruct();
        $old = new SegmentTranslationStruct();
        $propagation = ['segments_for_propagation' => ['propagated_ids' => []]];
        $result = [
            'stats' => [
                ProjectsMetadataMarshaller::WORD_COUNT_RAW->value => ['draft' => 1, 'new' => 0],
            ],
        ];

        $method = $this->getAccessibleMethod('finalizeTranslation');
        $method->invokeArgs($controller, [$new, $old, $propagation, &$result]);

        self::assertSame($propagation, $result['propagation']);
    }

    #[Test]
    public function evalSetContributionThrowsWhenSegmentIsNullForNonDraftStatuses(): void
    {
        $projectId = 890001;
        $jobId = 890002;
        $jobPassword = 'pw890002';
        $segmentId = 890003;
        $fileId = 890004;
        $this->seedMinimalProjectJobAndSegment($projectId, $jobId, $jobPassword, $segmentId, $fileId, 'Eval set', 2);

        $controller = $this->createControllerWithoutConstructor();
        $chunk = (new \Model\Jobs\JobDao())->getByIdAndPasswordOrFail($jobId, $jobPassword);
        $project = $chunk->getProject();
        $this->setNamedProperty($controller, 'chunk', $chunk);
        $this->setNamedProperty($controller, 'filter', MateCatFilter::getInstance(new FeatureSet(), 'en-US', 'it-IT', []));
        $this->setNamedProperty($controller, 'featureSet', new FeatureSet());

        $this->setProperty($controller, [
            'status' => TranslationStatus::STATUS_TRANSLATED,
            'id_job' => (string)$jobId,
            'password' => $jobPassword,
            'id_segment' => (string)$segmentId,
            'segment' => null,
            'propagate' => true,
            'context_before' => '',
            'context_after' => '',
            'project' => $project,
        ]);

        $new = new SegmentTranslationStruct();
        $new->translation = 'new';
        $old = new SegmentTranslationStruct();
        $old->translation = 'old';
        $old->status = TranslationStatus::STATUS_DRAFT;

        $method = $this->getAccessibleMethod('evalSetContribution');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Segment must not be null in evalSetContribution');
        $method->invoke($controller, $new, $old);
    }

    #[Test]
    public function getContextsFillsBeforeAndAfterFromSegmentDao(): void
    {
        $projectId = 891001;
        $jobId = 891002;
        $jobPassword = 'pw891002';
        $fileId = 891003;
        $segmentBefore = 891004;
        $segmentMain = 891005;
        $segmentAfter = 891006;
        $this->seedMinimalProjectJobAndSegment($projectId, $jobId, $jobPassword, $segmentMain, $fileId, 'Main segment', 2);

        $conn = Database::obtain()->getConnection();
        $conn->exec("INSERT IGNORE INTO segments (id, id_file, internal_id, segment, segment_hash, raw_word_count)
                     VALUES ({$segmentBefore}, {$fileId}, '0', 'Before context', 'hash_{$segmentBefore}', 2)");
        $conn->exec("INSERT IGNORE INTO segments (id, id_file, internal_id, segment, segment_hash, raw_word_count)
                     VALUES ({$segmentAfter}, {$fileId}, '2', 'After context', 'hash_{$segmentAfter}', 2)");

        $controller = $this->createControllerWithoutConstructor();
        $this->setNamedProperty($controller, 'filter', MateCatFilter::getInstance(new FeatureSet(), 'en-US', 'it-IT', []));
        $this->setNamedProperty($controller, 'featureSet', new FeatureSet());
        $this->setProperty($controller, [
            'id_before' => (string)$segmentBefore,
            'id_segment' => (string)$segmentMain,
            'id_after' => (string)$segmentAfter,
            'context_before' => '',
            'context_after' => '',
        ]);

        $method = $this->getAccessibleMethod('getContexts');
        $method->invoke($controller);

        $dataRef = new ReflectionProperty($controller, 'data');
        $data = $dataRef->getValue($controller);

        self::assertStringContainsString('Before context', $data['context_before']);
        self::assertStringContainsString('After context', $data['context_after']);
    }

    #[Test]
    public function checkSegmentSplitDataThrowsWhenIdSegmentIsMissingAfterSplitParsing(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $this->setNamedProperty($controller, 'logger', LoggerFactory::getLogger());
        $this->setNamedProperty($controller, 'request', new Request([], ['foo' => 'bar'], [], [], [], null));
        $this->setNamedProperty($controller, 'filter', MateCatFilter::getInstance(new FeatureSet(), 'en-US', 'it-IT', []));

        $this->setProperty($controller, [
            'translation' => 'text',
            'id_segment' => '-2',
            'status' => TranslationStatus::STATUS_TRANSLATED,
            'split_statuses' => [TranslationStatus::STATUS_TRANSLATED],
            'split_num' => null,
        ]);

        $method = $this->getAccessibleMethod('checkSegmentSplitData');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('missing id_segment');
        $method->invoke($controller);
    }

    #[Test]
    public function checkSegmentSplitDataThrowsForInvalidStatus(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $this->setNamedProperty($controller, 'logger', LoggerFactory::getLogger());
        $this->setNamedProperty($controller, 'request', new Request([], ['payload' => 'x'], [], [], [], null));
        $this->setNamedProperty($controller, 'filter', MateCatFilter::getInstance(new FeatureSet(), 'en-US', 'it-IT', []));

        $this->setProperty($controller, [
            'translation' => 'text',
            'id_segment' => '50',
            'status' => 'HACKED',
            'split_statuses' => [''],
            'split_num' => null,
        ]);

        $method = $this->getAccessibleMethod('checkSegmentSplitData');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error Hack Status');
        $method->invoke($controller);
    }

    #[Test]
    public function validateTheRequestThrowsWhenPasswordIsMissing(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $this->setNamedProperty($controller, 'logger', LoggerFactory::getLogger());
        $this->setNamedProperty($controller, 'request', new Request([], ['id_job' => '1', 'password' => null, 'id_segment' => '1'], [], [], [], null));

        $method = $this->getAccessibleMethod('validateTheRequest');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing password');
        $method->invoke($controller);
    }

    #[Test]
    public function validateTheRequestThrowsWhenIdSegmentIsMissing(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $this->setNamedProperty($controller, 'logger', LoggerFactory::getLogger());
        $this->setNamedProperty($controller, 'request', new Request([], ['id_job' => '1', 'password' => 'pw', 'id_segment' => null], [], [], [], null));

        $method = $this->getAccessibleMethod('validateTheRequest');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing id segment');
        $method->invoke($controller);
    }

    #[Test]
    public function validateTheRequestThrowsWhenJobIsArchived(): void
    {
        $projectId = 892001;
        $jobId = 892002;
        $jobPassword = 'pw892002';
        $segmentId = 892003;
        $fileId = 892004;
        $this->seedMinimalProjectJobAndSegment($projectId, $jobId, $jobPassword, $segmentId, $fileId, 'Archived', 2);
        Database::obtain()->getConnection()->exec("UPDATE jobs SET status = 'archived' WHERE id = {$jobId}");

        $controller = $this->createControllerWithoutConstructor();
        $this->setNamedProperty($controller, 'logger', LoggerFactory::getLogger());
        $this->setNamedProperty($controller, 'request', new Request([], [
            'id_job' => (string)$jobId,
            'password' => $jobPassword,
            'id_segment' => (string)$segmentId,
        ], [], [], [], null));

        $method = $this->getAccessibleMethod('validateTheRequest');

        $this->expectException(\Model\Exceptions\NotFoundException::class);
        $this->expectExceptionMessage('Job archived');
        $method->invoke($controller);
    }

    #[Test]
    public function evalSetContributionReturnsEarlyForDraftStatus(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $this->setProperty($controller, ['status' => TranslationStatus::STATUS_DRAFT]);

        $new = new SegmentTranslationStruct();
        $old = new SegmentTranslationStruct();

        $method = $this->getAccessibleMethod('evalSetContribution');
        $method->invoke($controller, $new, $old);

        self::assertTrue(true);
    }

    #[Test]
    public function getTranslationObjectBuildsApiV2LikePayload(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $this->setNamedProperty($controller, 'filter', MateCatFilter::getInstance(new FeatureSet(), 'en-US', 'it-IT', []));

        $saved = new SegmentTranslationStruct();
        $saved->version_number = 11;
        $saved->id_segment = 901;
        $saved->translation = 'Hello <b>world</b>';
        $saved->status = TranslationStatus::STATUS_TRANSLATED;

        $method = $this->getAccessibleMethod('getTranslationObject');
        $result = $method->invoke($controller, $saved);

        self::assertSame(11, $result['version_number']);
        self::assertSame(901, $result['sid']);
        self::assertSame(TranslationStatus::STATUS_TRANSLATED, $result['status']);
        self::assertIsString($result['translation']);
    }

    #[Test]
    public function getOriginalSuggestionProviderReturnsDefaultTMForNonDraftOldStatus(): void
    {
        $controller = $this->createControllerWithoutConstructor();

        $new = new SegmentTranslationStruct();
        $new->suggestions_array = json_encode([
            ['created_by' => 'x-deepl']
        ]);
        $new->suggestion_position = 1;

        $old = new SegmentTranslationStruct();
        $old->status = TranslationStatus::STATUS_TRANSLATED;

        $method = $this->getAccessibleMethod('getOriginalSuggestionProvider');
        $provider = $method->invoke($controller, $new, $old);

        self::assertSame(EngineConstants::TM, $provider);
    }

    #[Test]
    public function getOriginalSuggestionProviderExtractsProviderSuffixFromCreatedBy(): void
    {
        $controller = $this->createControllerWithoutConstructor();

        $new = new SegmentTranslationStruct();
        $new->suggestions_array = json_encode([
            ['created_by' => 'x-deepl']
        ]);
        $new->suggestion_position = 1;

        $old = new SegmentTranslationStruct();
        $old->status = TranslationStatus::STATUS_DRAFT;

        $method = $this->getAccessibleMethod('getOriginalSuggestionProvider');
        $provider = $method->invoke($controller, $new, $old);

        self::assertSame('deepl', $provider);
    }

    #[Test]
    public function checkSegmentSplitDataThrowsOnEmptyTranslation(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $this->setNamedProperty($controller, 'logger', LoggerFactory::getLogger());
        $this->setNamedProperty($controller, 'request', new Request([], ['foo' => 'bar'], [], [], [], null));
        $this->setNamedProperty($controller, 'filter', MateCatFilter::getInstance(new FeatureSet(), 'en-US', 'it-IT', []));

        $this->setProperty($controller, [
            'translation' => '',
            'id_segment' => '10',
            'status' => TranslationStatus::STATUS_TRANSLATED,
            'split_statuses' => [TranslationStatus::STATUS_TRANSLATED],
            'split_num' => null,
        ]);

        $method = $this->getAccessibleMethod('checkSegmentSplitData');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Empty Translation');
        $method->invoke($controller);
    }

    #[Test]
    public function checkSegmentSplitDataNormalizesIdAndResetsStatusToDraftForMixedSplitStatuses(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $this->setNamedProperty($controller, 'logger', LoggerFactory::getLogger());
        $this->setNamedProperty($controller, 'request', new Request([], ['foo' => 'bar'], [], [], [], null));
        $this->setNamedProperty($controller, 'filter', MateCatFilter::getInstance(new FeatureSet(), 'en-US', 'it-IT', []));

        $this->setProperty($controller, [
            'translation' => 'text',
            'id_segment' => '123-2',
            'status' => TranslationStatus::STATUS_TRANSLATED,
            'split_statuses' => [TranslationStatus::STATUS_TRANSLATED, TranslationStatus::STATUS_DRAFT],
            'split_num' => null,
        ]);

        $method = $this->getAccessibleMethod('checkSegmentSplitData');
        $method->invoke($controller);

        $dataRef = new ReflectionProperty($controller, 'data');
        $data = $dataRef->getValue($controller);
        self::assertSame('123', $data['id_segment']);
        self::assertSame('2', $data['split_num']);
        self::assertSame(TranslationStatus::STATUS_DRAFT, $data['status']);
    }

    #[Test]
    public function setSubFilteringBehaviorCreatesMateCatFilter(): void
    {
        $projectId = 884001;
        $jobId = 884002;
        $jobPassword = 'pw884002';
        $segmentId = 884003;
        $fileId = 884004;
        $this->seedMinimalProjectJobAndSegment($projectId, $jobId, $jobPassword, $segmentId, $fileId, 'Source', 2);

        $chunk = (new \Model\Jobs\JobDao())->getByIdAndPasswordOrFail($jobId, $jobPassword);
        $project = $chunk->getProject();

        $controller = $this->createControllerWithoutConstructor();
        $this->setNamedProperty($controller, 'featureSet', new FeatureSet());
        $this->setNamedProperty($controller, 'id_job', $jobId);
        $this->setNamedProperty($controller, 'password', $jobPassword);
        $this->setNamedProperty($controller, 'sourceContainsIcu', false);
        $this->setProperty($controller, [
            'project' => $project,
            'chunk' => $chunk,
            'id_segment' => (string)$segmentId,
        ]);

        $method = $this->getAccessibleMethod('setSubFilteringBehavior');
        $method->invoke($controller);

        $filterProp = new ReflectionProperty($controller, 'filter');
        self::assertInstanceOf(MateCatFilter::class, $filterProp->getValue($controller));
    }

    #[Test]
    public function getOldTranslationReturnsExistingTranslation(): void
    {
        $projectId = 885001;
        $jobId = 885002;
        $jobPassword = 'pw885002';
        $segmentId = 885003;
        $fileId = 885004;
        $this->seedMinimalProjectJobAndSegment($projectId, $jobId, $jobPassword, $segmentId, $fileId, 'Hello old', 2);

        Database::obtain()->getConnection()->exec(
            "INSERT IGNORE INTO segment_translations (id_segment, id_job, segment_hash, translation, status, translation_date, time_to_edit)
             VALUES ({$segmentId}, {$jobId}, 'hash_{$segmentId}', 'ciao vecchio', 'DRAFT', NOW(), 10)"
        );

        $controller = $this->createControllerWithoutConstructor();
        $this->setProperty($controller, [
            'id_segment' => (string)$segmentId,
            'id_job' => (string)$jobId,
        ]);

        $method = $this->getAccessibleMethod('getOldTranslation');
        $old = $method->invoke($controller);

        self::assertInstanceOf(SegmentTranslationStruct::class, $old);
        self::assertSame('ciao vecchio', $old->translation);
        self::assertSame(TranslationStatus::STATUS_DRAFT, $old->status);
    }

    #[Test]
    public function getOldTranslationThrowsWhenNoSegmentAndNoExistingRow(): void
    {
        $original = AppConfig::$VOLUME_ANALYSIS_ENABLED;
        AppConfig::$VOLUME_ANALYSIS_ENABLED = false;

        try {
            $controller = $this->createControllerWithoutConstructor();
            $this->setProperty($controller, [
                'id_segment' => '999001',
                'id_job' => '999002',
            ]);
            $this->setNamedProperty($controller, 'segment', null);

            $method = $this->getAccessibleMethod('getOldTranslation');
            $this->expectException(\Error::class);
            $this->expectExceptionMessage('SegmentTranslationStruct::$status must not be accessed before initialization');
            $method->invoke($controller);
        } finally {
            AppConfig::$VOLUME_ANALYSIS_ENABLED = $original;
        }
    }

    #[Test]
    public function validateTheRequestThrowsWhenIdJobIsMissing(): void
    {
        $controller = $this->createControllerWithoutConstructor();
        $this->setNamedProperty($controller, 'logger', LoggerFactory::getLogger());
        $this->setNamedProperty($controller, 'request', new Request([], ['id_job' => null], [], [], [], null));

        $method = $this->getAccessibleMethod('validateTheRequest');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing id job');
        $method->invoke($controller);
    }

    #[Test]
    public function validateTheRequestSanitizesAndLoadsChunkAndSegment(): void
    {
        $projectId = 886001;
        $jobId = 886002;
        $jobPassword = 'pw886002';
        $segmentId = 886003;
        $fileId = 886004;
        $this->seedMinimalProjectJobAndSegment($projectId, $jobId, $jobPassword, $segmentId, $fileId, 'Hello validate', 2);

        $controller = $this->createControllerWithoutConstructor();
        $this->setNamedProperty($controller, 'logger', LoggerFactory::getLogger());
        $this->setNamedProperty($controller, 'request', new Request([], [
            'id_job' => (string)$jobId,
            'password' => $jobPassword,
            'current_password' => $jobPassword,
            'id_segment' => (string)$segmentId,
            'time_to_edit' => '1500',
            'id_translator' => '123',
            'translation' => 'Nuova traduzione',
            'segment' => 'Hello validate',
            'version' => '3',
            'chosen_suggestion_index' => '1',
            'suggestion_array' => '[{"raw_translation":"s"}]',
            'status' => 'translated',
            'splitStatuses' => 'new,draft',
            'context_before' => 'before',
            'context_after' => 'after',
            'id_before' => '1',
            'id_after' => '2',
            'revision_number' => '0',
            'guess_tag_used' => '1',
            'characters_counter' => '12',
            'propagate' => '1',
        ], [], [], [], null));

        $method = $this->getAccessibleMethod('validateTheRequest');
        $data = $method->invoke($controller);

        self::assertSame((string)$jobId, $data['id_job']);
        self::assertSame($jobPassword, $data['password']);
        self::assertSame((string)$segmentId, $data['id_segment']);
        self::assertSame('TRANSLATED', $data['status']);
        self::assertSame(['NEW', 'DRAFT'], $data['split_statuses']);
        self::assertInstanceOf(\Model\Jobs\JobStruct::class, $data['chunk']);
        self::assertInstanceOf(SegmentStruct::class, $data['segment']);
    }

    #[Test]
    public function prepareTranslationReturnsPreparedPayloadForValidRequest(): void
    {
        $projectId = 893001;
        $jobId = 893002;
        $jobPassword = 'pw893002';
        $segmentId = 893003;
        $fileId = 893004;
        $beforeId = 893005;
        $afterId = 893006;

        $this->seedMinimalProjectJobAndSegment($projectId, $jobId, $jobPassword, $segmentId, $fileId, 'Source segment', 2);
        $conn = Database::obtain()->getConnection();
        $conn->exec("INSERT IGNORE INTO segments (id, id_file, internal_id, segment, segment_hash, raw_word_count)
                     VALUES ({$beforeId}, {$fileId}, '0', 'Before text', 'hash_{$beforeId}', 2)");
        $conn->exec("INSERT IGNORE INTO segments (id, id_file, internal_id, segment, segment_hash, raw_word_count)
                     VALUES ({$afterId}, {$fileId}, '2', 'After text', 'hash_{$afterId}', 2)");

        $controller = $this->createControllerWithoutConstructor();
        $this->setNamedProperty($controller, 'logger', LoggerFactory::getLogger());
        $this->setNamedProperty($controller, 'user', new \Model\Users\UserStruct());
        $this->setNamedProperty($controller, 'featureSet', new FeatureSet());
        $this->setNamedProperty($controller, 'request', new Request([], [
            'id_job' => (string)$jobId,
            'password' => $jobPassword,
            'current_password' => $jobPassword,
            'id_segment' => (string)$segmentId,
            'time_to_edit' => '1000',
            'id_translator' => '1',
            'translation' => 'Traduzione pronta',
            'segment' => 'Source segment',
            'version' => '1',
            'chosen_suggestion_index' => null,
            'suggestion_array' => null,
            'status' => 'translated',
            'splitStatuses' => '',
            'context_before' => '',
            'context_after' => '',
            'id_before' => (string)$beforeId,
            'id_after' => (string)$afterId,
            'revision_number' => '0',
            'guess_tag_used' => '0',
            'characters_counter' => null,
            'propagate' => '0',
        ], [], [], [], null));

        $method = $this->getAccessibleMethod('prepareTranslation');
        $prepared = $method->invoke($controller);

        self::assertArrayHasKey('segment', $prepared);
        self::assertArrayHasKey('translation', $prepared);
        self::assertArrayHasKey('check', $prepared);
        self::assertArrayHasKey('err_json', $prepared);
        self::assertInstanceOf(QA::class, $prepared['check']);
        self::assertIsString($prepared['translation']);
    }

    #[Test]
    public function persistTranslationWithoutPropagationStoresTranslationAndReturnsDefaultTotals(): void
    {
        $projectId = 894001;
        $jobId = 894002;
        $jobPassword = 'pw894002';
        $segmentId = 894003;
        $fileId = 894004;
        $this->seedMinimalProjectJobAndSegment($projectId, $jobId, $jobPassword, $segmentId, $fileId, 'Source persist', 2);

        $chunk = (new \Model\Jobs\JobDao())->getByIdAndPasswordOrFail($jobId, $jobPassword);
        $project = $chunk->getProject();

        $controller = $this->createControllerWithoutConstructor();
        $this->setNamedProperty($controller, 'chunk', $chunk);
        $segmentDao = new \Model\Segments\SegmentDao(Database::obtain());
        $this->setNamedProperty($controller, 'segment', $segmentDao->fetchById($segmentId, \Model\Segments\SegmentStruct::class));
        $this->setNamedProperty($controller, 'id_job', $jobId);
        $this->setNamedProperty($controller, 'password', $jobPassword);
        $this->setNamedProperty($controller, 'request_password', $jobPassword);
        $this->setNamedProperty($controller, 'user', new \Model\Users\UserStruct());

        $versionsHandler = new class implements \Plugins\Features\TranslationVersions\VersionHandlerInterface {
            public function saveVersionAndIncrement(SegmentTranslationStruct $new_translation, SegmentTranslationStruct $old_translation): bool
            {
                return true;
            }

            public function storeTranslationEvent(array $params): void
            {
            }

            public function propagateTranslation(SegmentTranslationStruct $translationStruct): array
            {
                return [];
            }
        };
        $this->setNamedProperty($controller, 'VersionsHandler', $versionsHandler);

        $featureSet = $this->createMock(FeatureSet::class);
        $featureSet->expects(self::once())
            ->method('dispatchRun')
            ->with(self::isInstanceOf(PostAddSegmentTranslationEvent::class));
        $this->setNamedProperty($controller, 'featureSet', $featureSet);

        $this->setProperty($controller, [
            'id_segment' => (string)$segmentId,
            'id_job' => (string)$jobId,
            'status' => TranslationStatus::STATUS_DRAFT,
            'propagate' => false,
            'split_statuses' => [''],
            'split_num' => null,
            'split_chunk_lengths' => null,
            'project' => $project,
            'revisionNumber' => 0,
            'chunk' => $chunk,
            'segment' => $segmentDao->fetchById($segmentId, \Model\Segments\SegmentStruct::class),
        ]);

        $old = new SegmentTranslationStruct();
        $old->id_segment = $segmentId;
        $old->id_job = $jobId;
        $old->segment_hash = 'hash_' . $segmentId;
        $old->translation = 'vecchia';
        $old->status = TranslationStatus::STATUS_DRAFT;
        $old->match_type = InternalMatchesConstants::NO_MATCH;
        $old->locked = false;
        $old->suggestion = 'old';
        $old->time_to_edit = 0;

        $new = new SegmentTranslationStruct();
        $new->id_segment = $segmentId;
        $new->id_job = $jobId;
        $new->segment_hash = 'hash_' . $segmentId;
        $new->translation = 'nuova';
        $new->status = TranslationStatus::STATUS_DRAFT;
        $new->match_type = InternalMatchesConstants::NO_MATCH;
        $new->locked = false;
        $new->time_to_edit = 1000;
        $new->suggestion = 'new';
        $new->translation_date = date('Y-m-d H:i:s');

        $qa = $this->createStub(QA::class);
        $qa->method('thereAreWarnings')->willReturn(false);

        $method = $this->getAccessibleMethod('persistTranslation');
        $result = $method->invoke($controller, $new, $old, 'nuova', '', $qa);

        self::assertSame([
            'totals' => [],
            'propagated_ids' => [],
            'segments_for_propagation' => [],
        ], $result);

        $stored = \Model\Translations\SegmentTranslationDao::findBySegmentAndJob($segmentId, $jobId);
        self::assertInstanceOf(SegmentTranslationStruct::class, $stored);
        self::assertSame('nuova', $stored->translation);
    }

    #[Test]
    public function persistTranslationWithPropagationReturnsPropagationTotalsFromVersionHandler(): void
    {
        $projectId = 898001;
        $jobId = 898002;
        $jobPassword = 'pw898002';
        $segmentId = 898003;
        $fileId = 898004;
        $this->seedMinimalProjectJobAndSegment($projectId, $jobId, $jobPassword, $segmentId, $fileId, 'Source propagation', 2);

        $chunk = (new \Model\Jobs\JobDao())->getByIdAndPasswordOrFail($jobId, $jobPassword);
        $project = $chunk->getProject();

        $controller = $this->createControllerWithoutConstructor();
        $segmentDao = new \Model\Segments\SegmentDao(Database::obtain());
        $this->setNamedProperty($controller, 'chunk', $chunk);
        $this->setNamedProperty($controller, 'segment', $segmentDao->fetchById($segmentId, \Model\Segments\SegmentStruct::class));
        $this->setNamedProperty($controller, 'id_job', $jobId);
        $this->setNamedProperty($controller, 'password', $jobPassword);
        $this->setNamedProperty($controller, 'request_password', $jobPassword);
        $this->setNamedProperty($controller, 'user', new \Model\Users\UserStruct());

        $expectedPropagation = [
            'totals' => ['translated' => 1],
            'propagated_ids' => [100, 101],
            'segments_for_propagation' => ['propagated_ids' => [100, 101]],
        ];

        $versionsHandler = new class($expectedPropagation) implements \Plugins\Features\TranslationVersions\VersionHandlerInterface {
            public function __construct(private array $propagation)
            {
            }

            public function saveVersionAndIncrement(SegmentTranslationStruct $new_translation, SegmentTranslationStruct $old_translation): bool
            {
                return true;
            }

            public function storeTranslationEvent(array $params): void
            {
            }

            public function propagateTranslation(SegmentTranslationStruct $translationStruct): array
            {
                return $this->propagation;
            }
        };
        $this->setNamedProperty($controller, 'VersionsHandler', $versionsHandler);

        $featureSet = $this->createStub(FeatureSet::class);
        $this->setNamedProperty($controller, 'featureSet', $featureSet);

        $this->setProperty($controller, [
            'id_segment' => (string)$segmentId,
            'id_job' => (string)$jobId,
            'status' => TranslationStatus::STATUS_TRANSLATED,
            'propagate' => true,
            'split_statuses' => [''],
            'split_num' => null,
            'split_chunk_lengths' => null,
            'project' => $project,
            'revisionNumber' => 0,
            'chunk' => $chunk,
            'segment' => $segmentDao->fetchById($segmentId, \Model\Segments\SegmentStruct::class),
        ]);

        $old = new SegmentTranslationStruct();
        $old->id_segment = $segmentId;
        $old->id_job = $jobId;
        $old->segment_hash = 'hash_' . $segmentId;
        $old->translation = 'stessa';
        $old->status = TranslationStatus::STATUS_DRAFT;
        $old->match_type = InternalMatchesConstants::NO_MATCH;
        $old->locked = false;
        $old->suggestion = 'old';
        $old->time_to_edit = 0;

        $new = new SegmentTranslationStruct();
        $new->id_segment = $segmentId;
        $new->id_job = $jobId;
        $new->segment_hash = 'hash_' . $segmentId;
        $new->translation = 'stessa';
        $new->status = TranslationStatus::STATUS_TRANSLATED;
        $new->match_type = InternalMatchesConstants::NO_MATCH;
        $new->locked = false;
        $new->time_to_edit = 1000;
        $new->suggestion = 'new';
        $new->translation_date = date('Y-m-d H:i:s');

        $qa = $this->createStub(QA::class);
        $qa->method('thereAreWarnings')->willReturn(false);

        $method = $this->getAccessibleMethod('persistTranslation');
        $result = $method->invoke($controller, $new, $old, 'stessa', '', $qa);

        self::assertSame($expectedPropagation, $result);
    }

    #[Test]
    public function checkIfSegmentIsNotDisabledThrowsWhenSegmentMetadataMarksItDisabled(): void
    {
        $jobId = 895001;
        $segmentId = 895002;
        $conn = Database::obtain()->getConnection();
        $conn->exec("INSERT IGNORE INTO segment_metadata (id_segment, meta_key, meta_value)
                     VALUES ({$segmentId}, 'translation_disabled', '1')");

        $controller = $this->createControllerWithoutConstructor();
        $this->setProperty($controller, [
            'id_job' => (string)$jobId,
            'id_segment' => (string)$segmentId,
        ]);

        $method = $this->getAccessibleMethod('checkIfSegmentIsNotDisabled');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is disabled');
        $method->invoke($controller);
    }

    #[Test]
    public function checkIfSegmentIsNotDisabledDoesNothingWhenSegmentIsEnabled(): void
    {
        $jobId = 896001;
        $segmentId = 896002;

        $controller = $this->createControllerWithoutConstructor();
        $this->setProperty($controller, [
            'id_job' => (string)$jobId,
            'id_segment' => (string)$segmentId,
        ]);

        $method = $this->getAccessibleMethod('checkIfSegmentIsNotDisabled');
        $method->invoke($controller);

        self::assertTrue(true);
    }

    #[Test]
    public function initVersionHandlerInitializesVersionsHandlerProperty(): void
    {
        $projectId = 897001;
        $jobId = 897002;
        $jobPassword = 'pw897002';
        $segmentId = 897003;
        $fileId = 897004;
        $this->seedMinimalProjectJobAndSegment($projectId, $jobId, $jobPassword, $segmentId, $fileId, 'Init version', 2);

        $chunk = (new \Model\Jobs\JobDao())->getByIdAndPasswordOrFail($jobId, $jobPassword);
        $project = $chunk->getProject();

        $controller = $this->createControllerWithoutConstructor();
        $this->setNamedProperty($controller, 'user', new \Model\Users\UserStruct());
        $this->setProperty($controller, [
            'chunk' => $chunk,
            'project' => $project,
            'id_segment' => (string)$segmentId,
        ]);

        $method = $this->getAccessibleMethod('initVersionHandler');
        $method->invoke($controller);

        $versionsProp = new ReflectionProperty($controller, 'VersionsHandler');
        self::assertInstanceOf(\Plugins\Features\TranslationVersions\VersionHandlerInterface::class, $versionsProp->getValue($controller));
    }

    #[Test]
    public function buildResultUsesCurrentTimestampWhenTranslationDateIsInvalid(): void
    {
        $controller = $this->createControllerWithoutConstructor();

        $chunk = new \Model\Jobs\JobStruct();
        $chunk->id = 9101;
        $chunk->password = 'pw9101';
        $chunk->new_raw_words = 0;
        $chunk->draft_raw_words = 0;
        $chunk->translated_raw_words = 0;
        $chunk->approved_raw_words = 0;
        $chunk->approved2_raw_words = 0;

        $this->setProperty($controller, [
            'chunk' => $chunk,
            'project' => ['status_analysis' => 'DONE'],
            'id_segment' => '77',
            'segment' => new SegmentStruct(),
            'revisionNumber' => 0,
        ]);
        $this->setNamedProperty($controller, 'filter', MateCatFilter::getInstance(new FeatureSet(), 'en-US', 'it-IT', []));
        $this->setNamedProperty($controller, 'user', new \Model\Users\UserStruct());
        $this->setNamedProperty($controller, 'featureSet', $this->createStub(FeatureSet::class));

        $newTranslation = new SegmentTranslationStruct();
        $newTranslation->id_segment = 77;
        $newTranslation->status = TranslationStatus::STATUS_DRAFT;
        $newTranslation->translation = 'x';
        $newTranslation->translation_date = 'not-a-date';

        $oldTranslation = new SegmentTranslationStruct();
        $oldTranslation->status = TranslationStatus::STATUS_NEW;

        $qa = $this->createStub(QA::class);
        $qa->method('getWarnings')->willReturn([(object)['outcome' => 0]]);

        $before = time();
        $method = $this->getAccessibleMethod('buildResult');
        $result = $method->invoke($controller, $newTranslation, $oldTranslation, [], $qa);
        $after = time();

        self::assertGreaterThanOrEqual($before, $result['version']);
        self::assertLessThanOrEqual($after, $result['version']);
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Asserts that $expectedCalls appear in $source in the given order.
     *
     * @param string   $source        The method source code
     */
    private static function assertMethodCallOrder(string $source): void
    {
        $expectedCalls = [
            'prepareTranslation',
            '$db->begin()',
            'buildNewTranslation',
            'persistTranslation',
            '$db->commit()',
            'buildResult',
            'finalizeTranslation',
        ];
        $lastPos = 0;
        foreach ($expectedCalls as $call) {
            $pos = strpos($source, $call, $lastPos);
            self::assertNotFalse(
                $pos,
                "All phases must be delegated to extracted methods in correct order — '$call' not found after position $lastPos in translate() body"
            );
            $lastPos = $pos + strlen($call);
        }
    }

    private function getMethodSource(ReflectionMethod $method): string
    {
        $fileName = $method->getFileName();
        self::assertIsString($fileName, 'ReflectionMethod must have a file name');
        $lines = file($fileName);
        self::assertIsArray($lines, 'file() must return array of lines');
        $start = $method->getStartLine() - 1;
        $length = $method->getEndLine() - $start;

        return implode('', array_slice($lines, $start, $length));
    }
}
