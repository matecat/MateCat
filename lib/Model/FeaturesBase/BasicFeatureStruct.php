<?php

namespace Model\FeaturesBase;

use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;
use Plugins\Features\BaseFeature;
use Plugins\Features\IBaseFeature;

/**
 * Class BasicFeatureStruct
 *
 * A BasicFeatureStruct is a feature that is not bound to a specific user. Example of such class is the DQF feature
 * which is enabled application-wide.
 *
 * BasicFeatureStruct can have options injected when the class is instantiated.
 *
 *
 */
class BasicFeatureStruct extends AbstractDaoSilentStruct implements IDaoStruct
{

    public string $feature_code;
    /**
     * @var array<string, mixed>|string|null
     */
    public string|array|null $options;

    public function getFullyQualifiedClassName(): ?string
    {
        return PluginsLoader::getPluginClass($this->feature_code);
    }

    /**
     * @return BaseFeature
     */
    public function toNewObject(): BaseFeature
    {
        $name = PluginsLoader::getPluginClass($this->feature_code);

        return new $name($this);
    }
}