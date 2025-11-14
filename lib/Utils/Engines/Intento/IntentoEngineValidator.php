<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 14/11/25
 * Time: 12:48
 *
 */

namespace Utils\Engines\Intento;

use DomainException;
use Exception;
use Model\Engines\Structs\EngineStruct;
use Utils\Engines\EnginesFactory;

class IntentoEngineValidator
{

    /**
     * @throws Exception
     */
    public static function validate(EngineStruct $engineStruct): void
    {
        $newTestCreatedMT = EnginesFactory::createTempInstance($engineStruct);
        $config = $newTestCreatedMT->getEngineRecord()->getExtraParamsAsArray();
        $config['segment'] = "Hello World";
        $config['source'] = "en-US";
        $config['target'] = "fr-FR";

        $mt_result = $newTestCreatedMT->get($config);

        if (isset($mt_result['error']['code'])) {
            switch ($mt_result['error']['code']) {
                // wrong provider credentials
                case -2:
                    $code = $mt_result['error']['http_code'] ?? 413;
                    $message = $mt_result['error']['message'];
                    break;

                // not valid license
                case -403:
                    $code = 413;
                    $message = "The Intento license you entered cannot be used inside CAT tools. Please subscribe to a suitable license to start using Intento as MT engine.";
                    break;

                default:
                    $code = 500;
                    $message = "Intento license not valid, please verify its validity and try again";
                    break;
            }

            throw new DomainException($message, $code);
        }
    }

    public static function validateRouting() // TODO
    {
    }

    public static function validateProvider()
    {
    }

}