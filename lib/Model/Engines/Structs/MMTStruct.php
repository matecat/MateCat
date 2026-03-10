<?php

/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 27/07/17
 * Time: 14.54
 */

namespace Model\Engines\Structs;

use Utils\Engines\MMT;

/**
 * Class Engine_MMTStruct
 *
 * This class contains the default parameters for a MMT EnginesFactory CREATION
 *
 */
class MMTStruct extends EngineStruct
{

    /**
     * @var string|null
     */
    public ?string $name = 'ModernMT Full';

    /**
     * @var string|null
     */
    public ?string $description = "ModernMT for subscribers, includes adaptive suggestions for entire documents, integrated glossary support and TM usage optimization.";

    /**
     * @var string|null
     */
    public ?string $base_url = "http://MMT";

    /**
     * @var string|null
     */
    public ?string $translate_relative_url = "translate";

    /**
     * @var string|null
     */
    public ?string $contribute_relative_url = "memories/content";

    /**
     * @var string|null
     */
    public ?string $update_relative_url = "memories/content";

    /**
     * @var string|array|null
     */
    public string|array|null $others = [
        "tmx_import_relative_url" => "memories/content",
        "api_key_check_auth_url" => "users/me",
        "user_update_activate" => "memories/connect",
        "context_get" => "context-vector",
    ];

    /**
     * @var string|null
     */
    public ?string $class_load = MMT::class;


    /**
     * @var string|array|null
     */
    public string|array|null $extra_parameters = [
        'MMT-License' => "",
    ];

    /**
     * @var ?int
     */
    public ?int $google_api_compliant_version = 2;

    /**
     * @var int|null
     */
    public ?int $penalty = 14;

    /**
     * An empty struct
     * @return MMTStruct
     */
    public static function getStruct(): static
    {
        return new MMTStruct();
    }
}