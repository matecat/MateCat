<?php

namespace unit\LQA;

use LogicException;
use Matecat\ICU\MessagePatternComparator;
use Model\Segments\SegmentMetadataStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\LQA\QA;
use Utils\LQA\QA\ErrorManager;

class QATest extends AbstractTest
{
    // ========== Constructor Tests ==========

    #[Test]
    public function constructorWithNullSegmentsCreatesInstance(): void
    {
        $qa = new QA(null, null);
        $this->assertFalse($qa->thereAreErrors());
    }

    #[Test]
    public function constructorWithEmptySegmentsHasNoErrors(): void
    {
        $qa = new QA('', '');
        $this->assertFalse($qa->thereAreErrors());
    }

    #[Test]
    public function constructorWithPlainTextHasNoErrors(): void
    {
        $qa = new QA('Hello World', 'Ciao Mondo');
        $this->assertFalse($qa->thereAreErrors());
    }

    #[Test]
    public function constructorWithValidXmlHasNoErrors(): void
    {
        $qa = new QA('<g id="1">Source</g>', '<g id="1">Target</g>');
        $this->assertFalse($qa->thereAreErrors());
    }

    #[Test]
    public function constructorWithUnclosedSourceTagHasErrors(): void
    {
        $qa = new QA('<g id="1">Unclosed', '<g id="1">Target</g>');
        $this->assertTrue($qa->thereAreErrors());
    }

    #[Test]
    public function constructorWithUnclosedTargetTagHasErrors(): void
    {
        $qa = new QA('<g id="1">Source</g>', '<g id="1">Unclosed');
        $this->assertTrue($qa->thereAreErrors());
    }

    // ========== Configuration Methods Tests ==========

    #[Test]
    public function setSourceSegLangAndGetSourceSegLangMatch(): void
    {
        $qa = new QA('Source', 'Target');
        $qa->setSourceSegLang('en-US');
        $this->assertEquals('en-US', $qa->getSourceSegLang());
    }

    #[Test]
    public function setTargetSegLangAndGetTargetSegLangMatch(): void
    {
        $qa = new QA('Source', 'Target');
        $qa->setTargetSegLang('it-IT');
        $this->assertEquals('it-IT', $qa->getTargetSegLang());
    }

    #[Test]
    public function setChunkReturnsSelfForChaining(): void
    {
        $qa = new QA('Source', 'Target');
        $result = $qa->setChunk(null);
        $this->assertSame($qa, $result);
    }

    // ========== Segment Getters Tests ==========

    #[Test]
    public function getSourceSegReturnsPreprocessedSource(): void
    {
        $qa = new QA('Hello World', 'Ciao Mondo');
        $this->assertEquals('Hello World', $qa->getSourceSeg());
    }

    #[Test]
    public function getTargetSegReturnsCleanedTarget(): void
    {
        $qa = new QA('Hello World', 'Ciao Mondo');
        $this->assertEquals('Ciao Mondo', $qa->getTargetSeg());
    }

    #[Test]
    public function getSourceSegReplacesTabWithPlaceholder(): void
    {
        $qa = new QA("Hello\tWorld", 'Ciao Mondo');
        $source = $qa->getSourceSeg();
        $this->assertStringContainsString('##$_09$##', $source);
    }

    #[Test]
    public function getTargetSegConvertsTabToHexEntity(): void
    {
        $qa = new QA('Source', "Target\tText");
        $target = $qa->getTargetSeg();
        $this->assertStringContainsString('&#x09;', $target);
    }

    #[Test]
    public function getSourceSegReplacesNewlineWithPlaceholder(): void
    {
        $qa = new QA("Line1\nLine2", 'Ciao');
        $source = $qa->getSourceSeg();
        $this->assertStringContainsString('##$_0A$##', $source);
    }

    // ========== Error Methods Tests ==========

    #[Test]
    public function addErrorWithTagMismatchSetsErrorState(): void
    {
        $qa = new QA('Source', 'Target');
        $qa->addError(QA::ERR_TAG_MISMATCH);
        $this->assertTrue($qa->thereAreErrors());
    }

    #[Test]
    public function addErrorWithTagOrderSetsWarningState(): void
    {
        $qa = new QA('Source', 'Target');
        $qa->addError(QA::ERR_TAG_ORDER);
        $this->assertTrue($qa->thereAreWarnings());
        $this->assertFalse($qa->thereAreErrors());
    }

