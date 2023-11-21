<?php

namespace EnginesModel;

use Constants_Engines;

class DeepLStruct
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
    public $base_url = "https://api.deepl.com/v1/";

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
        "api_key_check_auth_url" => "users/me",
        "user_update_activate" => "memories/connect",
        "context_get" => "context-vector",
    ];

    /**
     * @var string
     */
    public $class_load = Constants_Engines::DEEPL;

    /**
     * @var int
     */
    public $penalty = 14;
}