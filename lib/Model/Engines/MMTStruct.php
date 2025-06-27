<?php

/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 27/07/17
 * Time: 14.54
 */

namespace Model\Engines;

use Constants_Engines;

/**
 * Class Engine_MMTStruct
 *
 * This class contains the default parameters for a MMT Engine CREATION
 *
 */
class MMTStruct extends EngineStruct {

    /**
     * @var string
     */
    public string $name = 'ModernMT Full';

    /**
     * @var string
     */
    public string $description = "ModernMT for subscribers, includes adaptive suggestions for entire documents, integrated glossary support and TM usage optimization.";

    /**
     * @var string
     */
    public string $base_url = "http://MMT";

    /**
     * @var string
     */
    public string $translate_relative_url = "translate";

    /**
     * @var string
     */
    public string $contribute_relative_url = "memories/content";

    /**
     * @var string
     */
    public string $update_relative_url = "memories/content";

    /**
     * @var array
     */
    public $others = [
            "tmx_import_relative_url" => "memories/content",
            "api_key_check_auth_url"  => "users/me",
            "user_update_activate"    => "memories/connect",
            "context_get"             => "context-vector",
    ];

    /**
     * @var string
     */
    public string $class_load = Constants_Engines::MMT;


    /**
     * @var array
     */
    public $extra_parameters = [
            'MMT-License'      => "",
            'MMT-pretranslate' => "",
            'MMT-preimport'    => "",
    ];

    /**
     * @var int
     */
    public int $google_api_compliant_version = 2;

    /**
     * @var int
     */
    public int $penalty = 14;

    /**
     * An empty struct
     * @return EngineStruct
     */
    public static function getStruct(): EngineStruct {
        return new MMTStruct();
    }
}