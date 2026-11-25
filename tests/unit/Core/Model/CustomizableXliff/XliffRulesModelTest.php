<?php

namespace Matecat\Core\Model\CustomizableXliff;
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 22/08/24
 * Time: 17:18
 *
 */

use DomainException;
use Matecat\TestHelpers\AbstractTest;
use Model\Xliff\DTO\AbstractXliffRule;
use Model\Xliff\DTO\DefaultRule;
use Model\Xliff\DTO\Xliff12Rule;
use Model\Xliff\DTO\Xliff20Rule;
use Model\Xliff\DTO\XliffRulesModel;
use PHPUnit\Framework\Attributes\Test;

class XliffRulesModelTest extends AbstractTest
{

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function shouldNotAcceptDuplicatedStates()
    {
        $rulesModel = new XliffRulesModel();

        $rule1 = new Xliff12Rule(['needs-l10n'], 'pre-translated', 'translated', 'tm_100');
        $rule2 = new Xliff12Rule(['exact-match', 'needs-l10n'], 'pre-translated', 'translated', 'tm_100');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("The same state/state-qualifier cannot be used in two different rules: " . implode("", ['needs-l10n']));
        $this->expectExceptionCode(400);

        $rulesModel->addRule($rule1);
        $rulesModel->addRule($rule2);
    }

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function shouldGetTheRightRule()
    {
        $rulesModel = new XliffRulesModel();

        $defaultRule = new DefaultRule(['translated'], AbstractXliffRule::_ANALYSIS_PRE_TRANSLATED, null, null);

        $rule1 = new Xliff12Rule(['needs-l10n', 'translated'], 'pre-translated', 'translated', 'tm_100');
        $rule2 = new Xliff12Rule(['exact-match', 'needs-adaptation'], 'new');

        $rulesModel->addRule($rule1);
        $rulesModel->addRule($rule2);

        $this->assertEquals($rule2, $rulesModel->getMatchingRule(1, null, 'exact-match'));
        $this->assertEquals($rule1, $rulesModel->getMatchingRule(1, 'translated'));
        $this->assertEquals($defaultRule, $rulesModel->getMatchingRule(1, null, 'translated')); // we are passing a state as state-qualifier
        $this->assertEquals($defaultRule, $rulesModel->getMatchingRule(2, 'translated')); // there is not 2.0 rule defined

    }

    /**
     * @test
     * @throws Exception
     */
    #[Test]
    public function shouldLoadFromArray()
    {
        $array = [
            "xliff12" => [
                [
                    "states" => [
                        "new",
                        "translated",
                        "needs-review-adaptation",
                        "needs-review-l10n"
                    ],
                    "analysis" => "new"
                ],
                [
                    "states" => [
                        "signed-off",
                        "final"
                    ],
                    "analysis" => "pre-translated",
                    "editor" => "translated",
                    "match_category" => "tm_50_74"
                ],
                [
                    "states" => [
                        "exact-match",
                        "id-match",
                        "leveraged-repository",
                        "mt-suggestion"
                    ],
                    "analysis" => "pre-translated",
                    "editor" => "approved",
                    "match_category" => "ice"
                ]
            ],
            "xliff20" => [
                [
                    "states" => [
                        "final"
                    ],
                    "analysis" => "new"
                ]
            ]
        ];

        $rulesModel = XliffRulesModel::fromArray($array);

        $this->assertNotEmpty($rulesModel);
        $this->assertEquals($array, $rulesModel->getArrayCopy());


        $rule1 = new Xliff20Rule(['final'], 'new');
        $rule2 = new Xliff12Rule([
            "exact-match",
            "id-match",
            "leveraged-repository",
            "mt-suggestion"
        ], 'pre-translated', 'approved', 'ice');

        $this->assertEquals($rule1, $rulesModel->getMatchingRule(2, 'final'));
        $this->assertEquals($rule2, $rulesModel->getMatchingRule(1, null, 'exact-match'));
        $this->assertEquals(json_encode($rule2), json_encode($rulesModel->getMatchingRule(1, null, "id-match")));
    }

