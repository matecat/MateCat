<?php

/**
 * Created by PhpStorm.
 * User: egomez-prompsit
 * Date: 28/05/18
 * Time: 14.34
 */

/**
 * Class Engine_IADAATPAStruct
 *
 * This class contains the default parameters for a IADAATPA Engine CREATION
 *
 */
class EnginesModel_IADAATPAStruct extends EnginesModel_EngineStruct {

    /**
     * @var string
     */
    public $description = "IADAATPA Engine";

    /**
     * @var string
     */
    public $base_url = "https://app.iadaatpa.eu";

    /**
     * @var string
     */
    public $translate_relative_url = "api/translate";

    /**
     * @var string
     */
    public $contribute_relative_url = "";


    /**
     * @var string
     */    
    public $class_load = Constants_Engines::IADAATPA;

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
        return new EnginesModel_IADAATPAStruct();
    }

}
