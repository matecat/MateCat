<?php

namespace LQA;

class EntryCommentDao extends \DataAccess_AbstractDao {

    public function findByIssueId( $id_issue ) {
        $sql = "SELECT * FROM qa_entry_comments WHERE id_qa_entry = ? " .
            " ORDER BY create_date DESC ";
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\EntryCommentStruct' );
        $stmt->execute( array( $id_issue ) );
        return $stmt->fetchAll();
    }

    public function createComment( $data ) {
        $struct = new EntryCommentStruct( $data );
        $struct->ensureValid();
        $struct->create_date = date();
       

        $sql = "INSERT INTO qa_entry_comments " .
            " ( uid, id_qa_entry, create_date, comment, source_page ) " .
            " VALUES " .
            " ( :uid, :id_qa_entry, :create_date, :comment, :source_page ) ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\EntryCommentStruct' );
        $result = $stmt->execute( $struct->attributes(
            array('uid', 'id_qa_entry', 'create_date', 'comment', 'source_page')
        ));
        $lastId = $conn->lastInsertId();

        if ( $result ) {
            \LQA\EntryDao::updateRepliesCount( $struct->id_qa_entry );
        }

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
