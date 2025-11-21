<?php

/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 23/02/15
 * Time: 14.54
 */

namespace Model\Engines\Structs;

use Utils\Engines\SmartMATE;

/**
 * Class Engine_MicrosoftHubStruct
 *
 * This class contains the default parameters for a Moses Engine CREATION
 *
 */
class SmartMATEStruct extends EngineStruct
{

    /**
     * @var ?string
     */
    public ?string $description = "SmartMATE Engine by Capita";

    /**
     * @var ?string
     */
    public ?string $base_url = "https://api.smartmate.co/translate/api/v2.1";

    /**
     * @var ?string
     */
    public ?string $translate_relative_url = "translate";

    /**
     * @var string|array|null
     */
    public string|array|null $others = [
            'oauth_url' => 'https://api.smartmate.co/translate/oauth/token'
    ];

    /**
     * @var ?string
     */
    public ?string $contribute_relative_url = "";

    /**
     * @var string|array|null
     */
    public string|array|null $extra_parameters = [
            'token'         => null,
            'token_endlife' => 0,
            'client_id'     => "",
            'client_secret' => ""
    ];

    /**
     * @var ?string
     */
    public ?string $class_load = SmartMATE::class;


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
     * @return SmartMATEStruct
     */
    public static function getStruct(): static
    {
        return new SmartMATEStruct();
    }

}