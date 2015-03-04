<?php

/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 23/02/15
 * Time: 14.54
 */

/**
 * Class Engine_MicrosoftHubStruct
 *
 * This class contains the default parameters for a Microsoft Hub Engine CREATION
 *
 */
class Engine_MicrosoftHubStruct extends Engine_EngineStruct {

    /**
     * @var string A string from the ones in Constants_EngineType
     * @see Constants_EngineType
     */
    public $type = Constants_Engines::MT;

    /**
     * @var string
     */
    public $description = "Microsoft Translator Hub";

    /**
     * @var string
     */
    public $base_url = "http://api.microsofttranslator.com/v2/Http.svc/";

    /**
     * @var string
     */
    public $translate_relative_url = "Translate";

    /**
     * @var array
     */
    public $others = array(
            'oauth_url' => 'https://datamarket.accesscontrol.windows.net/v2/OAuth2-13/'
    );

    /**
     * @var string
     */
    public $class_load = Constants_Engines::MICROSOFT_HUB;


    /**
     * @var array
     */
    public $extra_parameters = array(
            'token'         => null,
            'token_endlife' => 0,
            'client_id'     => "",
            'client_secret' => ""
    );

    /**
     * @var int
     */
    public $google_api_compliant_version;

    /**
     * @var int
     */
    public $penalty = 14;

    /**
     * @var int 0 or 1
     */
    public $active = 1;

    /**
     * An empty struct
     * @return Engine_EngineStruct
     */
    public static function getStruct() {
        return new Engine_MicrosoftHubStruct();
    }

}