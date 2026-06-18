<?php

namespace Model\FeaturesBase;

use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;
use Model\DataAccess\IDatabase;
use Plugins\Features\BaseFeature;

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

    /**
     * @return string|null
     * @throws \RuntimeException
     */
    public function getFullyQualifiedClassName(): ?string
    {
        return PluginsLoader::getPluginClass($this->feature_code);
    }

    /**
     * @param ?IDatabase $db Caller's handle; null = transitional (BaseFeature falls back to obtain()).
     *
     * @return BaseFeature
     * @throws \RuntimeException
     */
    public function toNewObject(?IDatabase $db = null): BaseFeature
    {
        $name = PluginsLoader::getPluginClass($this->feature_code);
        $obj  = new $name($this);
        $obj->setDatabase($db);

        return $obj;
    }
}