<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/02/2018
 * Time: 17:20
 */

namespace Features\TranslationEvents\Model;

use Model\DataAccess\AbstractDao;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Database;
use PDO;
use ReflectionException;
use Utils\Constants\TranslationStatus;

class TranslationEventDao extends AbstractDao {

    const TABLE       = "segment_translation_events";
    const STRUCT_TYPE = "\Features\TranslationVersions\Model\TranslationEventStruct";

    protected static array $auto_increment_field = [ 'id' ];
    protected static array $primary_keys         = [ 'id' ];

    public function unsetFinalRevisionFlag( int $id_job, array $id_segments, array $source_pages ): int {

        $sql = " UPDATE segment_translation_events SET final_revision = 0 " .
                " WHERE id_job = :id_job " .
                " AND id_segment IN ( " . implode( ',', $id_segments ) . " ) " .
                " AND source_page IN ( " . implode( ',', $source_pages ) . " ) ";

        $conn = $this->getDatabaseHandler()->getConnection();
        $stmt = $conn->prepare( $sql );

        $stmt->execute( [
                'id_job' => $id_job,
        ] );

        return $stmt->rowCount();
    }

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

    public function getAllFinalRevisionsForSegment( $id_job, $id_segment ) {
        $sql = "SELECT * FROM segment_translation_events
                WHERE id_job = :id_job
                    AND id_segment = :id_segment
                    AND status != :draft
                    AND final_revision = 1
                ";

        $conn = $this->getDatabaseHandler()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, TranslationEventStruct::class );
        $stmt->execute( [
                'id_job'     => $id_job,
                'id_segment' => $id_segment,
                'draft'      => TranslationStatus::STATUS_DRAFT
        ] );

        return $stmt->fetchAll();
    }

    /**
     * @param $id_job
     * @param $id_segment
     *
     * @return TranslationEventStruct|null
     */
    public function getLatestEventForSegment( $id_job, $id_segment ): ?TranslationEventStruct {

        $sql = "SELECT * FROM segment_translation_events
                WHERE id_job = :id_job
                    AND id_segment = :id_segment
                    AND status != :draft
                    ORDER BY id DESC
                    LIMIT 1
                ";

        $conn = $this->getDatabaseHandler()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, TranslationEventStruct::class );
        $stmt->execute( [
                'id_job'     => $id_job,
                'id_segment' => $id_segment,
                'draft'      => TranslationStatus::STATUS_DRAFT
        ] );

        $res = $stmt->fetchAll();

        return $res[ 0 ] ?? null;

    }

    /**
     * @param array $id_segment_list
     * @param int   $id_job
     *
     * @return \Model\DataAccess\IDaoStruct[]
     * @throws ReflectionException
     */
    public function getTteForSegments( $id_segment_list, $id_job ) {
        $in  = str_repeat( '?,', count( $id_segment_list ) - 1 ) . '?';
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

        $stmt              = $this->_getStatementForQuery( $sql );
        $id_segment_list[] = $id_job;

        return $this->_fetchObject( $stmt, new ShapelessConcreteStruct, $id_segment_list ) ?? null;
    }

    /**
     * @param TranslationEventStruct[] $structs
     *
     * @return bool
     */
    public function bulkInsert( array $structs = [] ) {
        $sql = "INSERT INTO segment_translation_events
            (
                `id_job`,
                `id_segment`,
                `uid`,
                `version_number`,
                `source_page`,
                `status`,
                `create_date`,
                `final_revision`,
                `time_to_edit`
            )
            VALUES ";

        $bind_values = [];

        foreach ( $structs as $index => $struct ) {
            $isLast = ( $index === ( count( $structs ) - 1 ) );

            $sql .= "(?,?,?,?,?,?,?,?,?)";

            if ( !$isLast ) {
                $sql .= ',';
            }

            $bind_values[] = $struct->id_job;
            $bind_values[] = $struct->id_segment;
            $bind_values[] = $struct->uid;
            $bind_values[] = $struct->version_number;
            $bind_values[] = $struct->source_page;
            $bind_values[] = $struct->status;
            $bind_values[] = ( ( $struct->create_date instanceof \DateTime ) ? $struct->create_date->format( "Y-m-d H:i:s" ) : $struct->create_date );
            $bind_values[] = $struct->final_revision;
            $bind_values[] = $struct->time_to_edit;
        }

        if ( !empty( $bind_values ) ) {
            $conn = Database::obtain()->getConnection();
            $stmt = $conn->prepare( $sql );

            return $stmt->execute( $bind_values );
        }
    }

    /**
     * @param TranslationEventStruct $struct
     *
     * @return int
     */
    public function insert( TranslationEventStruct $struct ) {
        $sql = "INSERT INTO segment_translation_events
                    (
                        `id_job`,
                        `id_segment`,
                        `uid`,
                        `version_number`,
                        `source_page`,
                        `status`,
                        `create_date`,
                        `final_revision`,
                        `time_to_edit`
                    )
                    VALUES
                    (
                        :id_job,
                        :id_segment,
                        :uid,
                        :version_number,
                        :source_page,
                        :status,
                        :create_date,
                        :final_revision,
                        :time_to_edit
                    )
                ";

        $conn = $this->getDatabaseHandler()->getConnection();
        $stmt = $conn->prepare( $sql );

        $stmt->execute( [
                'id_job'         => $struct->id_job,
                'id_segment'     => $struct->id_segment,
                'uid'            => $struct->uid,
                'version_number' => $struct->version_number,
                'source_page'    => $struct->source_page,
                'status'         => $struct->status,
                'create_date'    => ( ( $struct->create_date instanceof \DateTime ) ? $struct->create_date->format( "Y-m-d H:i:s" ) : $struct->create_date ),
                'final_revision' => $struct->final_revision == 1,
                'time_to_edit'   => $struct->time_to_edit,
        ] );

        return $stmt->rowCount();
    }
}