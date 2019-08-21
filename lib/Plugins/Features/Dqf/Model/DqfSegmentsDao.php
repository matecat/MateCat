<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/07/2017
 * Time: 12:10
 */

namespace Features\Dqf\Model;


use DataAccess_AbstractDao;
use PDO;

class DqfSegmentsDao extends DataAccess_AbstractDao {
    const TABLE = 'dqf_segments';

    protected static $primary_keys         = ['id_segment'];
    protected static $auto_increment_field = [] ;

    public function getByIdSegment( $id_segment ) {
        $sql = "SELECT * FROM dqf_segments WHERE id_segment = ?" ;

        $conn = $this->getDatabaseHandler()->getConnection() ;
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [ $id_segment ] );

        $stmt->setFetchMode( PDO::FETCH_CLASS, '\Features\Dqf\Model\DqfSegmentsStruct' ) ;

        return $stmt->fetch();
    }

    /**
     * Returns a map that is an array whith key = id_segment and value = dqf_id_seg ;
     *
     * @param $min
     * @param $max
     *
     * @return array
     */
    public function getByIdSegmentRange( $min, $max ) {
        $sql = "SELECT * FROM dqf_segments WHERE id_segment >= ? AND id_segment <= ? " ;

        $conn = $this->getDatabaseHandler()->getConnection() ;
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [ $min, $max ] );

        $stmt->setFetchMode( PDO::FETCH_CLASS, '\Features\Dqf\Model\DqfSegmentsStruct' ) ;

        $result = [] ;
        while( $row = $stmt->fetch() ) {
            $result[ $row->id_segment ] = [
                    'dqf_segment_id'     => $row->dqf_segment_id,
                    'dqf_translation_id' => $row->dqf_translation_id
            ];
        }

        return $result ;
    }

    public function insertBulkMapForTranslationId( array $structs ) {
        $sql = " INSERT INTO dqf_segments (id_segment, dqf_translation_id) VALUES " ;
        $sql .= implode(', ', array_fill( 0, count( $structs ), " ( ?, ? ) " ) ) ;
        $sql .= " ON DUPLICATE KEY UPDATE dqf_segments.dqf_translation_id = VALUES(dqf_segments.dqf_translation_id) " ;

        $conn = $this->getDatabaseHandler()->getConnection() ;

        $stmt = $conn->prepare( $sql );
        $flattened_values = array_reduce( $structs, 'array_merge', array() );

        $result = $stmt->execute( $flattened_values );

        if ( !$result ) {
            throw new \Exception('Error during bulk save of dqf_segments: ' . var_export( $flattened_values, true)  ) ;
        }
    }

    /**
     * @param array $structs
     */
    public function insertBulkMap( array $structs ) {
        $sql = " INSERT INTO dqf_segments (id_segment, dqf_segment_id, dqf_translation_id) VALUES " ;
        $sql .= implode(', ', array_fill( 0, count( $structs ), " ( ?, ?, ? ) " ) );

        $conn = $this->getDatabaseHandler()->getConnection() ;

        $stmt = $conn->prepare( $sql );
        $flattened_values = array_reduce( $structs, 'array_merge', array() );
        $result = $stmt->execute( $flattened_values );

        if ( !$result ) {
            throw new \Exception('Error during bulk save of dqf_segments: ' . var_export( $flattened_values, true)  ) ;

        }
    }
}