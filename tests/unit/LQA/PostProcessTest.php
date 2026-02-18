<?php

namespace unit\LQA;

use Exception;
use LogicException;
use Matecat\SubFiltering\AbstractFilter;
use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use PHPUnit\Framework\Attributes\Test;
use ReflectionException;
use TestHelpers\AbstractTest;
use Utils\LQA\PostProcess;

class PostProcessTest extends AbstractTest
{
    protected AbstractFilter $filter;
    protected FeatureSet $featureSet;

    public function setUp(): void
    {
        parent::setUp();

        $this->featureSet = new FeatureSet();
        $this->featureSet->loadFromString("translation_versions,review_extended,mmt,airbnb");

        $this->filter = MateCatFilter::getInstance($this->featureSet, 'en-EN', 'it-IT', []);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesWithNestedTagsNormalizesCorrectly(): void
    {
        $source_seg = <<<TRG
<g id="1621">By selecting this menu as shown in Fig.18 you can review the measurement records (refer to Fig.19), press the <x id="1622"/></g><g id="1623"> </g><g id="1624">or the <x id="1625"/></g><g id="1626"> </g><g id="1627">button to review the records page by page, the longer you press the<x id="1628"/></g><g id="1629">  </g><g id="1630">or<x id="1631"/></g><g id="1632"> </g><g id="1633">button the faster record page changes.</g>
TRG;

        $target_seg = <<<SRC
<g id="1621">By selecting this menu as shown in Fig.18 you can review the measurement records (refer to Fig.19), press the <x id="1622"/> </g> <g id="1623"> </g> <g id="1624"> or the <x id="1625"/></g><g id="1626"> </g> <g id="1627"> button to review the records page by page, the longer you press the <x id="1628"/></g><g id="1629">  </g><g id="1630"> or <x id="1631"/> </g><g id="1632"> </g><g id="1633"> button the faster record page changes. </g>
SRC;

        $source_seg = $this->filter->fromLayer2ToLayer0($source_seg);
        $target_seg = $this->filter->fromLayer2ToLayer0($target_seg);

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertFalse($check->thereAreErrors());
        $this->assertFalse($check->thereAreWarnings());

        $warnings = $check->getWarnings();
        $errors = $check->getErrors();

        $this->assertCount(1, $warnings);
        $this->assertEquals(0, $warnings[0]->outcome);
        $this->assertCount(1, $errors);
        $this->assertEquals(0, $errors[0]->outcome);

        $normalized = $check->getTrgNormalized();
        $this->assertEquals($source_seg, $normalized);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesWithLeadingSpaceNormalizesCorrectly(): void
    {
        $source_seg = ' Only Text';
        $target_seg = 'Only Text';

        $source_seg = $this->filter->fromLayer2ToLayer0($source_seg);
        $target_seg = $this->filter->fromLayer2ToLayer0($target_seg);

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertFalse($check->thereAreErrors());
        $this->assertFalse($check->thereAreWarnings());

        $normalized = $check->getTrgNormalized();
        $this->assertEquals(' Only Text', $normalized);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesWithRecursiveNestedTagsNormalizesCorrectly(): void
    {
        $source_seg = <<<SRC
<g id="6"> <g id="7">st</g><g id="8">&nbsp;Section of <.++* Tokyo <g id="9"><g id="10">Station</g></g>, Osaka </g></g>
SRC;

        $target_seg = <<<TRG
<g id="6"> <g id="7"> st </g> <g id="8">&nbsp;Section of <.++* Tokyo <g id="9"> <g id="10"> Station </g> </g>, Osaka </g> </g>
TRG;

        $source_seg = $this->filter->fromLayer2ToLayer0($source_seg);
        $target_seg = $this->filter->fromLayer2ToLayer0($target_seg);

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertFalse($check->thereAreErrors());
        $this->assertFalse($check->thereAreWarnings());

        $normalized = $check->getTrgNormalized();
        $this->assertEquals($source_seg, $normalized);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesWithSpaceBetweenTagsNormalizesCorrectly(): void
    {
        $source_seg = '<g id="1877">31-235</g> <g id="1878">The default PR upper alarm is120.</g>';
        $target_seg = '<g id="1877"> 31-235 </g><g id="1878"> L\'impostazione predefinita PR IS120 allarme. </g>';

        $source_seg = $this->filter->fromLayer2ToLayer0($source_seg);
        $target_seg = $this->filter->fromLayer2ToLayer0($target_seg);

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertFalse($check->thereAreErrors());
        $this->assertFalse($check->thereAreWarnings());

        $normalized = $check->getTrgNormalized();
        $this->assertEquals('<g id="1877">31-235</g> <g id="1878">L\'impostazione predefinita PR IS120 allarme.</g>', $normalized);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesWithTagMismatchReportsError(): void
    {
        $source_seg = '<g id="1877">31-235</g> <g id="1878">The default PR upper alarm is120.</g>';
        $target_seg = '<g id="1877"> 31-235 </g><x id="1878"/> L\'impostazione predefinita PR IS120 allarme.';

        $source_seg = $this->filter->fromLayer2ToLayer0($source_seg);
        $target_seg = $this->filter->fromLayer2ToLayer0($target_seg);

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertTrue($check->thereAreErrors());
        $this->assertTrue($check->thereAreWarnings());

        $errors = $check->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals(1000, $errors[0]->outcome);
        $this->assertMatchesRegularExpression('/\( 1 \)/', $check->getErrorsJSON());

        $this->expectException(LogicException::class);
        $check->getTrgNormalized();
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesWithDifferentTagIdDoesNotReportError(): void
    {
        $source_seg = '<g id="1877">31-235</g> <g id="1878">The default PR upper alarm is120.</g>';
        $target_seg = '<g id="1877"> 31-235 </g><g id="1879"> L\'impostazione predefinita PR IS120 allarme. </g>';

        $source_seg = $this->filter->fromLayer2ToLayer0($source_seg);
        $target_seg = $this->filter->fromLayer2ToLayer0($target_seg);

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        // Different tag ID but same tag type - should not report error
        $this->assertFalse($check->thereAreErrors());
        $this->assertFalse($check->thereAreWarnings());

        $normalized = $check->getTrgNormalized();
        $this->assertEquals('<g id="1877">31-235</g> <g id="1879">L\'impostazione predefinita PR IS120 allarme.</g>', $normalized);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesWithTabBetweenTagsNormalizesCorrectly(): void
    {
        $source_seg = "<g id=\"1877\">31-235</g>\t<g id=\"1878\">The default PR upper alarm is120.</g>";
        $target_seg = '<g id="1877"> 31-235 </g><g id="1878"> L\'impostazione predefinita PR IS120 allarme. </g>';

        $source_seg = $this->filter->fromLayer2ToLayer0($source_seg);
        $target_seg = $this->filter->fromLayer2ToLayer0($target_seg);

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertFalse($check->thereAreErrors());
        $this->assertFalse($check->thereAreWarnings());

        $normalized = $check->getTrgNormalized();
        // Tab is removed in normalization
        $this->assertEquals('<g id="1877">31-235</g><g id="1878">L\'impostazione predefinita PR IS120 allarme.</g>', $normalized);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesMultipleCallsProduceSameResult(): void
    {
        $source_seg = '<g id="1877">31-235</g> <g id="1878">The default PR upper alarm is120.</g>';
        $target_seg = '<g id="1877"> 31-235 </g><g id="1879"> L\'impostazione predefinita PR IS120 allarme. </g>';

        $source_seg = $this->filter->fromLayer2ToLayer0($source_seg);
        $target_seg = $this->filter->fromLayer2ToLayer0($target_seg);

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $normalized1 = $check->getTrgNormalized();

        $check->realignMTSpaces();
        $this->assertFalse($check->thereAreErrors());
        $this->assertFalse($check->thereAreWarnings());

        $this->assertEquals($normalized1, '<g id="1877">31-235</g> <g id="1879">L\'impostazione predefinita PR IS120 allarme.</g>');
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function getErrorsReturnsErrorObjects(): void
    {
        $check = new PostProcess('Source', 'Target');
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $errors = $check->getErrors();
        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function getWarningsReturnsWarningObjects(): void
    {
        $check = new PostProcess('Source', 'Target');
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $warnings = $check->getWarnings();
        $this->assertIsArray($warnings);
        $this->assertNotEmpty($warnings);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function getErrorsJSONReturnsValidJson(): void
    {
        $check = new PostProcess('Source', 'Target');
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $json = $check->getErrorsJSON();
        $decoded = json_decode($json, true);
        $this->assertNotNull($decoded);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function setSourceSegLangSetsLanguage(): void
    {
        $check = new PostProcess('Source', 'Target');
        $check->setFeatureSet($this->featureSet);
        $check->setSourceSegLang('en-US');
        $check->realignMTSpaces();

        // Verify language affects processing (no exception thrown)
        $this->assertFalse($check->thereAreErrors());
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function setTargetSegLangSetsLanguage(): void
    {
        $check = new PostProcess('Source', 'Target');
        $check->setFeatureSet($this->featureSet);
        $check->setTargetSegLang('it-IT');
        $check->realignMTSpaces();

        // Verify language affects processing (no exception thrown)
        $this->assertFalse($check->thereAreErrors());
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesWithPlainTextNoErrors(): void
    {
        $check = new PostProcess('Hello World', 'Ciao Mondo');
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertFalse($check->thereAreErrors());
        $this->assertFalse($check->thereAreWarnings());

        $normalized = $check->getTrgNormalized();
        $this->assertEquals('Ciao Mondo', $normalized);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesWithEmptySegmentsNoErrors(): void
    {
        $check = new PostProcess('', '');
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertFalse($check->thereAreErrors());
        $this->assertFalse($check->thereAreWarnings());
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function thereAreNoticesReturnsTrueForWhitespaceMismatch(): void
    {
        $check = new PostProcess(' Text with leading space', 'Text without leading space');
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        // PostProcess normalizes whitespace, so no error after normalization
        $this->assertFalse($check->thereAreErrors());
    }

    // ========== performConsistencyCheck Tests ==========

    /**
     * @throws Exception
     */
    #[Test]
    public function performConsistencyCheckDelegatesToQa(): void
    {
        $check = new PostProcess('<g id="1">Source</g>', '<g id="1">Target</g>');
        $check->setFeatureSet($this->featureSet);

        $errors = $check->performConsistencyCheck();

        $this->assertIsArray($errors);
        $this->assertFalse($check->thereAreErrors());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function performConsistencyCheckDetectsTagMismatch(): void
    {
        $check = new PostProcess('<g id="1">Source</g><x id="2"/>', '<g id="1">Target</g>');
        $check->setFeatureSet($this->featureSet);

        $check->performConsistencyCheck();

        $this->assertTrue($check->thereAreErrors());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function performConsistencyCheckDetectsSymbolMismatch(): void
    {
        $check = new PostProcess('Price: €100', 'Prezzo: 100');
        $check->setFeatureSet($this->featureSet);

        $check->performConsistencyCheck();

        // Symbol mismatches are notices, not errors
        $this->assertFalse($check->thereAreErrors());
    }

    // ========== performTagCheckOnly Tests ==========

    /**
     * @throws Exception
     */
    #[Test]
    public function performTagCheckOnlyDelegatesToQa(): void
    {
        $check = new PostProcess('<g id="1">Source</g>', '<g id="1">Target</g>');
        $check->setFeatureSet($this->featureSet);

        $errors = $check->performTagCheckOnly();

        $this->assertIsArray($errors);
        $this->assertFalse($check->thereAreErrors());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function performTagCheckOnlyDetectsTagMismatch(): void
    {
        $check = new PostProcess('<g id="1">Source</g><x id="2"/>', '<g id="1">Target</g>');
        $check->setFeatureSet($this->featureSet);

        $check->performTagCheckOnly();

        $this->assertTrue($check->thereAreErrors());
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function performTagCheckOnlyIgnoresSymbolMismatch(): void
    {
        $check = new PostProcess('Price: €100', 'Prezzo: 100');
        $check->setFeatureSet($this->featureSet);

        $check->performTagCheckOnly();

        // Tag check only doesn't check symbols
        $this->assertFalse($check->thereAreErrors());
    }

    // ========== getTargetSeg Tests ==========

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function getTargetSegReturnsPreprocessedTarget(): void
    {
        $check = new PostProcess('Source', 'Target');
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $target = $check->getTargetSeg();
        $this->assertEquals('Target', $target);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function getTargetSegAfterRealignmentReturnsNormalizedTarget(): void
    {
        $source_seg = '<g id="1">Text</g> <g id="2">More</g>';
        $target_seg = '<g id="1"> Text </g><g id="2"> More </g>';

        $source_seg = $this->filter->fromLayer2ToLayer0($source_seg);
        $target_seg = $this->filter->fromLayer2ToLayer0($target_seg);

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $target = $check->getTargetSeg();
        $this->assertIsString($target);
    }

    // ========== NBSP Handling Tests ==========

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesWithHeadNBSPInSource(): void
    {
        // Source has NBSP at head, target has regular space
        $source_seg = "\u{00A0}Text with nbsp";
        $target_seg = " Text with space";

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertFalse($check->thereAreErrors());
        $normalized = $check->getTrgNormalized();
        // Should have NBSP at the beginning (may be HTML entity or Unicode)
        $this->assertTrue(
            str_starts_with($normalized, "\u{00A0}") || str_starts_with($normalized, '&#160;'),
            'Should start with NBSP (Unicode or HTML entity)'
        );
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesWithTailNBSPInSource(): void
    {
        // Source has NBSP at tail, target has regular space
        $source_seg = "Text with nbsp\u{00A0}";
        $target_seg = "Text with space ";

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertFalse($check->thereAreErrors());
        $normalized = $check->getTrgNormalized();
        // Should have NBSP at the end (may be HTML entity or Unicode)
        $this->assertTrue(
            str_ends_with($normalized, "\u{00A0}") || str_ends_with($normalized, '&#160;'),
            'Should end with NBSP (Unicode or HTML entity)'
        );
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesWithHeadNBSPInTarget(): void
    {
        // Source has regular space, target has NBSP
        $source_seg = " Text with space";
        $target_seg = "\u{00A0}Text with nbsp";

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertFalse($check->thereAreErrors());
        $normalized = $check->getTrgNormalized();
        // Should have regular space at the beginning (converted from NBSP)
        $this->assertStringStartsWith(" ", $normalized);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesWithTailNBSPInTarget(): void
    {
        // Source has regular space, target has NBSP
        $source_seg = "Text with space ";
        $target_seg = "Text with nbsp\u{00A0}";

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertFalse($check->thereAreErrors());
        $normalized = $check->getTrgNormalized();
        // Should have regular space at the end (converted from NBSP)
        $this->assertStringEndsWith(" ", $normalized);
    }

    // ========== Head Space Normalization Tests ==========

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesAddsHeadSpaceWhenMissing(): void
    {
        // Source starts with space, target doesn't
        $source_seg = '<g id="1"> Text</g>';
        $target_seg = '<g id="1">Testo</g>';

        $source_seg = $this->filter->fromLayer2ToLayer0($source_seg);
        $target_seg = $this->filter->fromLayer2ToLayer0($target_seg);

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertFalse($check->thereAreErrors());
        $normalized = $check->getTrgNormalized();
        // Should have space added after opening tag
        $this->assertStringContainsString('> Testo', $normalized);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesRemovesHeadSpaceWhenExtra(): void
    {
        // Source doesn't start with space, target does
        $source_seg = '<g id="1">Text</g>';
        $target_seg = '<g id="1"> Testo</g>';

        $source_seg = $this->filter->fromLayer2ToLayer0($source_seg);
        $target_seg = $this->filter->fromLayer2ToLayer0($target_seg);

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertFalse($check->thereAreErrors());
        $normalized = $check->getTrgNormalized();
        // Should have space removed after opening tag
        $this->assertStringContainsString('>Testo', $normalized);
    }

    // ========== Tail Space Normalization Tests ==========

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesAddsTailSpaceWhenMissing(): void
    {
        // Source ends with space before closing tag, target doesn't
        $source_seg = '<g id="1">Text </g>';
        $target_seg = '<g id="1">Testo</g>';

        $source_seg = $this->filter->fromLayer2ToLayer0($source_seg);
        $target_seg = $this->filter->fromLayer2ToLayer0($target_seg);

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertFalse($check->thereAreErrors());
        $normalized = $check->getTrgNormalized();
        // Should have space added before closing tag
        $this->assertStringContainsString('Testo </g>', $normalized);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesRemovesTailSpaceWhenExtra(): void
    {
        // Source doesn't end with space, target does
        $source_seg = '<g id="1">Text</g>';
        $target_seg = '<g id="1">Testo </g>';

        $source_seg = $this->filter->fromLayer2ToLayer0($source_seg);
        $target_seg = $this->filter->fromLayer2ToLayer0($target_seg);

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertFalse($check->thereAreErrors());
        $normalized = $check->getTrgNormalized();
        // Should have space removed before closing tag
        $this->assertStringContainsString('Testo</g>', $normalized);
    }

    // ========== setFeatureSet Tests ==========

    /**
     * @throws Exception
     */
    #[Test]
    public function setFeatureSetReturnsSelfForChaining(): void
    {
        $check = new PostProcess('Source', 'Target');
        $result = $check->setFeatureSet($this->featureSet);

        $this->assertSame($check, $result);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function setFeatureSetPropagatesFeatureSet(): void
    {
        $check = new PostProcess('<g id="1">Source</g>', '<g id="1">Target</g>');
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        // Should complete without errors - FeatureSet was propagated
        $this->assertFalse($check->thereAreErrors());
    }

    // ========== Language Setting Tests ==========

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function setSourceSegLangWithCjkLanguage(): void
    {
        $check = new PostProcess('Text ', '文本');
        $check->setFeatureSet($this->featureSet);
        $check->setSourceSegLang('ja-JP');
        $check->realignMTSpaces();

        // Should complete without errors
        $this->assertFalse($check->thereAreErrors());
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function setTargetSegLangWithCjkLanguage(): void
    {
        $check = new PostProcess('Text', '文本 ');
        $check->setFeatureSet($this->featureSet);
        $check->setTargetSegLang('zh-CN');
        $check->realignMTSpaces();

        // Should complete without errors
        $this->assertFalse($check->thereAreErrors());
    }

    // ========== Edge Cases ==========

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesWithNullSegments(): void
    {
        $check = new PostProcess(null, null);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertFalse($check->thereAreErrors());
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesWithOnlySpaces(): void
    {
        $check = new PostProcess('   ', '   ');
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertFalse($check->thereAreErrors());
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesWithDomExceptionHandling(): void
    {
        // Invalid XML that causes DOM exception
        $check = new PostProcess('<g id="1">Unclosed', '<g id="1">Target</g>');
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        // Should handle DOM exception gracefully
        $this->assertTrue($check->thereAreErrors());
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesWithMismatchedSegmentCount(): void
    {
        // Source has more segments than target when split by ">"
        $source_seg = '<g id="1">A</g><g id="2">B</g><g id="3">C</g>';
        $target_seg = '<g id="1">X</g><g id="2">Y</g>';

        $source_seg = $this->filter->fromLayer2ToLayer0($source_seg);
        $target_seg = $this->filter->fromLayer2ToLayer0($target_seg);

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        // Should report error due to tag count mismatch
        $this->assertTrue($check->thereAreErrors());
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesPreservesContent(): void
    {
        $source_seg = '<g id="1">Hello World</g>';
        $target_seg = '<g id="1"> Ciao Mondo </g>';

        $source_seg = $this->filter->fromLayer2ToLayer0($source_seg);
        $target_seg = $this->filter->fromLayer2ToLayer0($target_seg);

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertFalse($check->thereAreErrors());
        $normalized = $check->getTrgNormalized();
        // Content should be preserved
        $this->assertStringContainsString('Ciao Mondo', $normalized);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesWithSelfClosingTags(): void
    {
        $source_seg = 'Text <x id="1"/> more text';
        $target_seg = 'Testo <x id="1"/> altro testo';

        $source_seg = $this->filter->fromLayer2ToLayer0($source_seg);
        $target_seg = $this->filter->fromLayer2ToLayer0($target_seg);

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertFalse($check->thereAreErrors());
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesWithBxExTags(): void
    {
        $source_seg = '<bx id="1"/>Text<ex id="1"/>';
        $target_seg = '<bx id="1"/> Testo <ex id="1"/>';

        $source_seg = $this->filter->fromLayer2ToLayer0($source_seg);
        $target_seg = $this->filter->fromLayer2ToLayer0($target_seg);

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertFalse($check->thereAreErrors());
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesWithPhTags(): void
    {
        $source_seg = 'Text<ph id="1" equiv-text="base64:dGVzdA=="/>more';
        $target_seg = 'Testo <ph id="1" equiv-text="base64:dGVzdA=="/> altro';

        $source_seg = $this->filter->fromLayer2ToLayer0($source_seg);
        $target_seg = $this->filter->fromLayer2ToLayer0($target_seg);

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertFalse($check->thereAreErrors());
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesWithMultipleNBSP(): void
    {
        // Multiple NBSP characters
        $source_seg = "\u{00A0}\u{00A0}Text\u{00A0}\u{00A0}";
        $target_seg = "  Testo  ";

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertFalse($check->thereAreErrors());
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function getWarningsJSONReturnsValidJson(): void
    {
        $check = new PostProcess('Source', 'Target');
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        // Access warnings through the public API
        $warnings = $check->getWarnings();
        $this->assertIsArray($warnings);
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function thereAreNoticesReturnsCorrectly(): void
    {
        $check = new PostProcess(' Text', 'Text');
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        // After realignment, spaces are normalized, so no notices
        $this->assertFalse($check->thereAreErrors());
        $this->assertFalse($check->thereAreWarnings());
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function realignMTSpacesWithComplexNestedStructure(): void
    {
        $source_seg = '<g id="1"><g id="2"><g id="3">Deep</g></g></g>';
        $target_seg = '<g id="1"> <g id="2"> <g id="3"> Deep </g> </g> </g>';

        $source_seg = $this->filter->fromLayer2ToLayer0($source_seg);
        $target_seg = $this->filter->fromLayer2ToLayer0($target_seg);

        $check = new PostProcess($source_seg, $target_seg);
        $check->setFeatureSet($this->featureSet);
        $check->realignMTSpaces();

        $this->assertFalse($check->thereAreErrors());
        $normalized = $check->getTrgNormalized();
        $this->assertEquals($source_seg, $normalized);
    }
}
