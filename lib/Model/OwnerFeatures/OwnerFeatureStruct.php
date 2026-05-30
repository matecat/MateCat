<?php

namespace Model\OwnerFeatures;

use Model\FeaturesBase\BasicFeatureStruct;

class OwnerFeatureStruct extends BasicFeatureStruct
{

    public int $id;
    public int $uid;
    public ?int $id_team = null;

    /**
     * @var array<string, mixed>|string|null
     */
    public string|array|null $options;
    public ?string $last_update = null;
    public ?string $create_date = null;
    public bool $enabled;

    /**
     * @return array<string, mixed>|null
     */
    public function getOptions(): ?array
    {
        if (is_array($this->options)) {
            return $this->options;
        }

        if (is_string($this->options)) {
            return json_decode($this->options, true);
        }

        return null;
    }

}
