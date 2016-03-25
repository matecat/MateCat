<?php

class OwnerFeatures_OwnerFeatureStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $id;
    public $uid ;
    public $feature_code ;
    public $options ;
    public $last_update ;
    public $create_date ;
    public $enabled ;

    public function toClassName() {
        return Utils::underscoreToCamelCase( $this->feature_code );
    }

    public function classExists() {
        $name =  "Features\\" . $feature->toClassName() ;
        return class_exists( $name );
    }

}
