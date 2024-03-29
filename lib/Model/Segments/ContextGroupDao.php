<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 24/09/18
 * Time: 16.07
 *
 */

namespace Segments;

use DataAccess_AbstractDao;
use DataAccess_IDaoStruct;
use Projects_ProjectStruct;

class ContextGroupDao extends DataAccess_AbstractDao {

    const TABLE       = 'context_groups';
    const STRUCT_TYPE = "ContextStruct";
    protected static $auto_increment_field = [ 'id' ];
    protected static $primary_keys         = [ 'id', 'id_project' ];

    protected static $query_get_all_by_project   = "SELECT * FROM context_groups WHERE id_project = :id_project";
    protected static $query_get_all_by_file_id   = "SELECT * FROM context_groups WHERE id_file = :id_file";
    protected static $query_get_by_segment_id    = "SELECT * FROM context_groups WHERE id_segment = :id_segment";
    protected static $query_get_by_segment_range = "SELECT * FROM context_groups WHERE id_segment BETWEEN :id_segment_start AND :id_segment_stop";

    /**
     * @param Projects_ProjectStruct $project
     *
     * @return DataAccess_IDaoStruct[]
     */
    public function getAllByProject( Projects_ProjectStruct $project ) {
        $stmt = $this->_getStatementForCache( self::$query_get_all_by_project );

        return $this->_fetchObject( $stmt,
                $project,
                [
                        'id_project' => $project->id
                ]
        );
    }

    public function getBySegmentID( $sid ) {
        $stmt = $this->_getStatementForCache( self::$query_get_by_segment_id );

        return $this->_fetchObject( $stmt,
                new ContextStruct(),
                [
                        'id_segment' => $sid
                ]
        )[ 0 ];
    }

    public function getByFileID( $fid ) {
        $stmt = $this->_getStatementForCache( self::$query_get_all_by_file_id );

        return $this->_fetchObject( $stmt,
                new ContextStruct(),
                [
                        'id_file' => $fid
                ]
        );
    }

    public function getBySIDRange( $start, $stop ) {
        $stmt = $this->_getStatementForCache( self::$query_get_by_segment_range );
        /** @var ContextStruct[] $resSet */
        $resSet      = $this->_fetchObject( $stmt,
                new ContextStruct(),
                [
                        'id_segment_start' => $start,
                        'id_segment_stop'  => $stop
                ]
        );
        $_fetchGroup = [];
        foreach ( $resSet as $cStruct ) {
            $_fetchGroup[ $cStruct->id_segment ] = $cStruct;
        }
        unset( $resSet );

        return $_fetchGroup;
    }

}