<?php

namespace LQA;
use \Log as Log ;

class EntryDao extends \DataAccess_AbstractDao {
    protected function _buildResult( $array_result ) {
    }

    public static function updateRepliesCount( $id ) {
        $sql = "UPDATE qa_entries SET replies_count = " .
            " ( SELECT count(*) FROM " .
            " qa_entry_comments WHERE id_qa_entry = :id " .
            " ) WHERE id = :id ";

        \Log::doLog( $sql );

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        return $stmt->execute( array( 'id' => $id ) );
    }

    public static function deleteEntry( EntryStruct $record ) {
        $sql = "DELETE FROM qa_entries WHERE id = :id ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);
        return $stmt->execute( array( 'id' => $record->id ));
    }

    /**
     * @param $id
     *
     * @return EntryStruct
     */
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

    /**
     * @param \Chunks_ChunkStruct $chunk
     *
     * @return EntryStruct[]
     */
    public static function findAllByChunk( \Chunks_ChunkStruct $chunk ) {
        $sql = "SELECT qa_entries.* FROM qa_entries
          JOIN segment_translations
            ON segment_translations.id_segment = qa_entries.id_segment
          JOIN jobs
            ON jobs.id = segment_translations.id_job
            AND jobs.password = :password AND jobs.id = :id ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute(array('id' => $chunk->id, 'password' => $chunk->password ));
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\EntryStruct' );
        return $stmt->fetchAll();
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

    /**
     * @param $data
     *
     * @return EntryStruct
     * @throws \Exceptions\ValidationError
     */
    public static function createEntry( $data ) {
        $data = self::ensureStartAndStopPositionAreOrdered( $data ) ;

        $struct = new EntryStruct( $data );
        $struct->ensureValid();
        $struct->setDefaults();

        $sql = "INSERT INTO qa_entries " .
            " ( " .
            " id_segment, id_job, id_category, severity, " .
            " translation_version, start_node, start_offset, " .
            " end_node, end_offset, " .
            " is_full_segment, penalty_points, comment, " .
            " target_text, uid " .
            " ) VALUES ( " .
            " :id_segment, :id_job, :id_category, :severity, " .
            " :translation_version, :start_node, :start_offset, " .
            " :end_node, :end_offset, " .
            " :is_full_segment, :penalty_points, :comment, " .
            " :target_text, :uid " .
            " ) ; " ;

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $values = $struct->attributes(
            array(
                'id_segment', 'id_job', 'id_category', 'severity',
                'translation_version', 'start_node', 'start_offset',
                'end_node', 'end_offset', 'is_full_segment',
                'penalty_points', 'comment', 'target_text', 'uid')
        );

        $stmt->execute( $values );
        $lastId = $conn->lastInsertId();

        return self::findById( $lastId );
    }

    /**
     * This funciton is to ensure that start and stop nodes and offsets are
     * from the minor to the major.
     *
     * In normal selection ( left to right )
     * start and stop nodes are always ordered from minor to major.
     * When selection is done right to left, nodes may be provided in inverse
     * order ( from major to minor).
     *
     * This silent correction of provided data is to reduce the amount of work
     * required on the clients.
     */
    private static function ensureStartAndStopPositionAreOrdered($data) {
        \Log::doLog( $data );

        if ( $data['start_node'] == $data['end_node'] ) {
            // if start node and stop node are the same, just order the offsets if needed
            if ( intval( $data['start_offset'] ) > intval( $data['end_offset'] )) {
                $tmp = $data['start_offset'] ;
                $data['start_offset'] = $data['end_offset'];
                $data['end_offset'] = $tmp ;
                unset($tmp);
            }
        }
        else if ( intval( $data['start_node'] > intval( $data['end_node'] ) ) ) {
            // in this case selection was backward, invert both nodes and
            // offsets.
            $tmp = $data['start_offset'] ;
            $data['start_offset'] = $data['end_offset'];
            $data['end_offset'] = $tmp ;

            $tmp = $data['start_node'] ;
            $data['start_node'] = $data['end_node'];
            $data['end_node'] = $tmp ;
        }
        else {
            // in any other case leave everything as is
        }

        return $data;

    }

    /**
     * Function to update the rebutted_at column
     *
     * @param Integer   $id         ID of the Entry
     * @param Boolean   $isToRebut  If true rebut, else undo rebut
     *
     * @return EntryStruct
     *
     */
    public function updateRebutted( $id, $isToRebut ) {
        $rebutted_at = null;

        if( $isToRebut === true ) {
            $rebutted_at = date('Y-m-d H:i:s');
        }

        $sql =  "  UPDATE qa_entries "
                . "   SET rebutted_at = :rebutted_at "
                . " WHERE id = :id ; " ;

        $opts = array(
            'rebutted_at' => $rebutted_at,
            'id' => $id
        );

        $stmt = $this->con->prepare( $sql );

        $stmt->execute( $opts );

        return $this->findById( $opts['id'] );
    }

}