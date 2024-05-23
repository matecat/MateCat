<?php

namespace EnginesModel;

use Constants_Engines;
use EnginesModel_EngineStruct;

class DeepLStruct extends EnginesModel_EngineStruct
{
    /**
     * @var string
     */
    public $name = 'DeepL';

    /**
     * @var string
     */
    public $description = "DeepL - Accurate translations for individuals and Teams.";

    /**
     * @var string
     */
    public $base_url = "https://api.deepl.com";

    /**
     * @var string
     */
    public $translate_relative_url = "v1/translate";

    /**
     * @var array
     */
    public $others = [
        "relative_glossaries_url" => "glossaries",
    ];

    /**
     * @var array
     */
    public $extra_parameters = [
        'DeepL-Auth-Key' => "",
    ];

    /**
     * @var string
     */
    public $class_load = Constants_Engines::DEEPL;

    /**
     * @var int
     */
    public $penalty = 15; // to get 85% matches

    public static function getStruct() {
        return new DeepLStruct();
    }
}