<?php

namespace Validator;

use Engines\DeepL\DeepLApiClient;
use EnginesModel\DeepLStruct;
use EnginesModel_EngineDAO;
use Exception;

class DeepLValidator
{
    /**
     * @param DeepLStruct $struct
     * @throws Exception
     */
    public static function validate(DeepLStruct $struct)
    {
        $dao = new EnginesModel_EngineDAO();
        $dao->validateForUser($struct);

        $apiKey = $struct->extra_parameters[ 'DeepL-Auth-Key' ];
        $deepLClient = DeepLApiClient::newInstance($apiKey);

        try {
            $deepLClient->translate("hello", "en", "it");
        } catch (Exception $exception){
            throw new Exception("Invalid DeepL API key.");
        }
    }
}