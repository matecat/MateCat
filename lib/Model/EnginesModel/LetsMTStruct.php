<?php

/**
 * @author Rihards Krislauks rihards.krislauks@tilde.lv / rihards.krislauks@gmail.com
 */

/**
 * Class Engine_MicrosoftHubStruct
 *
 * This class contains the default parameters for a LetsMT Engine CREATION
 *
 */
class EnginesModel_LetsMTStruct extends EnginesModel_EngineStruct {

    /**
     * @var string
     */
    public $description = "Tilde MT Engine";

    /**
     * @var string
     */
    public $base_url = "https://www.letsmt.eu/ws/service.svc/json";
    
    /**
     * @var string
     */
    public $translate_relative_url = "TranslateEx";

    /**
     * @var string
     */
    public $contribute_relative_url = "UpdateTranslation";

    /**
     * @var array
     */
    public $others = array(
            'system_list_relative_url' => 'GetSystemList',
            'term_list_relative_url' => 'GetSystemTermCorpora',
            'app_id' => 'matecat'
    );

    /**
     * @var string
     */
    public $class_load = Constants_Engines::LETSMT;


    /**
     * @var array
     */
    public $extra_parameters = array(
            'client_id'     => "",
            'system_id'     => "",
            'terms_id'      => "",
            'use_qe'        => false,
            'minimum_qe'    => 0,
            'source_lang'   => "",
            'target_lang'   => ""
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
        return new EnginesModel_LetsMTStruct();
    }

}