<?php

/**
 * Class Engine_YandexTranslateStruct
 *
 * This class contains the default parameters for a Yandex.Translate Engine CREATION
 *
 */
class EnginesModel_YandexTranslateStruct extends EnginesModel_EngineStruct {

    /**
     * @var string
     */
    public $description = "Yandex.Translate";

    /**
     * @var string
     */
    public $base_url = "https://translate.yandex.net/api/v1.5/tr.json";

    /**
     * @var string
     */
    public $translate_relative_url = "translate";

    /**
     * @var array
     */
    public $extra_parameters = array(
        'client_secret' => ""
    );

    /**
     * @var string
     */
    public $class_load = Constants_Engines::YANDEX_TRANSLATE;


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
        return new EnginesModel_YandexTranslateStruct();
    }

}
