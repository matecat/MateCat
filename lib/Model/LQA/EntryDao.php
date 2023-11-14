<?php

namespace LQA;

use Chunks_ChunkStruct;
use DataAccess\ShapelessConcreteStruct;
use DataAccess_AbstractDao;
use DataAccess_IDaoStruct;
use Database;
use Exceptions\ValidationError;
use Log;
use PDO;
use Utils;

class EntryDao extends DataAccess_AbstractDao {
    protected function _buildResult( $array_result ) {
    }

    /**
     * @param array $ids
     * @return array
     */
    public static function getBySegmentIds(array $ids = [])
    {
        $sql = "SELECT 
            q.id_job,
            q.id_segment,
            q.source_page,
            q.id_category,
            q.severity,
            q.translation_version,
            q.penalty_points,
            q.create_date,
            cat.label as cat_label
        FROM
            qa_entries q
                LEFT JOIN
            qa_categories cat ON q.id_category = cat.id
        WHERE
            q.deleted_at IS NULL
                AND q.id_segment IN ( " . implode(', ' , $ids ) . " ) ";
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute();
        $stmt->setFetchMode( PDO::FETCH_CLASS, ShapelessConcreteStruct::class );

        return $stmt->fetchAll();
    }

    public static function updateRepliesCount( $id ) {
        $sql = "UPDATE qa_entries SET replies_count = " .
                " ( SELECT count(*) FROM " .
                " qa_entry_comments WHERE id_qa_entry = :id " .
                " ) WHERE id = :id ";

        Log::doJsonLog( $sql );

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        return $stmt->execute( [ 'id' => $id ] );
    }

    public static function hardDeleteEntry( EntryStruct $record ) {
        $sql = "DELETE FROM qa_entries WHERE id = :id ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        return $stmt->execute( [ 'id' => $record->id ] );
    }

