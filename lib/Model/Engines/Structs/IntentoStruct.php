<?php

namespace Model\Engines\Structs;

use Utils\Engines\Intento;

/**
 * Class Engine_IntentoStruct
 *
 * This class contains the default parameters for a Intento EnginesFactory CREATION
 *
 */
class IntentoStruct extends EngineStruct {

    /**
     * @var ?string
     */
    public ?string $description = "Intento";

    /**
     * @var ?string
     */
    public ?string $base_url = "https://api.inten.to/ai/text";

    /**
     * @var ?string
     */
    public ?string $translate_relative_url = "translate";

    /**
     * @var array
     */
    public $extra_parameters = [
            'apikey'           => "",
            'provider'         => "",
            'providerkey'      => "",
            'providercategory' => ""
    ];

    /**
     * @var ?string
     */
    public ?string $class_load = Intento::class;


    /**
     * @var ?int
     */
    public ?int $google_api_compliant_version = 2;

    /**
     * An empty struct
     * @return EngineStruct
     */
    public static function getStruct(): EngineStruct {
        return new IntentoStruct();
    }

}
