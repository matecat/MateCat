<?php


namespace Matecat\Core\Model;

use Matecat\TestHelpers\AbstractTest;
use Model\Analysis\PayableRates;
use PHPUnit\Framework\Attributes\Test;

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/06/2017
 * Time: 16:51
 */
class PayableRatesTest extends AbstractTest
{

    /**
     * @test
     */
    #[Test]
    public function getPayableRates()
    {
        $languageCombos = [
            ['en', 'zh', 77],
            ['en-GB', 'zh', 77],
            ['en-GB', 'zh', 77],
            ['en-US', 'zh', 77],
            ['en-US', 'zh-CN', 77],
            ['en-US', 'zh-HK', 90],
            ['en', 'zh-HK', 90],
        ];

        foreach ($languageCombos as $languageCombo) {
            $payableRate = PayableRates::getPayableRates($languageCombo[0], $languageCombo[1]);
            $errorMessage = 'Error for language combination ' . $languageCombo[0] . '<->' . $languageCombo[1] . '. Exp. ' . $languageCombo[2] . ', got ' . $payableRate['MT'];

            $this->assertEquals($languageCombo[2], $payableRate['MT'], $errorMessage);
        }
    }

    /**
     * @test
     */
    #[Test]
    public function getPayableRates_unknownPairReturnsDefault()
    {
        // An unknown language pair should return $DEFAULT_PAYABLE_RATES
        $payableRate = PayableRates::getPayableRates('xx', 'yy');
        $this->assertEquals(PayableRates::$DEFAULT_PAYABLE_RATES, $payableRate);
    }

    /**
     * @test
     */
    #[Test]
    public function resolveBreakdowns_usesExplicitDefaultWhenNoPairFound()
    {
        $customDefault = [
            'NO_MATCH'    => 50,
            '50%-74%'     => 50,
            '75%-84%'     => 30,
            '85%-94%'     => 30,
            '95%-99%'     => 30,
            '100%'        => 10,
            '100%_PUBLIC' => 10,
            'REPETITIONS' => 10,
            'INTERNAL'    => 30,
            'MT'          => 40,
            'ICE'         => 0,
            'ICE_MT'      => 40,
        ];

        $result = PayableRates::resolveBreakdowns([], 'xx', 'yy', $customDefault);
        $this->assertEquals($customDefault, $result);
    }
}