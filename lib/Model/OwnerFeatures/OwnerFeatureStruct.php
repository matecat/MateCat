<?php

namespace Model\OwnerFeatures;

use Model\FeaturesBase\BasicFeatureStruct;

class OwnerFeatureStruct extends BasicFeatureStruct
{

    public int $id;
    public int $uid;
    public ?int $id_team = null;

    /**
     * @var array|string|null
     */
    public string|array|null $options;
    public ?string $last_update = null;
    public ?string $create_date = null;
    public bool $enabled;

    public function getOptions()
    {
        return json_decode($this->options, true);
    }

}
