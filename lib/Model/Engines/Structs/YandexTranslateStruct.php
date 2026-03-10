<?php

namespace Model\Engines\Structs;

use Utils\Engines\YandexTranslate;

/**
 * Class Engine_YandexTranslateStruct
 *
 * This class contains the default parameters for a Yandex.Translate EnginesFactory CREATION
 *
 */
class YandexTranslateStruct extends EngineStruct
{

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
     * @var string|array|null
     */
    public string|array|null $extra_parameters = [
        'client_secret' => ""
    ];

    /**
     * @var ?string
     */
    public ?string $class_load = YandexTranslate::class;


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
     * @return YandexTranslateStruct
     */
    public static function getStruct(): static
    {
        return new YandexTranslateStruct();
    }

}
