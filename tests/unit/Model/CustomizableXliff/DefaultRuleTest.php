<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 22/08/24
 * Time: 17:58
 *
 */

use Model\Xliff\DTO\DefaultRule;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class DefaultRuleTest extends AbstractTest
{

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function testStateQualifiers()
    {
        $stateQualifiers = [
            'exact-match',
            'id-match',
            'leveraged-glossary',
            'leveraged-repository',
            'rejected-grammar',
            'rejected-inaccurate',
            'rejected-length',
            'rejected-spelling',
            'tm-suggestion',
        ];

        foreach ($stateQualifiers as $stateQualifier) {
            $rule = new DefaultRule([$stateQualifier], 'pre-translated');

            $this->assertTrue($rule->isTranslated("testo", "traduzione"));
            $this->assertEquals('APPROVED', $rule->asEditorStatus());
            $this->assertEquals('ICE', $rule->asMatchType());
            $this->assertEquals(1, $rule->asStandardWordCount(1, ['ICE' => 100]));
            $this->assertEquals(1, $rule->asEquivalentWordCount(1, ['ICE' => 100]));
        }

        $stateQualifiers = [
            'fuzzy-match',
            'leveraged-inherited',
            'leveraged-mt',
            'leveraged-tm',
            'mt-suggestion',
        ];

        foreach ($stateQualifiers as $stateQualifier) {
            $rule = new DefaultRule([$stateQualifier], 'pre-translated');

            $this->assertEquals('NEW', $rule->asEditorStatus());
            $this->assertEquals('ICE', $rule->asMatchType());
            $this->assertEquals(1, $rule->asStandardWordCount(1, ['ICE' => 100]));
            $this->assertEquals(1, $rule->asEquivalentWordCount(1, ['ICE' => 100]));
        }
    }

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function testTranslatedWithState()
    {
        // needs-review-adaptation is considered translated
        $rule = new DefaultRule(['needs-review-adaptation'], 'pre-translated');

        $this->assertTrue($rule->isTranslated("testo", "traduzione"));
        $this->assertEquals('TRANSLATED', $rule->asEditorStatus());
        $this->assertEquals('ICE', $rule->asMatchType());
        $this->assertEquals(1, $rule->asStandardWordCount(1, ['ICE' => 100]));
        $this->assertEquals(1, $rule->asEquivalentWordCount(1, ['ICE' => 100]));
    }

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function testTranslatedWithStateQualifier()
    {
        $rule = new DefaultRule(['translated'], 'pre-translated');

        $this->assertTrue($rule->isTranslated("testo", "traduzione"));
        $this->assertEquals('TRANSLATED', $rule->asEditorStatus());
        $this->assertEquals('ICE', $rule->asMatchType());
        $this->assertEquals(1, $rule->asStandardWordCount(1, ['ICE' => 100]));
        $this->assertEquals(1, $rule->asEquivalentWordCount(1, ['ICE' => 100]));
    }

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function testTranslatedWithNoStates_old_behaviour()
    {
        $rule = new DefaultRule([], 'pre-translated');

        $this->assertTrue($rule->isTranslated("testo", "traduzione"));
        $this->assertEquals('APPROVED', $rule->asEditorStatus());
        $this->assertEquals('ICE', $rule->asMatchType());
        $this->assertEquals(1, $rule->asStandardWordCount(1, ['ICE' => 100]));
        $this->assertEquals(1, $rule->asEquivalentWordCount(1, ['ICE' => 100]));
    }

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function testNewWithNoStates_old_behaviour()
    {
        $rule = new DefaultRule([], 'pre-translated');

        $this->assertFalse($rule->isTranslated("testo", "testo"));
    }

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function testNew_exception()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("DefaultRule is designed to be pre-translated only.");
        $this->expectExceptionCode(500);

        new DefaultRule([], 'new');
    }

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function testNewWithStateQualifier()
    {
        $rule = new DefaultRule(['fuzzy-match'], 'pre-translated');

        $this->assertFalse($rule->isTranslated("testo", "traduzione"));
    }

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function testNewWithState()
    {
        $rule = new DefaultRule(['initial'], 'pre-translated');

        $this->assertFalse($rule->isTranslated("testo", "traduzione"));
    }

}