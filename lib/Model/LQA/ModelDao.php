<?php

namespace LQA;

class ModelDao extends \DataAccess_AbstractDao {
    protected function _buildResult( $array_result ) { }

    public static function findById( $id ) {
        $sql = "SELECT * FROM qa_models WHERE id = :id LIMIT 1" ;
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute(array('id' => $id));
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\ModelStruct' );
        return $stmt->fetch();
    }

    public static function createRecord( $data ) {
        $sql = "INSERT INTO qa_models ( label ) " .
            " VALUES ( :label ) ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( array( 'label' => $data['label'] ) );
        $lastId = $conn->lastInsertId();

        return self::findById( $lastId );
    }

}
