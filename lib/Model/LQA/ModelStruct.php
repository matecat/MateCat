<?php

namespace LQA;

class ModelStruct extends \DataAccess_AbstractDaoSilentStruct implements \DataAccess_IDaoStruct {
    public $id;
    public $label ;

    public function getSerialized() {
        return CategoryDao::getSerializedModel( $this->id );
    }
}
