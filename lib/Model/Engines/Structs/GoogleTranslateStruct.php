<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 28/12/2017
 * Time: 12:06
 */

namespace Model\Engines\Structs;

use Utils\Engines\GoogleTranslate;

/**
 * Class GoogleTranslateStruct
 *
 * This class contains the default parameters for a Google Translate EnginesFactory CREATION
 *
 */
class GoogleTranslateStruct extends EngineStruct
{

    /**
     * @var ?string
     */
    public ?string $description = "Google Translate";

    /**
     * @var ?string
     */
    public ?string $base_url = "https://translation.googleapis.com";

    /**
     * @var ?string
     */
    public ?string $translate_relative_url = "language/translate/v2";

    /**
     * @var string|array|null
     */
    public string|array|null $extra_parameters = [
            'client_secret' => ""
    ];

    /**
     * @var ?string
     */
    public ?string $class_load = GoogleTranslate::class;


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
     * @return GoogleTranslateStruct
     */
    public static function getStruct(): static
    {
        return new GoogleTranslateStruct();
    }

}
