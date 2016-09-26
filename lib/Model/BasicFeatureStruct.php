<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 06/09/16
 * Time: 15:19
 */
class BasicFeatureStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $feature_code ;

    public function toClassName() {
        return Utils::underscoreToCamelCase( $this->feature_code );
    }

}