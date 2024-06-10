<?php

/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 27/07/17
 * Time: 14.54
 */

/**
 * Class Engine_MMTStruct
 *
 * This class contains the default parameters for a MMT Engine CREATION
 *
 */
class EnginesModel_MMTStruct extends EnginesModel_EngineStruct {

    /**
     * @var string 
     */
    public $name = 'ModernMT Full';

    /**
     * @var string
     */
    public $description = "ModernMT for subscribers, includes adaptive suggestions for entire documents, integrated glossary support and TM usage optimization.";

    /**
     * @var string
     */
    public $base_url = "http://MMT";

    /**
     * @var string
     */
    public $translate_relative_url = "translate";

    /**
     * @var string
     */
    public $contribute_relative_url = "memories/content";

    /**
     * @var string
     */
    public $update_relative_url = "memories/content";

    /**
     * @var array
     */
    public $others = [
            "tmx_import_relative_url" => "memories/content",
            "api_key_check_auth_url" => "users/me",
            "user_update_activate" => "memories/connect",
            "context_get" => "context-vector",
    ];

    /**
     * @var string
     */
    public $class_load = Constants_Engines::MMT;


    /**
     * @var array
     */
    public $extra_parameters = [
        'MMT-License' => "",
        'MMT-pretranslate' => "",
        'MMT-preimport' => "",
    ];

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
        return new EnginesModel_MMTStruct();
    }
}