<?php

namespace unit\Controllers;

use Controller\API\App\SetTranslationController;
use Model\Analysis\Constants\InternalMatchesConstants;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Projects\ProjectStruct;
use Model\Segments\SegmentStruct;
use Model\Translations\SegmentTranslationStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Utils\Constants\EngineConstants;
use Utils\Constants\TranslationStatus;
use Utils\LQA\QA;

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
