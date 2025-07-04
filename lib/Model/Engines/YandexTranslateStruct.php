<?php

namespace Model\Engines;

use Constants_Engines;

/**
 * Class Engine_YandexTranslateStruct
 *
 * This class contains the default parameters for a Yandex.Translate Engine CREATION
 *
 */
class YandexTranslateStruct extends EngineStruct {

    /**
     * @var ?string
     */
    public ?string $description = "Yandex.Translate";

    /**
     * @var ?string
     */
    public ?string $base_url = "https://translate.yandex.net/api/v1.5/tr.json";

    /**
     * @var ?string
     */
    public ?string $translate_relative_url = "translate";

    /**
     * @var array
     */
    public $extra_parameters = [
            'client_secret' => ""
    ];

    /**
     * @var ?string
     */
    public ?string $class_load = Constants_Engines::YANDEX_TRANSLATE;


    /**
     * @var ?int
     */
    public ?int $google_api_compliant_version = 2;

    /**
     * @var ?int
     */
    public ?int $penalty = 14;

    /**
     * An empty struct
     * @return EngineStruct
     */
    public static function getStruct(): EngineStruct {
        return new YandexTranslateStruct();
    }

}
