<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 23/02/15
 * Time: 14.54
 */
class Engine_EngineStruct extends DataAccess_AbstractDaoObjectStruct implements DataAccess_IDaoStruct {

    /**
     * @var int
     */
    public $id;

    /**
     * @var string
     */
    public $name;


    /**
     * @var string A string from the ones in Constants_EngineType
     * @see Constants_EngineType
     */
    public $type;

    /**
     * @var string
     */
    public $description;

    /**
     * @var string
     */
    public $base_url;

    /**
     * @var string
     */
    public $translate_relative_url;

    /**
     * @var string
     */
    public $contribute_relative_url;

    /**
     * @var string
     */
    public $delete_relative_url;

    /**
     * @var array
     */
    public $others;

    /**
     * @var string
     */
    public $extra_parameters;

    /**
     * @var int
     */
    public $google_api_compliant_version;

    /**
     * @var int
     */
    public $penalty;

    /**
     * @var int 0 or 1
     */
    public $active;

    /**
     * @var int
     */
    public $uid;

    /**
     * An empty struct
     * @return Engine_EngineStruct
     */
    public static function getStruct() {
        return new Engine_EngineStruct();
    }


}