    #[Test]
    public function addErrorWithWhitespaceHeadSetsNoticeState(): void
    {
        $qa = new QA('Source', 'Target');
        $qa->addError(QA::ERR_WS_HEAD);
        $this->assertTrue($qa->thereAreNotices());
        $this->assertFalse($qa->thereAreWarnings());
    }

    #[Test]
    public function addCustomErrorMakesItAvailable(): void
    {
        $qa = new QA('Source', 'Target');
        $qa->addCustomError(['code' => 9999, 'debug' => 'Custom error', 'tip' => 'Fix it']);
        $qa->addError(9999);
        $this->assertTrue($qa->thereAreWarnings());
    }

    #[Test]
    public function getErrorsReturnsErrorObjects(): void
    {
        $qa = new QA('Source', 'Target');
        $qa->addError(QA::ERR_TAG_MISMATCH);

        $errors = $qa->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals(QA::ERR_TAG_MISMATCH, $errors[0]->outcome);
    }

    #[Test]
    public function getErrorsJSONReturnsValidJson(): void
    {
        $qa = new QA('Source', 'Target');
        $qa->addError(QA::ERR_TAG_MISMATCH);

        $json = $qa->getErrorsJSON();
        $decoded = json_decode($json, true);
        $this->assertNotNull($decoded);
        $this->assertEquals(QA::ERR_TAG_MISMATCH, $decoded[0]['outcome']);
    }

    #[Test]
    public function getWarningsIncludesErrorsAndWarnings(): void
    {
        $qa = new QA('Source', 'Target');
        $qa->addError(QA::ERR_TAG_MISMATCH);
        $qa->addError(QA::ERR_TAG_ORDER);

        $warnings = $qa->getWarnings();
        $this->assertCount(2, $warnings);
    }

    #[Test]
    public function getNoticesIncludesAllSeverityLevels(): void
    {
        $qa = new QA('Source', 'Target');
        $qa->addError(QA::ERR_TAG_MISMATCH);
        $qa->addError(QA::ERR_TAG_ORDER);
        $qa->addError(QA::ERR_WS_HEAD);

        $notices = $qa->getNotices();
        $this->assertCount(3, $notices);
    }

    #[Test]
    public function getExceptionListGroupsBySeverity(): void
    {
        $qa = new QA('Source', 'Target');
        $qa->addError(QA::ERR_TAG_MISMATCH);
        $qa->addError(QA::ERR_TAG_ORDER);
        $qa->addError(QA::ERR_WS_HEAD);

        $list = $qa->getExceptionList();
        $this->assertArrayHasKey(QA::ERROR, $list);
        $this->assertArrayHasKey(QA::WARNING, $list);
        $this->assertArrayHasKey(QA::INFO, $list);
        $this->assertCount(1, $list[QA::ERROR]);
        $this->assertCount(1, $list[QA::WARNING]);
        $this->assertCount(1, $list[QA::INFO]);
    }

    #[Test]
    public function jsonToExceptionListParsesValidJson(): void
    {
        $json = '[{"outcome": 1000, "debug": "Tag mismatch."}]';
        $list = QA::JSONtoExceptionList($json);

        $this->assertCount(1, $list[QA::ERROR]);
    }

    #[Test]
    public function jsonToExceptionListHandlesInvalidJson(): void
    {
        $json = 'invalid json';
        $list = QA::JSONtoExceptionList($json);

        $this->assertEmpty($list[QA::ERROR]);
    }

    // ========== Perform Consistency Check Tests ==========

    #[Test]
    public function performConsistencyCheckWithMatchingTagsReturnsNoError(): void
    {
        $qa = new QA('<g id="1">Source</g>', '<g id="1">Target</g>');
        $errors = $qa->performConsistencyCheck();

        $this->assertCount(1, $errors);
        $this->assertEquals(QA::ERR_NONE, $errors[0]->outcome);
    }

    #[Test]
    public function performConsistencyCheckWithMismatchedTagIdHasErrors(): void
    {
        $qa = new QA('<g id="1">Source</g>', '<g id="2">Target</g>');
        $qa->performConsistencyCheck();

        $this->assertTrue($qa->thereAreErrors());
    }

    #[Test]
    public function performConsistencyCheckWithMismatchedTagCountHasErrors(): void
    {
        $qa = new QA('<g id="1">Source</g><x id="2"/>', '<g id="1">Target</g>');
        $qa->performConsistencyCheck();

        $this->assertTrue($qa->thereAreErrors());
    }

    #[Test]
    public function performConsistencyCheckWithMissingSymbolHasNotices(): void
    {
        $qa = new QA('Price: €100', 'Prezzo: 100');
        $qa->performConsistencyCheck();

        $this->assertTrue($qa->thereAreNotices());
    }

