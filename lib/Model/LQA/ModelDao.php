<?php

namespace LQA;

class ModelDao extends \DataAccess_AbstractDao {
    protected function _buildResult( $array_result ) { }

    /**
     * @param $id
     * @return \LQA\ModelStruct
     */
    public static function findById( $id ) {
        $sql = "SELECT * FROM qa_models WHERE id = :id LIMIT 1" ;
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute(array('id' => $id));
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\ModelStruct' );
        return $stmt->fetch();
    }

    /**
     * @param $data
     * @return ModelStruct
     * @throws \Exceptions\ValidationError
     */
    public static function createRecord( $data ) {
        $sql = "INSERT INTO qa_models ( label, pass_type, pass_options ) " .
            " VALUES ( :label, :pass_type, :pass_options ) ";

        $struct = new ModelStruct( array(
            'label' => $data['label'] ,
            'pass_type' => $data['passfail']['type'],
            'pass_options' => json_encode( $data['passfail']['options'] )
        ) );
        $struct->ensureValid();

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( $struct->attributes(
            array('label', 'pass_type', 'pass_options')
        ));
        $lastId = $conn->lastInsertId();

        return self::findById( $lastId );
    }

    /**
     * Recursively create categories and subcategories based on the
     * QA model definition.
     *
     */
    public static function createModelFromJsonDefinition( $json ) {
        $model_root = $json['model'];
        $model = ModelDao::createRecord( $model_root );

        $default_severities = $model_root['severities'];
        $categories = $model_root['categories'];

        function insertRecord($record, $model_id, $parent_id, $default_severities) {
            if ( !array_key_exists('severities', $record) ) {
                $record['severities'] = $default_severities ;
            }

            $category = CategoryDao::createRecord(array(
                'id_model'   => $model_id,
                'label'      => $record['label'],
                'id_parent'  => $parent_id,
                'severities' => json_encode( $record['severities'] )
            ));

            if ( array_key_exists('subcategories', $record) && !empty( $record['subcategories'] ) ) {
                foreach( $record['subcategories'] as $sub ) {
                    insertRecord($sub, $model_id, $category->id, $default_severities);
                }
            }
        }

        foreach($categories as $record) {
            insertRecord($record, $model->id, null, $default_severities);
        }

        return $model ;
    }

}
