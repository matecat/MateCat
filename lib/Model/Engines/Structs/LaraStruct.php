<?php

/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 27/07/17
 * Time: 14.54
 */

namespace Model\Engines\Structs;

use Utils\Engines\Lara;

/**
 * Class LaraStruct
 *
 * This class contains the default parameters for a Lara EnginesFactory CREATION
 *
 */
class LaraStruct extends EngineStruct
{

    /**
     * @var string|null
     */
    public ?string $name = 'Lara';

    /**
     * @var string|null
     */
    public ?string $description = "LLM-based machine translation that understands context and learns from previously translated content, delivering high-quality, nuanced translations.";

    /**
     * @var string|null
     */
    public ?string $base_url = "http://Lara";

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
            "user_update_activate"    => "memories/connect",
    ];

    /**
     * @var string|null
     */
    public ?string $class_load = Lara::class;


    /**
     * @var string|array|null
     */
    public string|array|null $extra_parameters = [
            'Lara-AccessKeyId'     => "",
            'Lara-AccessKeySecret' => "",
            'MMT-License'          => ""
    ];

    /**
     * @var int|null
     */
    public ?int $google_api_compliant_version = 2;

    /**
     * @var int|null
     */
    public ?int $penalty = 14;

    /**
     * An empty struct
     * @return LaraStruct
     */
    public static function getStruct(): static
    {
        return new LaraStruct();
    }
}