<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 14/11/25
 * Time: 17:23
 *
 */

namespace Utils\Engines\GoogleTranslate;

use DomainException;
use Exception;
use Model\Engines\Structs\EngineStruct;
use Utils\Engines\EnginesFactory;

class GoogleTranslateEngineValidator
{

    /**
     * @throws Exception
     */
    public static function validate(EngineStruct $engineStruct): void
    {
        $newTestCreatedMT = EnginesFactory::createTempInstance($engineStruct);
        $config = $newTestCreatedMT->getConfigStruct();
        $config['segment'] = "Hello World";
        $config['source'] = "en-US";
        $config['target'] = "fr-FR";
        $config['key'] = $newTestCreatedMT->client_secret ?? null;

        $mt_result = $newTestCreatedMT->get($config);

        if (isset($mt_result['error']['code'])) {
            throw new DomainException($mt_result['error']['message']);
        }
    }

}