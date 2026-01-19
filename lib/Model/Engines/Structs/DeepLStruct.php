<?php

namespace Model\Engines\Structs;

use Utils\Engines\DeepL;

class DeepLStruct extends EngineStruct
{
    /**
     * @var ?string
     */
    public ?string $name = 'DeepL';

    /**
     * @var ?string
     */
    public ?string $description = "DeepL - Accurate translations for individuals and Teams.";

    /**
     * @var ?string
     */
    public ?string $base_url = "https://api.deepl.com";

    /**
     * @var ?string
     */
    public ?string $translate_relative_url = "v1/translate";

    /**
     * @var string|array|null
     */
    public string|array|null $others = [
        "relative_glossaries_url" => "glossaries",
    ];

    /**
     * @var string|array|null
     */
    public string|array|null $extra_parameters = [
        'DeepL-Auth-Key' => "",
    ];

    /**
     * @var ?string
     */
    public ?string $class_load = DeepL::class;

    /**
     * @var ?int
     */
    public ?int $penalty = 15; // to get 85% matches

    public static function getStruct(): static
    {
        return new DeepLStruct();
    }
}