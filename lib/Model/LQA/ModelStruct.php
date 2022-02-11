<?php

namespace LQA;

use Exception;

class ModelStruct extends \DataAccess_AbstractDaoSilentStruct implements \DataAccess_IDaoStruct {

    protected static $auto_increment_fields = ['id'];
    protected static $primary_keys = ['id'];

    public $id;
    public $label ;

    public $pass_type ;
    public $pass_options ;

    public $hash;

    public $qa_model_template_id;

    /**
     * Returns the serialized representation of categires and subcategories.
     *
     * @return string
     */
    public function getSerializedCategories() {
        return json_encode( ['categories' => CategoryDao::getCatgoriesAndSeverities( $this->id ) ] ) ;
    }

    public function getCategoriesAndSeverities() {
        return CategoryDao::getCatgoriesAndSeverities( $this->id );
    }

    /**
     * @return CategoryStruct[]
     */
    public function getCategories() {
        return CategoryDao::getCategoriesByModel( $this );
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getLimit() {
        $options = json_decode( $this->pass_options, true);
        if ( ! array_key_exists('limit', $options) ) {
            throw new Exception( 'limit is not defined in JSON options');
        }
        return $options['limit'];

    }
}
