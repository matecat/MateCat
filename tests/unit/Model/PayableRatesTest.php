<?php

use TestHelpers\AbstractTest;

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/06/2017
 * Time: 16:51
 */
class PayableRateStructTest extends AbstractTest {

    /**
     * @test
     */
    public function getPayableRates()
    {
        $languageCombos = [
            ['en', 'zh', 82],
            ['en-GB', 'zh', 82],
            ['en-GB', 'zh', 82],
            ['en-US', 'zh', 82],
            ['en-US', 'zh-CN', 82],
            ['en-US', 'zh-HK', 90],
            ['en', 'zh-HK', 90],
        ];

        foreach ($languageCombos as $languageCombo){
            $payableRate = Analysis_PayableRates::getPayableRates($languageCombo[0], $languageCombo[1]);
            $errorMessage = 'Error for language combination '.$languageCombo[0].'<->'.$languageCombo[1].'. Exp. '.$languageCombo[2].', got ' . $payableRate['MT'];

            $this->assertEquals($languageCombo[2], $payableRate['MT'], $errorMessage);
        }
    }
}