    #[Test]
    public function performConsistencyCheckWithMismatchedNewlinesHasNotices(): void
    {
        $qa = new QA("Line1\nLine2", "Linea1 Linea2");
        $qa->performConsistencyCheck();

        $this->assertTrue($qa->thereAreNotices());
    }

    #[Test]
    public function performConsistencyCheckWithUnclosedTagHasErrors(): void
    {
        $qa = new QA('<g id="1">Unclosed', '<g id="1">Target</g>');
        $qa->performConsistencyCheck();

        $this->assertTrue($qa->thereAreErrors());
    }

    // ========== Perform Tag Check Only Tests ==========

    #[Test]
    public function performTagCheckOnlyWithMatchingTagsReturnsNoError(): void
    {
        $qa = new QA('<g id="1">Source</g>', '<g id="1">Target</g>');
        $errors = $qa->performTagCheckOnly();

        $this->assertCount(1, $errors);
        $this->assertEquals(QA::ERR_NONE, $errors[0]->outcome);
    }

    #[Test]
    public function performTagCheckOnlyWithMismatchedCountHasErrors(): void
    {
        $qa = new QA('<g id="1">Source</g><x id="2"/>', '<g id="1">Target</g>');
        $qa->performTagCheckOnly();

        $this->assertTrue($qa->thereAreErrors());
    }

    #[Test]
    public function performTagCheckOnlyIgnoresSymbolMismatches(): void
    {
        $qa = new QA('€100', '100');
        $qa->performTagCheckOnly();

        // Symbol check is not performed in tag-only mode
        $this->assertFalse($qa->thereAreErrors());
    }

    // ========== Perform Tag Position Check Tests ==========

    #[Test]
    public function performTagPositionCheckWithReorderedTagsHasWarnings(): void
    {
        $qa = new QA('<g id="1">S</g><g id="2">T</g>', '<g id="1">T</g><g id="2">S</g>');
        $qa->performTagPositionCheck(
            '<g id="1">S</g><g id="2">T</g>',
            '<g id="2">T</g><g id="1">S</g>'
        );

        $this->assertTrue($qa->thereAreWarnings());
    }

    #[Test]
    public function performTagPositionCheckWithMatchingOrderHasNoWarnings(): void
    {
        $qa = new QA('<g id="1">S</g><g id="2">T</g>', '<g id="1">T</g><g id="2">S</g>');
        $qa->performTagPositionCheck(
            '<g id="1">S</g><g id="2">T</g>',
            '<g id="1">T</g><g id="2">S</g>'
        );

        $this->assertFalse($qa->thereAreWarnings());
    }

    // ========== Get Normalized Target Tests ==========

    #[Test]
    public function getTrgNormalizedReturnsNormalizedTarget(): void
    {
        $qa = new QA('<g id="1">Source</g>', '<g id="1">Target</g>');
        $qa->prepareDOMStructures();

        $normalized = $qa->getTrgNormalized();
        $this->assertStringContainsString('Target', $normalized);
    }

    #[Test]
    public function getTrgNormalizedThrowsExceptionWhenErrors(): void
    {
        $qa = new QA('<g id="1">Source</g>', '<g id="2">Target</g>');
        $qa->performConsistencyCheck();

        $this->expectException(LogicException::class);
        $qa->getTrgNormalized();
    }

    #[Test]
    public function getTrgNormalizedCleansWhitespaceInEmptyTags(): void
    {
        $qa = new QA('<g id="1"></g>', '<g id="1"></g>');
        $qa->prepareDOMStructures();

        $normalized = $qa->getTrgNormalized();
        $this->assertEquals('<g id="1"></g>', $normalized);
    }

    // ========== Get Malformed XML Structs Tests ==========

    #[Test]
    public function getMalformedXmlStructsReturnsArrayWithSourceAndTarget(): void
    {
        $qa = new QA('<g id="1">Source</g>', '<g id="2">Target</g>');
        $qa->performConsistencyCheck();

        $structs = $qa->getMalformedXmlStructs();
        $this->assertArrayHasKey('source', $structs);
        $this->assertArrayHasKey('target', $structs);
    }

    // ========== Get Target Tag Position Error Tests ==========

    #[Test]
    public function getTargetTagPositionErrorReturnsArrayAfterCheck(): void
    {
        $qa = new QA('<g id="1">A</g><g id="2">B</g>', '<g id="2">B</g><g id="1">A</g>');
        $qa->performConsistencyCheck();

        $errors = $qa->getTargetTagPositionError();
        $this->assertIsArray($errors);
    }

