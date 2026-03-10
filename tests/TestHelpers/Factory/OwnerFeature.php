<?php

use Model\DataAccess\Database;
use Model\OwnerFeatures\OwnerFeatureDao;
use Model\OwnerFeatures\OwnerFeatureStruct;

class Factory_OwnerFeature extends Factory_Base
{

    static function create($values)
    {
        $values = array_merge([
            'uid' => 1,
            'feature_code' => 'project_completion',
            'options' => '{}',
            'enabled' => true,
        ], $values);

        $dao = new OwnerFeatureDao(Database::obtain());
        $struct = new OwnerFeatureStruct($values);

        return $dao->create($struct);
    }
}
