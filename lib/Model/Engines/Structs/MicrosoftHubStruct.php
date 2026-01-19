<?php

/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 23/02/15
 * Time: 14.54
 */

namespace Model\Engines\Structs;

use Utils\Engines\MicrosoftHub;

/**
 * Class Engine_MicrosoftHubStruct
 *
 * This class contains the default parameters for a Microsoft Hub EnginesFactory CREATION
 *
 */
class MicrosoftHubStruct extends EngineStruct
{

    /**
     * @var ?string
     */
    public ?string $description = "Microsoft Translator Hub";

    /**
     * @var ?string
     */
    public ?string $base_url = "https://api.microsofttranslator.com/v2/Http.svc/";

    /**
     * @var ?string
     */
    public ?string $translate_relative_url = "Translate";

    /**
     * @var string|array
     */
    public string|array $others = [
        'oauth_url' => 'https://api.cognitive.microsoft.com/sts/v1.0/issueToken'
    ];

    /**
     * @var ?string
     */
    public ?string $class_load = MicrosoftHub::class;


    /**
     * @var string|array|null
     */
    public string|array|null $extra_parameters = [
        'token' => null,
        'token_endlife' => 0,
        'client_id' => "",
        'client_secret' => "",
        'category' => "",
    ];

    /**
     * @var ?int
     */
    public ?int $google_api_compliant_version = 2;

    /**
     * @var ?int
     */
    public ?int $penalty = 14;

    /**
     *  An empty struct
     *
     * @return static
     */
    public static function getStruct(): static
    {
        return new MicrosoftHubStruct();
    }

}