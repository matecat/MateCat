<?php

namespace LQA;

class EntryCommentDao extends \DataAccess_AbstractDao {

    public function createComment( $data ) {
        $struct = new EntryCommentStruct( $data );
        $struct->ensureValid();

        $sql = "INSERT INTO qa_entry_comments " .
            " ( uid, id_qa_entry, create_date, comment ) " .
            " VALUES " .
            " ( :uid, :id_qa_entry, :create_date, :comment ) ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\EntryCommentStruct' );
        $stmt->execute( $struct->attributes(
            array('uid', 'id_qa_entry', 'create_date', 'comment')
        ));

        $lastId = $conn->lastInsertId();
        return self::findById( $lastId );
    }

    public function findById( $id ) {
        $sql = "SELECT * FROM qa_entry_comments WHERE id = ? " ;
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\EntryCommentStruct' );
        $stmt->execute( array( $id ) );
        return $stmt->fetch();
    }

    protected function _buildResult( $array_result ) { }

}
