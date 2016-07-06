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
class EnginesModel_MicrosoftHubStruct extends EnginesModel_EngineStruct {

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
            'client_secret' => "",
            'category'      => "",
    );

    /**
     * @var int
     */
    public $google_api_compliant_version = 2;

    /**
     * @var int
     */
    public $penalty = 14;

    /**
     * An empty struct
     * @return EnginesModel_EngineStruct
     */
    public static function getStruct() {
        return new EnginesModel_MicrosoftHubStruct();
    }

}