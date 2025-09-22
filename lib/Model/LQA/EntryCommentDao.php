<?php

namespace Model\LQA;

use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use PDO;

class EntryCommentDao extends AbstractDao {

    /**
     * @param $id_issue
     *
     * @return EntryCommentStruct[]
     */
    public function findByIssueId( $id_issue ): array {
        $sql  = "SELECT * FROM qa_entry_comments WHERE id_qa_entry = ? " .
                " ORDER BY create_date DESC ";
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, EntryCommentStruct::class );
        $stmt->execute( [ $id_issue ] );

        return $stmt->fetchAll();
    }

    /**
     * @param array $data
     *
     * @return EntryCommentStruct
     */
    public function createComment( array $data ): EntryCommentStruct {
        $struct              = new EntryCommentStruct( $data );
        $struct->create_date = date( 'Y-m-d H:i:s' );


        $sql = "INSERT INTO qa_entry_comments " .
                " ( uid, id_qa_entry, create_date, comment, source_page ) " .
                " VALUES " .
                " ( :uid, :id_qa_entry, :create_date, :comment, :source_page ) ";

        $conn = Database::obtain()->getConnection();
        Database::obtain()->begin();

        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, EntryCommentStruct::class );
        $result = $stmt->execute( $struct->toArray(
                [ 'uid', 'id_qa_entry', 'create_date', 'comment', 'source_page' ]
        ) );
        $lastId = $conn->lastInsertId();

        if ( $result ) {
            EntryDao::updateRepliesCount( $struct->id_qa_entry );
        }
        $struct->id = $lastId;

        $conn->commit();

        return $struct;
    }

    public function findById( $id ): ?EntryCommentStruct {
        $sql  = "SELECT * FROM qa_entry_comments WHERE id = ? ";
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, EntryCommentStruct::class );
        $stmt->execute( [ $id ] );

        return $stmt->fetch() ?: null;
    }

    /**
     * Fetches comments grouped by issue IDs.
     *
     * @param array $ids
     *
     * @return array
     */
    public function fetchCommentsGroupedByIssueIds( array $ids ): array {
        $sql = "SELECT id_qa_entry, qa_entry_comments.* FROM qa_entry_comments WHERE id_qa_entry " .
                " IN ( " . implode( ', ', $ids ) . " ) " .
                " ORDER BY id_qa_entry, id ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute();

        return $stmt->fetchAll( PDO::FETCH_GROUP | PDO::FETCH_ASSOC );
    }

    protected function _buildResult( array $array_result ) {
    }

}
