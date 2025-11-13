<?php

/**
 * Created by PhpStorm.
 * User: egomez-prompsit
 * Date: 28/07/15
 * Time: 14.34
 */

namespace Model\Engines\Structs;

use Utils\Engines\Apertium;

/**
 * Class Engine_ApertiumStruct
 *
 * This class contains the default parameters for a Apertium EnginesFactory CREATION
 *
 */
class ApertiumStruct extends EngineStruct
{

    /**
     * @var ?string
     */
    public ?string $description = "Apertium Engine";

    /**
     * @var ?string
     */
    public ?string $base_url = "https://api.prompsit.com";

    /**
     * @var ?string
     */
    public ?string $translate_relative_url = "apertiumws/";

    /**
     * @var ?string
     */
    public ?string $contribute_relative_url = "";


    /**
     * @var ?string
     */
    public ?string $class_load = Apertium::class;

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
     * @return ApertiumStruct
     */
    public static function getStruct(): static
    {
        return new ApertiumStruct();
    }

}