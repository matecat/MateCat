<?php

namespace LQA ;

class CategoryStruct extends \DataAccess_AbstractDaoSilentStruct implements \DataAccess_IDaoStruct {

    public $id;
    public $severities ;

    public $id_model ;
    public $id_parent ;
    public $label ;
    public $options ;

    /**
     * @return mixed
     */
    public function getJsonSeverities() {
        return json_decode( $this->severities, true );
    }

}
