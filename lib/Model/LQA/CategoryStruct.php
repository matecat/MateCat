<?php

namespace LQA ;

class CategoryStruct extends \DataAccess_AbstractDaoSilentStruct implements \DataAccess_IDaoStruct {

    public $id;
    private $severities ;
    public $id_model ;
    public $id_parent ;
    public $label ;

    /**
     * @return mixed
     */
    public function getJsonSeverities() {
        return json_decode( $this->severities, true );
    }

    public function asArray() {
        $out = array();
        $out['id'] = $this->id ;
        $out['label'] = $this->label ;
        $out['id_parent'] = $this->id_parent ;

        $out['severities'] = json_decode($this->severities, true);
        return $out;
    }

}