    public static function deleteEntry( EntryStruct $record ) {
        $sql = "UPDATE qa_entries SET deleted_at = :deleted_at WHERE id = :id ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        return $stmt->execute( [
                'id'         => $record->id,
                'deleted_at' => Utils::mysqlTimestamp( time() )
        ] );
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
                " WHERE qa_entries.id = :id AND qa_entries.deleted_at IS NULL LIMIT 1";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [ 'id' => $id ] );
        $stmt->setFetchMode( PDO::FETCH_CLASS, 'LQA\EntryStruct' );

        return $stmt->fetch();
    }

    /**
     * @param Chunks_ChunkStruct $chunk
     *
     * @return EntryStruct[]
     */
    public static function findAllByChunk( Chunks_ChunkStruct $chunk ) {
        $sql = "SELECT qa_entries.*, qa_categories.label as category_label FROM qa_entries
          JOIN segment_translations
            ON segment_translations.id_segment = qa_entries.id_segment
            AND qa_entries.id_job = segment_translations.id_job
          JOIN jobs
            ON jobs.id = qa_entries.id_job
          JOIN qa_categories ON qa_categories.id = qa_entries.id_category
           WHERE
            qa_entries.deleted_at IS NULL AND
            qa_entries.id_job = :id AND jobs.password = :password ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [ 'id' => $chunk->id, 'password' => $chunk->password ] );
        $stmt->setFetchMode( PDO::FETCH_CLASS, '\DataAccess\ShapelessConcreteStruct' );

        return $stmt->fetchAll();
    }

    /**
     * @param      $id_segment
     * @param null $source_page
     *
     * @return array
     */
    public static function findAllBySegmentId( $id_segment, $source_page = null ) {

        $sql = "SELECT * FROM qa_entries WHERE qa_entries.deleted_at IS NULL AND id_segment = :id_segment ";

        $data = [ 'id_segment' => $id_segment ];
        if ( !is_null( $source_page ) ) {
            $data[ 'source_page' ] = $source_page;
            $sql                   .= " AND source_page = :source_page ";
        }


        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( $data );
        $stmt->setFetchMode( PDO::FETCH_CLASS, '\DataAccess\ShapelessConcreteStruct' );

        return $stmt->fetchAll();
    }

    public static function findByIdSegmentAndSourcePage( $id_segment, $id_job, $source_page ) {
        $sql = "SELECT qa_entries.*, qa_categories.label as category " .
                " FROM qa_entries " .
                " LEFT JOIN qa_categories ON qa_categories.id = id_category " .
                " WHERE id_job = :id_job AND id_segment = :id_segment " .
                " AND qa_entries.deleted_at IS NULL " .
                " AND qa_entries.source_page = :source_page " .
                " ORDER BY create_date DESC ";

        $opts = [
                'id_segment'  => $id_segment,
                'id_job'      => $id_job,
                'source_page' => $source_page
        ];

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( $opts );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'LQA\EntryWithCategoryStruct' );

        return $stmt->fetchAll();
    }

    public static function findAllByTranslationVersion( $id_segment, $id_job, $version ) {
        $sql = "SELECT qa_entries.*, qa_categories.label as category " .
                " FROM qa_entries " .
                " LEFT JOIN qa_categories ON qa_categories.id = id_category " .
                " WHERE id_job = :id_job AND id_segment = :id_segment " .
                " AND qa_entries.deleted_at IS NULL " .
                " AND translation_version = :translation_version " .
                " ORDER BY create_date DESC ";

        $opts = [
                'id_segment'          => $id_segment,
                'id_job'              => $id_job,
                'translation_version' => $version
        ];

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( $opts );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'LQA\EntryStruct' );

        return $stmt->fetchAll();
    }

    public static function getCountByIdJobAndSourcePage( $id_job, $source_page ) {
        $sql = "SELECT count(qa_entries.id) as count  " .
                " FROM qa_entries " .
                " WHERE id_job = :id_job " .
                " AND qa_entries.deleted_at IS NULL " .
                " AND source_page = :source_page " ;

        $opts = [
                'id_job'      => $id_job,
                'source_page' => $source_page
        ];

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( $opts );

        $stmt->setFetchMode( PDO::FETCH_ASSOC );

        return $stmt->fetch();
    }

    /**
     * @param EntryStruct $entryStruct
     *
     * @return EntryStruct
     * @throws ValidationError
     */
    public static function createEntry( EntryStruct $entryStruct ) {

        $entryStruct = self::ensureStartAndStopPositionAreOrdered( $entryStruct );
        $entryStruct->setDefaults();

        $sql  = "INSERT INTO qa_entries 
             ( 
             id_segment, id_job, id_category, severity, 
             translation_version, start_node, start_offset, 
             end_node, end_offset, 
             is_full_segment, penalty_points, comment, 
             target_text, uid, source_page 
             ) VALUES ( 
                :id_segment, 
                :id_job, 
                :id_category, 
                :severity, 
                :translation_version, 
                :start_node, 
                :start_offset, 
                :end_node, 
                :end_offset, 
                :is_full_segment, 
                :penalty_points, 
                :comment, 
                :target_text, 
                :uid, 
                :source_page 
             ); 
        ";
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $values = $entryStruct->toArray(
                [
                        'id_segment', 'id_job', 'id_category', 'severity',
                        'translation_version', 'start_node', 'start_offset',
                        'end_node', 'end_offset', 'is_full_segment',
                        'penalty_points', 'comment', 'target_text', 'uid', 'source_page'
                ]
        );

        $stmt->execute( $values );
        $lastId = $conn->lastInsertId();

        $entryStruct->id = $lastId;

        return $entryStruct;
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
     *
     * @return EntryStruct
     *
     */
    private static function ensureStartAndStopPositionAreOrdered( $entryStruct ) {

        Log::doJsonLog( $entryStruct );

        if ( $entryStruct->start_node == $entryStruct->end_node ) {
            // if start node and stop node are the same, just order the offsets if needed
            if ( intval( $entryStruct->start_offset ) > intval( $entryStruct->end_offset ) ) {
                $tmp                       = $entryStruct->start_offset;
                $entryStruct->start_offset = $entryStruct->end_offset;
                $entryStruct->end_offset   = $tmp;
                unset( $tmp );
            }
        } else {
            if ( intval( $entryStruct->start_node > intval( $entryStruct->end_node ) ) ) {
                // in this case selection was backward, invert both nodes and
                // offsets.
                $tmp                       = $entryStruct->start_offset;
                $entryStruct->start_offset = $entryStruct->end_offset;
                $entryStruct->end_offset   = $tmp;

                $tmp                     = $entryStruct->start_node;
                $entryStruct->start_node = $entryStruct->end_node;
                $entryStruct->end_node   = $tmp;
            } else {
                // in any other case leave everything as is
            }
        }

        return $entryStruct;

    }

    /**
     * Function to update the rebutted_at column
     *
     * @param Integer $id        ID of the Entry
     * @param Boolean $isToRebut If true rebut, else undo rebut
     *
     * @return EntryStruct
     *
     */
    public function updateRebutted( $id, $isToRebut ) {
        $rebutted_at = null;

        if ( $isToRebut === true ) {
            $rebutted_at = date( 'Y-m-d H:i:s' );
        }

        $sql = "  UPDATE qa_entries "
                . "   SET rebutted_at = :rebutted_at "
                . " WHERE id = :id ; ";

        $opts = [
                'rebutted_at' => $rebutted_at,
                'id'          => $id
        ];

        $stmt = $this->database->prepare( $sql );

        $stmt->execute( $opts );

        return $this->findById( $opts[ 'id' ] );
    }

    /**
     * @param      $id_job
     * @param      $password
     * @param      $revisionNumber
     * @param null $idFilePart
     * @param int  $ttl
     *
     * @return DataAccess_IDaoStruct[]
     */
    public function getIssuesGroupedByIdFilePart( $id_job, $password, $revisionNumber, $idFilePart = null, $ttl = 0) {

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $sql     = "SELECT
                s.internal_id as content_id,
                s.id as segment_id,
                e.severity as severity_label,
                penalty_points,
                severities,
                options as cat_options,
                label as cat_label
            FROM
                qa_entries e
                    JOIN
                segments s ON s.id = e.id_segment
                    JOIN
                jobs j ON j.id = e.id_job
                JOIN
                qa_categories c ON e.id_category = c.id
            
                WHERE
                    e.id_job = :id_job
                    AND j.password = :password
                    AND e.source_page = :revisionNumber
                    AND e.deleted_at IS NULL";

        $params = [
            'id_job'   => $id_job,
            'password' => $password,
            'revisionNumber' => $revisionNumber
        ];

        if($idFilePart){
            $sql .= " AND id_file_part = :id_file_part";
            $params['id_file_part'] = $idFilePart;
        }

        $stmt = $conn->prepare( $sql );

        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), $params );
    }
}
