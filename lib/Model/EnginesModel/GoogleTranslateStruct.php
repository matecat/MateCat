<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 28/12/2017
 * Time: 12:06
 */

/**
 * Class EnginesModel_GoogleTranslateStruct
 *
 * This class contains the default parameters for a Google Translate Engine CREATION
 *
 */
class EnginesModel_GoogleTranslateStruct extends EnginesModel_EngineStruct {

    /**
     * @var string
     */
    public $description = "Google Translate";

    /**
     * @var string
     */
    public $base_url = "https://translation.googleapis.com";

    /**
     * @var string
     */
    public $translate_relative_url = "language/translate/v2";

    /**
     * @var array
     */
    public $extra_parameters = array(
            'client_secret' => ""
    );

    /**
     * @var string
     */
    public $class_load = Constants_Engines::GOOGLE_TRANSLATE;


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
     * @return EnginesModel_GoogleTranslateStruct
     */
    public static function getStruct() {
        return new EnginesModel_GoogleTranslateStruct();
    }

}
