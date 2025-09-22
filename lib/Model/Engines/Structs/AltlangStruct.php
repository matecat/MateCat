<?php

/**
 * Created by PhpStorm.
 * User: egomez-prompsit
 * Date: 28/07/15
 * Time: 14.34
 */

namespace Model\Engines\Structs;

use Utils\Engines\Altlang;

/**
 * Class Engine_AltlangStruct
 *
 * This class contains the default parameters for an Altlang EnginesFactory CREATION
 *
 */
class AltlangStruct extends EngineStruct {

    /**
     * @var ?string
     */
    public ?string $description = "AltLang Engine";

    /**
     * @var ?string
     */
    public ?string $base_url = "https://api2.prompsit.com";

    /**
     * @var ?string
     */
    public ?string $translate_relative_url = "wsprompsit/";

    /**
     * @var ?string
     */
    public ?string $contribute_relative_url = "";


    /**
     * @var ?string
     */
    public ?string $class_load = Altlang::class;

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
        return new AltlangStruct();
    }

}