    // ========== Component Getters Tests ==========

    #[Test]
    public function getErrorManagerReturnsSharedInstance(): void
    {
        $qa = new QA('Source', 'Target');
        $qa->addError(QA::ERR_TAG_MISMATCH);

        $manager = $qa->getErrorManager();
        $this->assertTrue($manager->thereAreErrors());
    }

    #[Test]
    public function getPreprocessorReturnsSharedInstance(): void
    {
        $qa = new QA('Source', 'Target');
        $preprocessor = $qa->getPreprocessor();

        $result = $preprocessor->preprocess("test\ttab");
        $this->assertStringContainsString('##$_09$##', $result);
    }

    #[Test]
    public function getDomHandlerReturnsSharedInstance(): void
    {
        $qa = new QA('<g id="1">Source</g>', '<g id="1">Target</g>');
        $handler = $qa->getDomHandler();

        $srcMap = $handler->getSrcDomMap();
        $this->assertIsArray($srcMap);
    }

    #[Test]
    public function getTagCheckerReturnsSharedInstance(): void
    {
        $qa = new QA('<g id="1">Source</g>', '<g id="1">Target</g>');
        $checker = $qa->getTagChecker();

        $errors = $checker->getTagPositionError();
        $this->assertIsArray($errors);
    }

    // ========== Complex Scenarios Tests ==========

    #[Test]
    public function complexNestedTagsAreHandledCorrectly(): void
    {
        $source = '<g id="1"><g id="2"><x id="3"/></g></g>';
        $target = '<g id="1"><g id="2"><x id="3"/></g></g>';

        $qa = new QA($source, $target);
        $qa->performConsistencyCheck();

        $this->assertFalse($qa->thereAreErrors());
    }

    #[Test]
    public function bxExTagsAreHandledCorrectly(): void
    {
        $source = '<bx id="1"/><ex id="1"/>';
        $target = '<bx id="1"/><ex id="1"/>';

        $qa = new QA($source, $target);
        $qa->performConsistencyCheck();

        $this->assertFalse($qa->thereAreErrors());
    }

    #[Test]
    public function mixedContentWithSpacesIsHandledCorrectly(): void
    {
        $source = 'Text <g id="1">inside</g> more';
        $target = 'Testo <g id="1">dentro</g> altro';

        $qa = new QA($source, $target);
        $qa->performConsistencyCheck();

        $this->assertFalse($qa->thereAreErrors());
    }

    #[Test]
    public function emptyGTagsAreHandledCorrectly(): void
    {
        $source = '<g id="1"></g>';
        $target = '<g id="1"></g>';

        $qa = new QA($source, $target);
        $qa->performConsistencyCheck();

        $this->assertFalse($qa->thereAreErrors());
    }

    #[Test]
    public function phTagWithEquivTextIsHandledCorrectly(): void
    {
        $source = '<ph id="mtc_1" equiv-text="base64:dGVzdA=="/>';
        $target = '<ph id="mtc_1" equiv-text="base64:dGVzdA=="/>';

        $qa = new QA($source, $target);
        $qa->performConsistencyCheck();

        $this->assertFalse($qa->thereAreErrors());
    }

    #[Test]
    public function whitespaceInsideTagsGeneratesNotices(): void
    {
        $source = '<g id="1"> Text with spaces </g>';
        $target = '<g id="1">Text with spaces</g>';

        $qa = new QA($source, $target);
        $qa->performConsistencyCheck();

        $this->assertTrue($qa->thereAreNotices());
    }

    #[Test]
    public function multipleSymbolMismatchesGenerateMultipleNotices(): void
    {
        $source = '€100 @ 50% = £50';
        $target = '100 at 50 percent equals 50';

        $qa = new QA($source, $target);
        $qa->performConsistencyCheck();

        $this->assertTrue($qa->thereAreNotices());
        $notices = $qa->getNotices();
        $this->assertGreaterThan(1, count($notices));
    }

    #[Test]
    public function selfClosingTagsAreHandledCorrectly(): void
    {
        $source = '<x id="1"/><x id="2"/>';
        $target = '<x id="1"/><x id="2"/>';

        $qa = new QA($source, $target);
        $qa->performConsistencyCheck();

        $this->assertFalse($qa->thereAreErrors());
    }

