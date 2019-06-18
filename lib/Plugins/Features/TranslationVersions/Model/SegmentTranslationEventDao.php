<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/02/2018
 * Time: 17:20
 */

namespace Features\TranslationVersions\Model ;

class SegmentTranslationEventDao extends \DataAccess_AbstractDao {

    const TABLE       = "segment_translation_events";
    const STRUCT_TYPE = "\Features\TranslationVersions\Model\SegmentTranslationEventStruct" ;

    protected static $auto_increment_field = [ 'id' ];
    protected static $primary_keys         = [ 'id' ];

    public function insertForPropagation($propagatedIds, SegmentTranslationEventStruct $struct) {

        $sql = "INSERT INTO " . self::TABLE . " ( id_job, id_segment, uid ,
                status, version_number, source_page, create_date  )
                SELECT :id_job, st.id_segment, :uid, st.status, st.version_number, :source_page, :create_date
                FROM segment_translations st WHERE st.id_segment IN ( " .
                implode(',', $propagatedIds ) . " ) " ;

        $conn = $this->getDatabaseHandler()->getConnection() ;
        $stmt = $conn->prepare( $sql );

        $struct->setTimestamp('create_date', time() );

        $stmt->execute( $struct->toArray(['id_job', 'uid', 'source_page', 'create_date']) ) ;

        return $stmt->rowCount() ;
    }

    /**
     * @param $id_job
     * @param $min_segment
     * @param $max_segment
     *
     * @return \Features\TranslationVersions\Model\SegmentTranslationEventStruct[]
     */
    public function getLatestEventsInSegmentInterval( $id_job, $min_segment, $max_segment ) {
        $sql = "SELECT * FROM segment_translation_events WHERE id IN (  " .
                " SELECT max(id) FROM segment_translation_events " .
                " WHERE id_job = :id_job  " .
                " AND id_segment >= :min_segment AND id_segment <= :max_segment " .
                " GROUP BY id_segment ) ORDER BY id_segment " ;

        $conn = $this->getDatabaseHandler()->getConnection() ;
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, self::STRUCT_TYPE );
        $stmt->execute( [
            'id_job'      => $id_job,
            'min_segment' => $min_segment,
            'max_segment' => $max_segment
        ] );

        return $stmt->fetchAll();
    }

    public function getFinalRevisionsForSegment( $id_job, $id_segment ) {
        $sql = "SELECT * FROM segment_translation_events
                WHERE id_job = :id_job
                    AND id_segment = :id_segment
                    AND final_revision = 1
                " ;

        $conn = $this->getDatabaseHandler()->getConnection() ;
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, self::STRUCT_TYPE );
        $stmt->execute( [
                'id_job'      => $id_job,
                'id_segment' => $id_segment
        ] );

        return $stmt->fetchAll();
    }

    /**
     * @param $id_job
     * @param $id_segment
     *
     * @return SegmentTranslationEventStruct|null
     */
    public function getLatestEventForSegment( $id_job, $id_segment ) {
        $latest_events = $this->getLatestEventsInSegmentInterval( $id_job, $id_segment, $id_segment ) ;
        if ( $latest_events ) {
            return $latest_events[0] ;
        }
        return null ;
    }
}