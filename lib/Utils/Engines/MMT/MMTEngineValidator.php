<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 14/11/25
 * Time: 16:08
 *
 */

namespace Utils\Engines\MMT;

use Exception;
use Model\Engines\Structs\EngineStruct;
use Utils\Engines\EnginesFactory;
use Utils\Engines\MMT as MMTEngine;

class MMTEngineValidator
{

    /**
     * @throws MMTServiceApiException
     * @throws Exception
     */
    public static function validate(EngineStruct $engineStruct): void
    {
        /** @var MMTEngine $newTestCreatedMT */
        $newTestCreatedMT = EnginesFactory::createTempInstance($engineStruct);

        // Check the account
        $checkAccount = $newTestCreatedMT->checkAccount();

        if (!isset($checkAccount['billingPeriod']['planForCatTool'])) {
            throw new Exception("MMT license not valid");
        }

        $planForCatTool = $checkAccount['billingPeriod']['planForCatTool'];

        if ($planForCatTool === false) {
            throw new Exception(
                "The ModernMT license you entered cannot be used inside CAT tools. Please subscribe to a suitable license to start using the ModernMT plugin."
            );
        }
    }

}