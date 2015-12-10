<?php

namespace LQA;
use \Log as Log ;

class EntryDao extends \DataAccess_AbstractDao {
    protected function _buildResult( $array_result ) {
    }

    public static function findById( $id ) {
        $sql = "SELECT qa_entries.*, qa_categories.label AS category " .
            " FROM qa_entries " .
            " LEFT JOIN qa_categories ON qa_categories.id = id_category " .
            " WHERE qa_entries.id = :id LIMIT 1" ;

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute(array('id' => $id));
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\EntryStruct' );
        return $stmt->fetch();
    }

    public static function findAllByTranslationVersion($id_segment, $id_job, $version) {
        $sql = "SELECT qa_entries.*, qa_categories.label as category " .
            " FROM qa_entries " .
            " LEFT JOIN qa_categories ON qa_categories.id = id_category " .
            " WHERE id_job = :id_job AND id_segment = :id_segment " .
            " AND translation_version = :translation_version " .
            " ORDER BY create_date DESC ";

        $opts = array(
            'id_segment' => $id_segment,
            'id_job' => $id_job,
            'translation_version' => $version
        );

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( $opts );

        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\EntryStruct' );
        return $stmt->fetchAll();
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
