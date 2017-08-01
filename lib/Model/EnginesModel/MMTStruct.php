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
    public $description = "MMT Context Adaptive Engine";

    /**
     * @var string
     */
    public $base_url = "http://api-test.mymemory.translated.net";

    /**
     * @var string
     */
    public $translate_relative_url = "mmt/get";

    /**
     * @var string
     */
    public $contribute_relative_url = "mmt/set";

    /**
     * @var string
     */
    public $update_relative_url = "mmt/update";

    /**
     * @var array
     */
    public $others = [
            'tmx_import_relative_url' => "mmt/tmx/import",
            "api_key_check_auth_url" => "mmt/me",
            "user_update_activate" => "mmt/keys/update",
            "context_get" => "mmt/context/get",
    ];

    /**
     * @var string
     */
    public $class_load = Constants_Engines::MMT;


    /**
     * @var array
     */
    public $extra_parameters = [
            'MyMemory-License' => "",
            'User_id'          => "", //<UNIQUE_COMPUTER_HASH>
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