    #[Test]
    public function mixedSelfClosingAndPairedTagsAreHandledCorrectly(): void
    {
        $source = '<g id="1">Text <x id="2"/> more</g>';
        $target = '<g id="1">Testo <x id="2"/> altro</g>';

        $qa = new QA($source, $target);
        $qa->performConsistencyCheck();

        $this->assertFalse($qa->thereAreErrors());
    }

    #[Test]
    public function segmentWithOnlyWhitespaceHasNoErrors(): void
    {
        $qa = new QA('   ', '   ');
        $qa->performConsistencyCheck();

        $this->assertFalse($qa->thereAreErrors());
    }

    #[Test]
    public function headingWhitespaceMismatchGeneratesNotice(): void
    {
        $qa = new QA(' Text', 'Text');
        $qa->performConsistencyCheck();

        $this->assertTrue($qa->thereAreNotices());
    }

    #[Test]
    public function trailingWhitespaceMismatchGeneratesNotice(): void
    {
        $qa = new QA('Text ', 'Text');
        $qa->setSourceSegLang('en-US');
        $qa->performConsistencyCheck();

        $this->assertTrue($qa->thereAreNotices());
    }

    #[Test]
    public function prepareDomStructuresPopulatesDomMaps(): void
    {
        $qa = new QA('<g id="1">Source</g>', '<g id="1">Target</g>');
        $qa->prepareDOMStructures();

        $handler = $qa->getDomHandler();
        $srcMap = $handler->getSrcDomMap();
        $trgMap = $handler->getTrgDomMap();

        $this->assertNotEmpty($srcMap);
        $this->assertNotEmpty($trgMap);
    }

    #[Test]
    public function cjkSourceLanguageSkipsTrailingSpaceCheck(): void
    {
        $qa = new QA('Text ', 'テキスト');
        $qa->setSourceSegLang('ja-JP');
        $qa->performConsistencyCheck();

        // CJK source should not report trailing space mismatch
        $notices = $qa->getNotices();
        foreach ($notices as $notice) {
            $this->assertNotEquals(ErrorManager::ERR_BOUNDARY_TAIL_SPACE_MISMATCH, $notice->outcome);
        }
    }

    #[Test]
    public function setCharactersCountConfiguresSizeRestrictionChecker(): void
    {
        $qa = new QA('Source', 'Target');
        $qa->setCharactersCount(99, new SegmentMetadataStruct(['meta_value' => 100, 'meta_key' => QA::SIZE_RESTRICTION]));
        $qa->performConsistencyCheck();

        // Should not throw exception
        $this->assertFalse($qa->thereAreErrors());
    }

    #[Test]
    public function setCharactersCountConfiguresSizeRestrictionCheckerFail(): void
    {
        $qa = new QA('Source', 'Target');
        $qa->setCharactersCount(100, new SegmentMetadataStruct(['meta_value' => 99, 'meta_key' => QA::SIZE_RESTRICTION]));
        $qa->performConsistencyCheck();

        // Should not throw exception
        $this->assertTrue($qa->thereAreErrors());
    }

    // ========== ICU Patterns Tests ==========

    #[Test]
    public function constructorWithIcuPatternValidatorAndIcuFlag(): void
    {
        $comparator = new MessagePatternComparator(
            'en-US',
            'it-IT',
            '{count, plural, one {# item} other {# items}}',
            '{count, plural, one {# elemento} other {# elementi}}'
        );
        $qa = new QA(
            '{count, plural, one {# item} other {# items}}',
            '{count, plural, one {# elemento} other {# elementi}}',
            $comparator,
            true
        );

        $this->assertFalse($qa->thereAreErrors());
    }

    #[Test]
    public function performConsistencyCheckWithIcuPatternsRunsIcuCheck(): void
    {
        $comparator = new MessagePatternComparator(
            'en-US',
            'it-IT',
            '{count, plural, one {# item} other {# items}}',
            '{count, plural, one {# elemento} other {# elementi}}'
        );
        $qa = new QA(
            '{count, plural, one {# item} other {# items}}',
            '{count, plural, one {# elemento} other {# elementi}}',
            $comparator,
            true
        );

        $qa->performConsistencyCheck();

        // ICU check runs - verify we can get errors without exception
        $this->assertIsArray($qa->getErrors());
    }

    #[Test]
    public function performConsistencyCheckWithInvalidIcuPatternHasNotices(): void
    {
        $comparator = new MessagePatternComparator(
            'en-US',
            'it-IT',
            '{count, plural, one {# item} other {# items}}',
            'Invalid pattern without plurals'
        );
        $qa = new QA(
            '{count, plural, one {# item} other {# items}}',
            'Invalid pattern without plurals',
            $comparator,
            true
        );

        $qa->performConsistencyCheck();

        // ICU validation should report issues
        $this->assertTrue($qa->thereAreNotices());
    }

