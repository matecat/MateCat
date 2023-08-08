<?php

/**
 * Created by PhpStorm.
 * User: egomez-prompsit
 * Date: 28/07/15
 * Time: 14.34
 */

/**
 * Class Engine_AltlangStruct
 *
 * This class contains the default parameters for a Altlang Engine CREATION
 *
 */
class EnginesModel_AltlangStruct extends EnginesModel_EngineStruct {

    /**
     * @var string
     */
    public $description = "AltLang Engine";

    /**
     * @var string
     */
    public $base_url = "https://api2.prompsit.com";
        
    /**
     * @var string
     */
    public $translate_relative_url = "wsprompsit/";

    /**
     * @var string
     */
    public $contribute_relative_url = "";


    /**
     * @var string
     */
    public $class_load = Constants_Engines::ALTLANG;

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
        return new EnginesModel_AltlangStruct();
    }

}