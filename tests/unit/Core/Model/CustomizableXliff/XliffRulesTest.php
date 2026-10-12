<?php

namespace Matecat\Core\Model\CustomizableXliff;
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 19/08/24
 * Time: 17:42
 *
 */

use DomainException;
use LogicException;
use Matecat\TestHelpers\AbstractTest;
use Model\Xliff\DTO\Xliff12Rule;
use Model\Xliff\DTO\Xliff20Rule;
use PHPUnit\Framework\Attributes\Test;

class XliffRulesTest extends AbstractTest
{

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function testTranslated()
    {
        $rule = new Xliff12Rule(['needs-l10n'], 'pre-translated', 'translated', 'tm_100');

        $this->assertTrue($rule->isTranslated("testo", "traduzione"));
        $this->assertEquals('TRANSLATED', $rule->asEditorStatus());
        $this->assertEquals('100%', $rule->asMatchType());
        $this->assertEquals(1, $rule->asStandardWordCount(1, ['100%' => 100]));
        $this->assertEquals(0.2, $rule->asEquivalentWordCount(1, ['100%' => 20]));
    }

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function testTranslatedWithoutEditor()
    {
        $rule = new Xliff12Rule(['needs-l10n'], 'pre-translated');

        $this->assertTrue($rule->isTranslated("testo", "traduzione"));
        $this->assertEquals('NEW', $rule->asEditorStatus());
        $this->assertEquals('ICE', $rule->asMatchType());
        $this->assertEquals(0, $rule->asStandardWordCount(1, ['ICE' => 0]));
    }

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function testNotTranslatedAndException()
    {
        $rule = new Xliff12Rule(['final'], 'new');

        $this->assertFalse($rule->isTranslated("testo", "traduzione"));
        $this->assertEquals('NEW', $rule->asEditorStatus());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Invalid call for rule. A `new` analysis rule do not have a match category.");
        $this->expectExceptionCode(500);
        $rule->asMatchType();
    }

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function testNotTranslatedAndWordCountException()
    {
        $rule = new Xliff12Rule(['final'], 'new');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Invalid call for rule. A `new` analysis rule do not have a defined word count.");
        $this->expectExceptionCode(500);

        $this->assertEquals(1, $rule->asStandardWordCount(1, []));
    }

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function testWrongState()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Wrong state value");
        $this->expectExceptionCode(400);

        new Xliff20Rule(['signed-off'], 'new');
    }

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function testWrongAnalysisValue()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Wrong analysis value");
        $this->expectExceptionCode(400);

        new Xliff12Rule(['signed-off'], 'pippo');
    }

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function testWrongEditorValue()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Wrong editor value");
        $this->expectExceptionCode(400);

        new Xliff20Rule(['final'], 'pre-translated', 'pippo');
    }

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function testWrongEditorValue2()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Wrong editor value. A `new` rule can not have an assigned editor value.");
        $this->expectExceptionCode(400);

        new Xliff20Rule(['final'], 'new', 'pippo');
    }

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function testWrongMatchCategory()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Wrong match_category value");
        $this->expectExceptionCode(400);

        new Xliff20Rule(['final'], 'pre-translated', 'translated', 'PIPPO');
    }

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function testWrongMatchCategory2()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Wrong match_category value. A `new` rule can not have an assigned match category.");
        $this->expectExceptionCode(400);

        new Xliff20Rule(['final'], 'new', null, 'tm_100');
    }

    /**
     * @test
     * @return void
     */
    #[Test]
    public function testStatesSeparationFor12()
    {
        $rule = new Xliff12Rule([ /*state */ 'needs-l10n', /*state-qualifier */ 'exact-match'], 'pre-translated', 'translated', 'tm_100');
        $this->assertEquals(['needs-l10n'], $rule->getStates('states'));
        $this->assertEquals(['exact-match'], $rule->getStates('state-qualifiers'));
        $this->assertEquals(['needs-l10n', 'exact-match'], $rule->getStates());
    }

    #[Test]
    public function testNoStateIsKeptApartFromRealStatesFor12()
    {
        $rule = new Xliff12Rule(['needs-l10n', 'no-state', 'exact-match'], 'pre-translated', 'translated', 'ice');
        $this->assertTrue($rule->isNoStateRule());
        $this->assertEquals(['needs-l10n'], $rule->getStates('states'));
        $this->assertEquals(['exact-match'], $rule->getStates('state-qualifiers'));
        $this->assertEquals(['no-state'], $rule->getStates('no-state'));
        $this->assertEquals(['no-state', 'needs-l10n', 'exact-match'], $rule->getStates());
    }

    #[Test]
    public function testNoStateIsAcceptedFor20()
    {
        $rule = new Xliff20Rule(['no-state'], 'new');
        $this->assertTrue($rule->isNoStateRule());
        $this->assertEquals([], $rule->getStates('states'));
        $this->assertEquals(['states' => ['no-state'], 'analysis' => 'new'], $rule->jsonSerialize());
    }

    #[Test]
    public function testNoStateRoundTripsThroughJson()
    {
        $rule = Xliff12Rule::fromArray(['states' => ['translated', 'no-state'], 'analysis' => 'pre-translated', 'editor' => 'approved', 'match_category' => 'ice']);
        $copy = Xliff12Rule::fromArray(json_decode(json_encode($rule), true));
        $this->assertEquals($rule, $copy);
        $this->assertEquals(['no-state', 'translated'], $copy->jsonSerialize()['states']);
    }

    #[Test]
    public function testARuleWithoutNoStateIsNotANoStateRule()
    {
        $this->assertFalse((new Xliff12Rule(['translated'], 'pre-translated', 'translated'))->isNoStateRule());
    }

}