    // ========== FeatureSet Tests ==========

    #[Test]
    public function setFeatureSetReturnsSelfForChaining(): void
    {
        $qa = new QA('<g id="1">Source</g>', '<g id="1">Target</g>');
        $featureSet = new \Model\FeaturesBase\FeatureSet();

        $result = $qa->setFeatureSet($featureSet);

        $this->assertSame($qa, $result);
    }

    #[Test]
    public function setFeatureSetConfiguresAllComponents(): void
    {
        $qa = new QA('<g id="1">Source</g>', '<g id="1">Target</g>');
        $featureSet = new \Model\FeaturesBase\FeatureSet();

        $qa->setFeatureSet($featureSet);
        $qa->performConsistencyCheck();

        // Should complete without errors
        $this->assertFalse($qa->thereAreErrors());
    }

    // ========== JSON Output Tests ==========

    #[Test]
    public function getWarningsJSONReturnsValidJson(): void
    {
        $qa = new QA('Source', 'Target');
        $qa->addError(QA::ERR_TAG_ORDER);

        $json = $qa->getWarningsJSON();
        $decoded = json_decode($json, true);

        $this->assertNotNull($decoded);
        $this->assertNotEmpty($decoded);
        // Find the error in the array
        $found = false;
        foreach ($decoded as $item) {
            if (isset($item['outcome']) && $item['outcome'] === QA::ERR_TAG_ORDER) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Should contain ERR_TAG_ORDER');
    }

    #[Test]
    public function getNoticesJSONReturnsValidJson(): void
    {
        $qa = new QA('Source', 'Target');
        $qa->addError(QA::ERR_WS_HEAD);

        $json = $qa->getNoticesJSON();
        $decoded = json_decode($json, true);

        $this->assertNotNull($decoded);
        $this->assertNotEmpty($decoded);
        // Just verify JSON is valid and non-empty
        $this->assertIsArray($decoded);
    }

    #[Test]
    public function getErrorsJSONWithNoErrorsReturnsValidJson(): void
    {
        $qa = new QA('Hello', 'Ciao');  // Valid plain text - no errors

        $json = $qa->getErrorsJSON();
        $decoded = json_decode($json, true);

        $this->assertNotNull($decoded);
        $this->assertIsArray($decoded);
    }

    // ========== BX/EX Validation Tests ==========

    #[Test]
    public function bxExNestedInGTagSourceNotTargetGeneratesError(): void
    {
        $source = '<g id="1"><bx id="2"/><ex id="2"/></g>';
        $target = '<bx id="2"/><ex id="2"/>';

        $qa = new QA($source, $target);
        $qa->performConsistencyCheck();

        $this->assertTrue($qa->thereAreErrors());
    }

    #[Test]
    public function bxExNestedInGTagTargetNotSourceGeneratesError(): void
    {
        $source = '<bx id="2"/><ex id="2"/>';
        $target = '<g id="1"><bx id="2"/><ex id="2"/></g>';

        $qa = new QA($source, $target);
        $qa->performConsistencyCheck();

        $this->assertTrue($qa->thereAreErrors());
    }

    #[Test]
    public function bxExCountMismatchGeneratesError(): void
    {
        $source = '<bx id="1"/><ex id="1"/><bx id="2"/><ex id="2"/>';
        $target = '<bx id="1"/><ex id="1"/>';

        $qa = new QA($source, $target);
        $qa->performConsistencyCheck();

        $this->assertTrue($qa->thereAreErrors());
    }

    // ========== performTagCheckOnly Edge Cases ==========

    #[Test]
    public function performTagCheckOnlyWithDOMExceptionReturnsErrors(): void
    {
        // Create a QA with invalid XML that causes DOM exception during structure preparation
        $qa = new QA('<g id="1">Unclosed', '<g id="1">Target</g>');

        $errors = $qa->performTagCheckOnly();

        $this->assertTrue($qa->thereAreErrors());
    }

    #[Test]
    public function performTagCheckOnlyWithBxExNestingDifference(): void
    {
        $source = '<g id="1"><bx id="2"/><ex id="2"/></g>';
        $target = '<bx id="2"/><ex id="2"/>';  // Different nesting

        $qa = new QA($source, $target);
        $qa->performTagCheckOnly();

        // Should detect the difference
        $this->assertTrue($qa->thereAreErrors() || $qa->thereAreWarnings() || $qa->thereAreNotices());
    }

    // ========== performConsistencyCheck Edge Cases ==========

    #[Test]
    public function performConsistencyCheckWithDOMExceptionReturnsEarly(): void
    {
        $qa = new QA('<g id="1">Unclosed', '<g id="1">Target</g>');

        $errors = $qa->performConsistencyCheck();

        // Should return early with errors from DOM loading
        $this->assertTrue($qa->thereAreErrors());
    }

    #[Test]
    public function performConsistencyCheckCallsGetTagDiffOnError(): void
    {
        $qa = new QA('<g id="1">Source</g><x id="2"/>', '<g id="1">Target</g>');
        $qa->performConsistencyCheck();

        // After error, getMalformedXmlStructs should have data
        $structs = $qa->getMalformedXmlStructs();
        $this->assertNotEmpty($structs['source']);
    }

    // ========== Tag Position Check Edge Cases ==========

    #[Test]
    public function performTagPositionCheckWithIdCheckDisabled(): void
    {
        $qa = new QA('<g id="1">S</g>', '<g id="2">T</g>');

        // Disable ID check - should not report ID mismatch
        $qa->performTagPositionCheck(
            '<g id="1">S</g>',
            '<g id="2">T</g>',
            false,  // performIdCheck disabled
            false   // performTagPositionsCheck disabled
        );

        // With both checks disabled, should not have warnings
        $this->assertFalse($qa->thereAreWarnings());
    }

    #[Test]
    public function performTagPositionCheckWithPositionCheckOnly(): void
    {
        $qa = new QA('<g id="1">S</g><g id="2">T</g>', '<g id="1">S</g><g id="2">T</g>');

        $qa->performTagPositionCheck(
            '<g id="1">S</g><g id="2">T</g>',
            '<g id="2">T</g><g id="1">S</g>',
            false,  // performIdCheck disabled
            true    // performTagPositionsCheck enabled
        );

        // Should report position warnings
        $this->assertTrue($qa->thereAreWarnings());
    }

    // ========== getTrgNormalized Edge Cases ==========

    #[Test]
    public function getTrgNormalizedWithNestedTags(): void
    {
        $qa = new QA(
            '<g id="1"><g id="2">Nested</g></g>',
            '<g id="1"><g id="2">Nidificato</g></g>'
        );
        $qa->prepareDOMStructures();

        $normalized = $qa->getTrgNormalized();

        $this->assertStringContainsString('<g id="1">', $normalized);
        $this->assertStringContainsString('<g id="2">', $normalized);
    }

    #[Test]
    public function getTrgNormalizedWithSpecialCharacters(): void
    {
        $qa = new QA("Source\ttext", "Target\ttext");
        $qa->prepareDOMStructures();

        $normalized = $qa->getTrgNormalized();

        // Tab should be converted back to entity
        $this->assertStringContainsString('&#x09;', $normalized);
    }

    #[Test]
    public function getTrgNormalizedWithEmptyTarget(): void
    {
        $qa = new QA('', '');
        $qa->prepareDOMStructures();

        $normalized = $qa->getTrgNormalized();

        $this->assertEquals('', $normalized);
    }

    // ========== Constants Tests ==========

    #[Test]
    public function errorConstantsMatchErrorManager(): void
    {
        $this->assertEquals(ErrorManager::ERR_NONE, QA::ERR_NONE);
        $this->assertEquals(ErrorManager::ERR_TAG_MISMATCH, QA::ERR_TAG_MISMATCH);
        $this->assertEquals(ErrorManager::ERR_TAG_ORDER, QA::ERR_TAG_ORDER);
        $this->assertEquals(ErrorManager::ERR_WS_HEAD, QA::ERR_WS_HEAD);
        $this->assertEquals(ErrorManager::ERR_SIZE_RESTRICTION, QA::ERR_SIZE_RESTRICTION);
    }

    #[Test]
    public function severityConstantsMatchErrorManager(): void
    {
        $this->assertEquals(ErrorManager::ERROR, QA::ERROR);
        $this->assertEquals(ErrorManager::WARNING, QA::WARNING);
        $this->assertEquals(ErrorManager::INFO, QA::INFO);
    }

    // ========== Segment Language Edge Cases ==========

    #[Test]
    public function setSourceSegLangToNullDoesNotThrow(): void
    {
        $qa = new QA('Source', 'Target');
        $qa->setSourceSegLang(null);

        $this->assertNull($qa->getSourceSegLang());
    }

    #[Test]
    public function setTargetSegLangToNullDoesNotThrow(): void
    {
        $qa = new QA('Source', 'Target');
        $qa->setTargetSegLang(null);

        $this->assertNull($qa->getTargetSegLang());
    }

    #[Test]
    public function cjkTargetLanguageHandlesWhitespace(): void
    {
        $qa = new QA('Text ', '文本');
        $qa->setTargetSegLang('zh-CN');
        $qa->performConsistencyCheck();

        // Should handle CJK target language properly
        $this->assertIsArray($qa->getNotices());
    }

    // ========== Multiple Symbol Checks ==========

    #[Test]
    public function dollarSignMismatchDetected(): void
    {
        $qa = new QA('Price: $100', 'Prezzo: 100');
        $qa->performConsistencyCheck();

        // Should have notices for symbol mismatch
        $this->assertTrue($qa->thereAreNotices());
    }

    #[Test]
    public function ampersandMismatchDetected(): void
    {
        $qa = new QA('Tom & Jerry', 'Tom e Jerry');
        $qa->performConsistencyCheck();

        // Should have notices for symbol mismatch
        $this->assertTrue($qa->thereAreNotices());
    }

    #[Test]
    public function atSignMismatchDetected(): void
    {
        $qa = new QA('Contact: test@example.com', 'Contatto: test.example.com');
        $qa->performConsistencyCheck();

        // Should have notices for symbol mismatch
        $this->assertTrue($qa->thereAreNotices());
    }

    #[Test]
    public function hashMismatchDetected(): void
    {
        $qa = new QA('Item #123', 'Articolo 123');
        $qa->performConsistencyCheck();

        // Should have notices for symbol mismatch
        $this->assertTrue($qa->thereAreNotices());
    }

    // ========== Whitespace Mismatch Tests ==========

    #[Test]
    public function newlineAtHeadMismatchDetected(): void
    {
        $qa = new QA("\nText after newline", "Text after newline");
        $qa->performConsistencyCheck();

        // Should have notices for whitespace mismatch
        $this->assertTrue($qa->thereAreNotices());
    }

    #[Test]
    public function newlineAtTailMismatchDetected(): void
    {
        $qa = new QA("Text before newline\n", "Text before newline");
        $qa->setSourceSegLang('en-US');
        $qa->performConsistencyCheck();

        // Should have notices for whitespace mismatch
        $this->assertTrue($qa->thereAreNotices());
    }

    // ========== Complex Tag Scenarios ==========

    #[Test]
    public function deeplyNestedTagsAreValidated(): void
    {
        $source = '<g id="1"><g id="2"><g id="3"><x id="4"/></g></g></g>';
        $target = '<g id="1"><g id="2"><g id="3"><x id="4"/></g></g></g>';

        $qa = new QA($source, $target);
        $qa->performConsistencyCheck();

        // Matching nested tags should not produce errors
        $this->assertFalse($qa->thereAreErrors());
    }

    #[Test]
    public function tagWithAttributesIsHandledCorrectly(): void
    {
        $source = '<g id="1" class="test" data-value="123">Content</g>';
        $target = '<g id="1" class="test" data-value="123">Contenuto</g>';

        $qa = new QA($source, $target);
        $qa->performConsistencyCheck();

        $this->assertFalse($qa->thereAreErrors());
    }

    #[Test]
    public function phTagWithDataRefIsHandledCorrectly(): void
    {
        $source = '<ph id="1" equiv-text="base64:PGJyPg==" dataRef="d1"/>';
        $target = '<ph id="1" equiv-text="base64:PGJyPg==" dataRef="d1"/>';

        $qa = new QA($source, $target);
        $qa->performConsistencyCheck();

        $this->assertFalse($qa->thereAreErrors());
    }

    // ========== Error Recovery Tests ==========

    #[Test]
    public function multipleConsistencyChecksCanBePerformed(): void
    {
        $qa = new QA('<g id="1">Source</g>', '<g id="1">Target</g>');

        $qa->performConsistencyCheck();
        $this->assertFalse($qa->thereAreErrors());

        // Second call should work
        $qa->performConsistencyCheck();
        $this->assertFalse($qa->thereAreErrors());
    }

    #[Test]
    public function performTagCheckOnlyFollowedByConsistencyCheck(): void
    {
        $qa = new QA('<g id="1">Source</g>', '<g id="1">Target</g>');

        $qa->performTagCheckOnly();
        $this->assertFalse($qa->thereAreErrors());

        $qa->performConsistencyCheck();
        $this->assertFalse($qa->thereAreErrors());
    }
}
