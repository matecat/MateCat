<?php

namespace LQA;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;
use Exception;

class ModelStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, QAModelInterface {

    protected static $auto_increment_fields = ['id'];
    protected static $primary_keys = ['id'];

    public $id;
    public $label ;
    public $create_date;
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
        return json_encode( ['categories' => CategoryDao::getCategoriesAndSeverities( $this->id ) ], JSON_HEX_APOS ) ;
    }

    public function getCategoriesAndSeverities() {
        return CategoryDao::getCategoriesAndSeverities( $this->id );
    }

    /**
     * @return CategoryStruct[]
     */
    public function getCategories() {
        return CategoryDao::getCategoriesByModel( $this );
    }

    /**
     * @return mixed
     */
    public function getPassOptions() {
        return json_decode( $this->pass_options );
    }

    /**
     * @return int[]
     * @throws Exception
     */
    public function getLimit() {
        $options = json_decode( $this->pass_options, true);

        if ( ! array_key_exists('limit', $options) ) {
            throw new Exception( 'limit is not defined in JSON options');
        }

        return $this->normalizeLimits($options['limit']);
    }

    /**
     * This function normalizes the limits.
     *
     * Ex: {"limit":{"1":"8","2":"5"}} is normalized to [0 => 8, 1 => 5]
     *
     * @param $limits
     * @return array
     */
    private function normalizeLimits($limits){

        $normalized = [];

        foreach($limits as $limit){
            $normalized[] = (int)$limit;
        }

        return $normalized;
    }

    /**
     * @return array
     */
    public function getDecodedModel() {

        $categoriesArray = [];
        foreach ( $this->getCategories() as $categoryStruct ){

            $category = $categoryStruct->toArrayWithJsonDecoded();

            if(!empty($category)){
                $categoriesArray[] = [
                    'id' => (int)$category['id'],
                    'label' => $category['label'],
                    'code' => $category['options']['code'],
                    'severities' => $category['severities'],
                ];
            }
        }

        return [
            'model' => [
                "id" => (int)$this->id,
                "template_model_id" => $this->qa_model_template_id ? (int)$this->qa_model_template_id : null,
                "version" => 1,
                "label" => $this->label,
                "create_date" => $this->create_date,
                "categories" => $categoriesArray,
                "passfail" => [
                    'type' => $this->pass_type,
                    'options' =>  $this->getPassOptions()
                ],
            ]
        ];
    }
}
