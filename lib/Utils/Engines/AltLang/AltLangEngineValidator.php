<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 14/11/25
 * Time: 18:15
 *
 */

namespace Utils\Engines\AltLang;

use DomainException;
use Exception;
use Model\Engines\Structs\EngineStruct;
use Utils\Engines\Altlang;
use Utils\Engines\EnginesFactory;

class AltLangEngineValidator
{
    /**
     * @throws Exception
     */
    public static function validate(EngineStruct $engineStruct): void
    {
        /** @var AltLang $newTestCreatedMT */
        $newTestCreatedMT = EnginesFactory::createTempInstance($engineStruct);
        $config = $newTestCreatedMT->getConfigStruct();
        $config['segment'] = "Hello World";
        $config['source'] = "en-US";
        $config['target'] = "en-GB";

        $mt_result = $newTestCreatedMT->get($config);

        if (isset($mt_result['error']['code'])) {
            throw new DomainException($mt_result['error']['message']);
        }
    }
}