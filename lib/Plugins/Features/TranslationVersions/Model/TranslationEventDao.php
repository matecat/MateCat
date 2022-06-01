<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/02/2018
 * Time: 17:20
 */

namespace Features\TranslationVersions\Model;

use DataAccess\ShapelessConcreteStruct;
use PDO;

class TranslationEventDao extends \DataAccess_AbstractDao {

    const TABLE       = "segment_translation_events";
    const STRUCT_TYPE = "\Features\TranslationVersions\Model\TranslationEventStruct";

    protected static $auto_increment_field = [ 'id' ];
    protected static $primary_keys         = [ 'id' ];

    /**
     * @param $id_job
     * @param $min_segment
     * @param $max_segment
     *
     * @return TranslationEventStruct[]
     */
    public function getLatestEventsInSegmentInterval( $id_job, $min_segment, $max_segment ) {

        $sql = "SELECT * FROM  segment_translation_events 
                JOIN (
                        SELECT max(id) as _m_id FROM segment_translation_events
                        WHERE id_job = :id_job
                        AND id_segment BETWEEN :min_segment AND :max_segment
                        GROUP BY id_segment 
                ) AS X ON _m_id = segment_translation_events.id
                ORDER BY id_segment";

        $conn = $this->getDatabaseHandler()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, TranslationEventStruct::class );
        $stmt->execute( [
                'id_job'      => $id_job,
                'min_segment' => $min_segment,
                'max_segment' => $max_segment
        ] );

        return $stmt->fetchAll();
    }

    public function getFinalRevisionForSegmentAndSourcePage( $id_job, $id_segment, $source_page ) {
        $sql = "SELECT * FROM segment_translation_events
                WHERE id_job = :id_job
                    AND id_segment = :id_segment
                    AND final_revision = 1
                    AND source_page = :source_page
                ";

        $conn = $this->getDatabaseHandler()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, TranslationEventStruct::class );
        $stmt->execute( [
                'id_job'      => $id_job,
                'id_segment'  => $id_segment,
                'source_page' => $source_page
        ] );

        return $stmt->fetch(); // expect one result only
    }

    public function getFinalRevisionsForSegment( $id_job, $id_segment ) {
        $sql = "SELECT * FROM segment_translation_events
                WHERE id_job = :id_job
                    AND id_segment = :id_segment
                    AND final_revision = 1
                ";

        $conn = $this->getDatabaseHandler()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, TranslationEventStruct::class );
        $stmt->execute( [
                'id_job'     => $id_job,
                'id_segment' => $id_segment
        ] );

        return $stmt->fetchAll();
    }

    public function getFinalRevisionForSegments( $id_job, $segment_ids ) {
        $sql = "SELECT source_page, segment_translation_events.* FROM segment_translation_events
                WHERE id_job = :id_job
                    AND id_segment IN (" . implode( ',', $segment_ids ) . " )
                    AND final_revision = 1
                ";

        $conn = $this->getDatabaseHandler()->getConnection();
        $stmt = $conn->prepare( $sql );
        // $stmt->setFetchMode( PDO::FETCH_CLASS, self::STRUCT_TYPE );
        $stmt->execute( [
                'id_job' => $id_job,
        ] );

        return $stmt->fetchAll( PDO::FETCH_GROUP | PDO::FETCH_CLASS, TranslationEventStruct::class );
    }

    /**
     * @param $id_job
     * @param $id_segment
     *
     * @return TranslationEventStruct|null
     */
    public function getLatestEventForSegment( $id_job, $id_segment ) {
        $latest_events = $this->getLatestEventsInSegmentInterval( $id_job, $id_segment, $id_segment );
        if ( $latest_events ) {
            return $latest_events[ 0 ];
        }

        return null;
    }

    /**
     * @param array $id_segment_list
     * @param int $id_job
     *
     * @return \DataAccess_IDaoStruct[]
     */
    public function getTteForSegments( $id_segment_list, $id_job ) {
        $in  = str_repeat('?,', count($id_segment_list) - 1) . '?';
        $sql = "
            SELECT 
                    id_segment, 
                    SUM( time_to_edit ) AS tte, 
                    source_page
                FROM
                    segment_translation_events
                WHERE
                    id_segment IN ( $in )
                AND id_job = ?
                GROUP BY id_segment, source_page
                ORDER BY id_segment, source_page
        ";

        $stmt   = $this->_getStatementForCache( $sql );
        $id_segment_list[] = $id_job;

        return @$this->_fetchObject( $stmt, new ShapelessConcreteStruct, $id_segment_list );
    }
}