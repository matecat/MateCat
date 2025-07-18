<?php

class Projects_MetadataStruct extends \DataAccess\AbstractDaoSilentStruct implements \DataAccess\IDaoStruct {

    public $id;
    public $id_project;
    public $key;
    public $value;

    /**
     * @return mixed
     */
    public function getValue() {

        $this->value = html_entity_decode($this->value);

        if ( Utils::isJson( $this->value ) ) {
            return json_decode( $this->value );
        }

        return $this->value;
    }
}
