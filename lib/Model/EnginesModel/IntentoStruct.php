<?php

/**
 * Class Engine_IntentoStruct
 *
 * This class contains the default parameters for a Intento Engine CREATION
 *
 */
class EnginesModel_IntentoStruct extends EnginesModel_EngineStruct {

    /**
     * @var string
     */
    public $description = "Intento";

    /**
     * @var string
     */
    public $base_url = "https://api.inten.to/ai/text";

    /**
     * @var string
     */
    public $translate_relative_url = "translate";

    /**
     * @var array
     */
    public $extra_parameters = array(
        'apikey' => "",
        'provider'=> "",
        'providerkey'=>"",
        'providercategory'=> ""
    );

    /**
     * @var string
     */
    public $class_load = Constants_Engines::INTENTO;


    /**
     * @var int
     */
    public $google_api_compliant_version = 2;

    /**
     * An empty struct
     * @return EnginesModel_EngineStruct
     */
    public static function getStruct() {
        return new EnginesModel_IntentoStruct();
    }

}
