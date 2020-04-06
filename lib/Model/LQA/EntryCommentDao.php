<?php

namespace LQA;

use Database;
use PDO;

class EntryCommentDao extends \DataAccess_AbstractDao {

    public function findByIssueId( $id_issue ) {
        $sql = "SELECT * FROM qa_entry_comments WHERE id_qa_entry = ? " .
            " ORDER BY create_date DESC ";
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, 'LQA\EntryCommentStruct' );
        $stmt->execute( array( $id_issue ) );

        return $stmt->fetchAll();
    }

    /**
     * @param $data
     * @return mixed
     * @deprecated remove the need for insert and find
     */

    public function createComment( $data ) {
        $struct = new EntryCommentStruct( $data );
        $struct->ensureValid();
        $struct->create_date = date('Y-m-d H:i:s');
       

        $sql = "INSERT INTO qa_entry_comments " .
            " ( uid, id_qa_entry, create_date, comment, source_page ) " .
            " VALUES " .
            " ( :uid, :id_qa_entry, :create_date, :comment, :source_page ) ";

        $conn = Database::obtain()->getConnection();
        Database::obtain()->begin();

        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, 'LQA\EntryCommentStruct' );
        $result = $stmt->execute( $struct->toArray(
                [ 'uid', 'id_qa_entry', 'create_date', 'comment', 'source_page' ]
        ) );
        $lastId = $conn->lastInsertId();

        if ( $result ) {
            \LQA\EntryDao::updateRepliesCount( $struct->id_qa_entry );
        }

        $record = self::findById( $lastId );
        $conn->commit() ;
        return $record ;
    }

    public function findById( $id ) {
        $sql = "SELECT * FROM qa_entry_comments WHERE id = ? " ;
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, 'LQA\EntryCommentStruct' );
        $stmt->execute( array( $id ) );
        return $stmt->fetch();
    }

    public function fetchCommentsGroupedByIssueIds( $ids ) {
        $sql = "SELECT id_qa_entry, qa_entry_comments.* FROM qa_entry_comments WHERE id_qa_entry " .
                " IN ( " . implode(', ' , $ids ) . " ) " .
                " ORDER BY id_qa_entry, id " ;

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( );
        return $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
    }

    protected function _buildResult( $array_result ) { }

}
