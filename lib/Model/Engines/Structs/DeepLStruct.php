<?php

namespace Model\Engines\Structs;

use Utils\Engines\DeepL;

class DeepLStruct extends EngineStruct {
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
     * @var array
     */
    public array $others = [
            "relative_glossaries_url" => "glossaries",
    ];

    /**
     * @var array
     */
    public $extra_parameters = [
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

    public static function getStruct(): EngineStruct {
        return new DeepLStruct();
    }
}