    #[Test]
    public function shouldRejectAnUnknownRuleType()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Invalid rule: xliff99");
        $this->expectExceptionCode(400);

        XliffRulesModel::fromArray(["xliff99" => []]);
    }

    #[Test]
    public function shouldRejectAnUnknownVersionNumber()
    {
        $rulesModel = new XliffRulesModel();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Invalid version: 3");
        $this->expectExceptionCode(400);

        $rulesModel->getRulesForVersion(3);
    }

    #[Test]
    public function shouldUseTheNoStateRuleForSegmentsWithoutState()
    {
        $rulesModel = new XliffRulesModel();
        $noStateRule = new Xliff12Rule(['no-state'], 'pre-translated', 'translated', 'ice');
        $rulesModel->addRule(new Xliff12Rule(['translated'], 'pre-translated', 'approved', 'ice'));
        $rulesModel->addRule($noStateRule);

        $this->assertSame($noStateRule, $rulesModel->getMatchingRule(1));
    }

    #[Test]
    public function shouldUseTheNoStateRuleAsFallbackForUnmatchedStates()
    {
        $rulesModel = new XliffRulesModel();
        $noStateRule = new Xliff20Rule(['no-state'], 'new');
        $rulesModel->addRule(new Xliff20Rule(['final'], 'pre-translated', 'approved2', 'ice'));
        $rulesModel->addRule($noStateRule);

        $this->assertSame($noStateRule, $rulesModel->getMatchingRule(2, 'reviewed'));
        $this->assertSame($noStateRule, $rulesModel->getMatchingRule(2, 'x-custom'));
    }

    #[Test]
    public function shouldPreferAnExplicitStateOverTheNoStateRuleWhateverTheOrder()
    {
        $rulesModel = new XliffRulesModel();
        $noStateRule = new Xliff12Rule(['no-state'], 'new');
        $translatedRule = new Xliff12Rule(['translated', 'exact-match'], 'pre-translated', 'translated', 'ice');
        $rulesModel->addRule($noStateRule);
        $rulesModel->addRule($translatedRule);

        $this->assertSame($translatedRule, $rulesModel->getMatchingRule(1, 'translated'));
        $this->assertSame($translatedRule, $rulesModel->getMatchingRule(1, 'new', 'exact-match'));
        $this->assertSame($noStateRule, $rulesModel->getMatchingRule(1, 'new'));
    }

    #[Test]
    public function shouldKeepTheDefaultRuleWithoutANoStateRule()
    {
        $rulesModel = new XliffRulesModel();
        $rulesModel->addRule(new Xliff12Rule(['translated'], 'pre-translated', 'translated', 'ice'));

        $this->assertInstanceOf(DefaultRule::class, $rulesModel->getMatchingRule(1));
        $this->assertInstanceOf(DefaultRule::class, $rulesModel->getMatchingRule(1, 'final'));
    }

    #[Test]
    public function shouldNotAcceptNoStateInTwoRules()
    {
        $rulesModel = new XliffRulesModel();
        $rulesModel->addRule(new Xliff12Rule(['no-state', 'translated'], 'pre-translated', 'translated', 'ice'));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("The same state/state-qualifier cannot be used in two different rules: no-state");
        $this->expectExceptionCode(400);

        $rulesModel->addRule(new Xliff12Rule(['final', 'no-state'], 'new'));
    }

    #[Test]
    public function shouldAllowANoStateRulePerVersion()
    {
        $rulesModel = XliffRulesModel::fromArray([
            'xliff12' => [['states' => ['no-state'], 'analysis' => 'new']],
            'xliff20' => [['states' => ['no-state'], 'analysis' => 'pre-translated', 'editor' => 'draft', 'match_category' => 'ice']],
        ]);

        $this->assertTrue($rulesModel->getMatchingRule(1)->isNoStateRule());
        $this->assertTrue($rulesModel->getMatchingRule(2)->isNoStateRule());
    }

}
