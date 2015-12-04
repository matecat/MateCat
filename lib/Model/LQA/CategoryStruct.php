<?php

namespace LQA ;

class CategoryStruct extends \DataAccess_AbstractDaoSilentStruct implements \DataAccess_IDaoStruct {

    public $id;
    private $severities ;
    public $id_model ;

    public function getJsonSeverities() {
        return json_decode( $this->severities, true );
    }

}
