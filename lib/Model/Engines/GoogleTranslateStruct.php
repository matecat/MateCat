<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 28/12/2017
 * Time: 12:06
 */

namespace Model\Engines;

use Constants_Engines;

/**
 * Class GoogleTranslateStruct
 *
 * This class contains the default parameters for a Google Translate Engine CREATION
 *
 */
class GoogleTranslateStruct extends EngineStruct {

    /**
     * @var string
     */
    public string $description = "Google Translate";

    /**
     * @var string
     */
    public string $base_url = "https://translation.googleapis.com";

    /**
     * @var string
     */
    public string $translate_relative_url = "language/translate/v2";

    /**
     * @var array
     */
    public $extra_parameters = [
            'client_secret' => ""
    ];

    /**
     * @var string
     */
    public string $class_load = Constants_Engines::GOOGLE_TRANSLATE;


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
     * @return GoogleTranslateStruct
     */
    public static function getStruct(): EngineStruct {
        return new GoogleTranslateStruct();
    }

}
