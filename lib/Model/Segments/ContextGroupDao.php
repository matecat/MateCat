<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 24/09/18
 * Time: 16.07
 *
 */

namespace Model\Segments;

use Model\DataAccess\AbstractDao;
use Model\Projects\ProjectStruct;
use ReflectionException;

class ContextGroupDao extends AbstractDao {

    const string TABLE       = 'context_groups';
    const string STRUCT_TYPE = ContextStruct::class;
    protected static array $auto_increment_field = [ 'id' ];
    protected static array $primary_keys         = [ 'id', 'id_project' ];

    protected static string $query_get_all_by_project   = "SELECT * FROM context_groups WHERE id_project = :id_project";
    protected static string $query_get_all_by_file_id   = "SELECT * FROM context_groups WHERE id_file = :id_file";
    protected static string $query_get_by_segment_id    = "SELECT * FROM context_groups WHERE id_segment = :id_segment";
    protected static string $query_get_by_segment_range = "SELECT * FROM context_groups WHERE id_segment BETWEEN :id_segment_start AND :id_segment_stop";

    /**
     * @param ProjectStruct $project
     *
     * @return ProjectStruct[]
     * @throws ReflectionException
     */
    public function getAllByProject( ProjectStruct $project ): array {
        $stmt = $this->_getStatementForQuery( self::$query_get_all_by_project );

        return $this->_fetchObjectMap( $stmt,
                ProjectStruct::class,
                [
                        'id_project' => $project->id
                ]
        );
    }

    /**
     * @throws ReflectionException
     */
    public function getBySegmentID( $sid ): ?ContextStruct {
        $stmt = $this->_getStatementForQuery( self::$query_get_by_segment_id );

        return $this->_fetchObjectMap( $stmt,
                ContextStruct::class,
                [
                        'id_segment' => $sid
                ]
        )[ 0 ] ?? null;
    }

    /**
     * @return ContextStruct[]
     * @throws ReflectionException
     */
    public function getByFileID( $fid ): array {
        $stmt = $this->_getStatementForQuery( self::$query_get_all_by_file_id );

        return $this->_fetchObjectMap( $stmt,
                ContextStruct::class,
                [
                        'id_file' => $fid
                ]
        );
    }

    /**
     * @param $start
     * @param $stop
     *
     * @return ContextStruct[]
     * @throws ReflectionException
     */
    public function getBySIDRange( $start, $stop ): array {
        $stmt = $this->_getStatementForQuery( self::$query_get_by_segment_range );

        $resSet      = $this->_fetchObjectMap( $stmt,
                ContextStruct::class,
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