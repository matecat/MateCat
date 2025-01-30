<?php

/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 27/07/17
 * Time: 14.54
 */

namespace EnginesModel;

use EnginesModel_EngineStruct;
use Utils\Engines\Lara;

/**
 * Class LaraStruct
 *
 * This class contains the default parameters for a Lara Engine CREATION
 *
 */
class LaraStruct extends EnginesModel_EngineStruct {

    /**
     * @var string
     */
    public $name = 'Lara';

    /**
     * @var string
     */
    public $description = "LLM-based machine translation that understands context and learns from previously translated content, delivering high-quality, nuanced translations.";

    /**
     * @var string
     */
    public $base_url = "http://Lara";

    /**
     * @var string
     */
    public $translate_relative_url = "translate";

    /**
     * @var string
     */
    public $contribute_relative_url = "memories/content";

    /**
     * @var string
     */
    public $update_relative_url = "memories/content";

    /**
     * @var array
     */
    public $others = [
            "tmx_import_relative_url" => "memories/content",
            "user_update_activate"    => "memories/connect",
    ];

    /**
     * @var string
     */
    public $class_load = Lara::class;


    /**
     * @var array
     */
    public $extra_parameters = [
            'Lara-AccessKeyId'     => "",
            'Lara-AccessKeySecret' => "",
            'MMT-License'          => ""
    ];

    /**
     * @var int
     */
    public $google_api_compliant_version = 2;

    /**
     * @var int
     */
    public $penalty = 14;

    /**
     * An empty struct
     * @return LaraStruct
     */
    public static function getStruct(): LaraStruct {
        return new LaraStruct();
    }
}