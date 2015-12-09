<?php

namespace LQA;
use \Log as Log ;

class EntryDao extends \DataAccess_AbstractDao {
    protected function _buildResult( $array_result ) {
    }

    public static function findById( $id ) {
        $sql = "SELECT * FROM qa_entries WHERE id = :id LIMIT 1" ;
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute(array('id' => $id));
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\EntryStruct' );
        return $stmt->fetch();
    }

    public static function createEntry( $data ) {
        $sql = "INSERT INTO qa_entries " .
            " ( " .
            " id_segment, id_job, id_category, severity, " .
            " translation_version, start_position, stop_position, " .
            " is_full_segment, penalty_points, comment, " .
            " target_text " .
            " ) VALUES ( " .
            " :id_segment, :id_job, :id_category, :severity, " .
            " :translation_version, :start_position, :stop_position, " .
            " :is_full_segment, :penalty_points, :comment, " .
            " :target_text " .
            " ) ; " ;

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        try {
            $stmt->execute( $data );
        } catch ( \Exception $e ) {
            // FIXME: this was required because Klein does not handle SQL
            // exceptions correctly.
            Log::doLog( $e->getMessage() );
        }

        $lastId = $conn->lastInsertId();
        return self::findById( $lastId );
    }

}
