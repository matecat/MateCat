<?php

namespace LQA;

class ModelStruct extends \DataAccess_AbstractDaoSilentStruct implements \DataAccess_IDaoStruct {
    public $id;
    public $label ;

    /**
     * Returns the serialized representation of categires and subcategories.
     *
     * @return string
     */
    public function getSerializedCategories() {
        return CategoryDao::getSerializedModel( $this->id );
    }
}
