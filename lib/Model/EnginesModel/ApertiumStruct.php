<?php

/**
 * Created by PhpStorm.
 * User: egomez-prompsit
 * Date: 28/07/15
 * Time: 14.34
 */

/**
 * Class Engine_ApertiumStruct
 *
 * This class contains the default parameters for a Apertium Engine CREATION
 *
 */
class EnginesModel_ApertiumStruct extends EnginesModel_EngineStruct {

    /**
     * @var string
     */
    public $description = "Apertium Engine";

    /**
     * @var string
     */
    public $base_url = "http://api.prompsit.com";

    /**
     * @var string
     */
    public $translate_relative_url = "apertiumws/";

    /**
     * @var string
     */
    public $contribute_relative_url = "";


    /**
     * @var string
     */
    public $class_load = Constants_Engines::APERTIUM;

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
        return new EnginesModel_ApertiumStruct();
    }

}