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
 * This class contains the default parameters for a Moses Engine CREATION
 *
 */
class EnginesModel_SmartMATEStruct extends EnginesModel_EngineStruct {

    /**
     * @var string
     */
    public $description = "SmartMATE Engine by Capita";

    /**
     * @var string
     */
    public $base_url = "https://api.smartmate.co/translate/api/v2.1";

    /**
     * @var string
     */
    public $translate_relative_url = "translate";

    /**
     * @var array
     */
    public $others = array(
            'oauth_url' => 'https://api.smartmate.co/translate/oauth/token'
    );

    /**
     * @var string
     */
    public $contribute_relative_url = "";

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
     * @var string
     */
    public $class_load = Constants_Engines::SMART_MATE;


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
        return new EnginesModel_SmartMATEStruct();
    }

}