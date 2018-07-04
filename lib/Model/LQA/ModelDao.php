<?php

namespace LQA;

use DataAccess_AbstractDao;
use Database;
use INIT;
use Log;

class ModelDao extends DataAccess_AbstractDao {
    const TABLE = "qa_models";

    protected static $auto_increment_fields = ['id'];

    protected function _buildResult( $array_result ) { }

    /**
     * @param $id
     * @return \LQA\ModelStruct
     */
    public static function findById( $id ) {
        $sql = "SELECT * FROM qa_models WHERE id = :id LIMIT 1" ;
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute(array('id' => $id));
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\ModelStruct' );
        return $stmt->fetch();
    }

    /**
     * @param $data
     * @return ModelStruct
     * @throws \Exceptions\ValidationError
     *
     * @deprecated remove the need for insert and select
     */
    public static function createRecord( $data ) {
        $sql = "INSERT INTO qa_models ( label, pass_type, pass_options ) " .
            " VALUES ( :label, :pass_type, :pass_options ) ";

        $struct = new ModelStruct( array(
            'label' => @$data['label'] ,
            'pass_type' => $data['passfail']['type'],
            'pass_options' => json_encode( $data['passfail']['options'] )
        ) );
        $struct->ensureValid();

        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( $sql );
        $stmt->execute( $struct->attributes(
            array('label', 'pass_type', 'pass_options')
        ));

        $lastId = $conn->lastInsertId();

        $record = self::findById( $lastId );

        return $record ;
    }

    /**
     * Recursively create categories and subcategories based on the
     * QA model definition.
     *
     * @param       $json
     *
     * @return ModelStruct
     *
     */
    public static function createModelFromJsonDefinition( $json ) {
        $model_root = $json['model'];
        $model = ModelDao::createRecord( $model_root );

        $default_severities = $model_root['severities'];
        $categories         = $model_root['categories'];

        foreach($categories as $category) {
            self::insertCategory($category, $model->id, null, $default_severities);
        }

        return $model ;
    }

    private static function insertCategory( $category, $model_id, $parent_id, $default_severities) {
        if ( !array_key_exists('severities', $category) ) {
            $category['severities'] = $default_severities ;
        }

        /*
         * Any other key found in the json array will populate the `options` field
         */
        $options = [] ;

        foreach( array_keys( $category ) as $key ) {
            if ( ! in_array( $key, ['label', 'severities', 'subcategories' ] ) )  {
                $options[ $key ] = $category[ $key ] ;
            }
        }

        $category_record = CategoryDao::createRecord(array(
                'id_model'   => $model_id,
                'label'      => $category['label'],
                'options'    => ( empty( $options ) ? null : json_encode( $options ) ),
                'id_parent'  => $parent_id,
                'severities' => json_encode( $category['severities'] )
        ));

        if ( array_key_exists('subcategories', $category) && !empty( $category['subcategories'] ) ) {
            foreach( $category['subcategories'] as $sub ) {
                self::insertCategory($sub, $model_id, $category_record->id, $default_severities);
            }
        }
    }

}
