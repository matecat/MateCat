<?php

namespace Utils\Validator;

use Engines\DeepL\DeepLApiClient;
use Exception;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\DeepLStruct;

class DeepLValidator {
    /**
     * @param DeepLStruct $struct
     *
     * @throws Exception
     */
    public static function validate( DeepLStruct $struct ) {
        $dao = new EngineDAO();
        $dao->validateForUser( $struct );

        $apiKey      = $struct->extra_parameters[ 'DeepL-Auth-Key' ];
        $deepLClient = DeepLApiClient::newInstance( $apiKey );

        try {
            $deepLClient->translate( "hello", "en", "it" );
        } catch ( Exception $exception ) {
            throw new Exception( "Invalid DeepL API key." );
        }
    }
}