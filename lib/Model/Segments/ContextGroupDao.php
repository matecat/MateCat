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
use Database;
use Exception;
use Log;

class ContextGroupDao extends DataAccess_AbstractDao {

    const TABLE = 'context_groups' ;
    const STRUCT_TYPE = "ContextStruct";
    protected static $auto_increment_field = ['id'];
    protected static $primary_keys         = ['id', 'id_project'];

    protected static $query_get_all_by_project = "SELECT * FROM context_groups WHERE id_project = :id_project";
    protected static $query_get_all_by_file_id = "SELECT * FROM context_groups WHERE id_file = :id_file";
    protected static $query_get_by_segment_id = "SELECT * FROM context_groups WHERE id_segment = :id_segment";
    protected static $query_get_by_segment_range = "SELECT * FROM context_groups WHERE id_segment BETWEEN :id_segment_start AND :id_segment_stop";

    /**
     * @param \Projects_ProjectStruct $project
     *
     * @return \DataAccess_IDaoStruct[]
     */
    public function getAllByProject( \Projects_ProjectStruct $project ){
        $stmt = $this->_getStatementForCache( self::$query_get_all_by_project );
        return $this->_fetchObject( $stmt,
                $project,
                array(
                        'id_project' => $project->id
                )
        );
    }

    public function getBySegmentID( $sid ){
        $stmt = $this->_getStatementForCache( self::$query_get_by_segment_id );
        return $this->_fetchObject( $stmt,
                new ContextStruct(),
                array(
                        'id_segment' => $sid
                )
        )[0];
    }

    public function getByFileID( $fid ){
        $stmt = $this->_getStatementForCache( self::$query_get_all_by_file_id );
        return $this->_fetchObject( $stmt,
                new ContextStruct(),
                array(
                        'id_file' => $fid
                )
        );
    }

    public function getBySIDRange( $start, $stop ){
        $stmt = $this->_getStatementForCache( self::$query_get_by_segment_range );
        /** @var ContextStruct[] $resSet */
        $resSet = $this->_fetchObject( $stmt,
                new ContextStruct(),
                array(
                        'id_segment_start' => $start,
                        'id_segment_stop'  => $stop
                )
        );
        $_fetchGroup = [];
        foreach( $resSet as $offset => $cStruct ){
            $_fetchGroup[ $cStruct->id_segment ] = $cStruct;
        }
        unset( $resSet );
        return $_fetchGroup;
    }

    /**
     * @param $projectStructure
     *
     * @throws Exception
     */
    public static function bulkInsertTUFromProjectStructure( $projectStructure ) {

        $template = " INSERT INTO " . self::TABLE . " ( id_project, id_segment, context_json ) VALUES " ;

        $insert_values = array();
        $chunk_size = 30;

        $id_project = $projectStructure[ 'id_project' ];

        foreach ( $projectStructure[ 'context-group' ] as $internal_id => $v ) {

            $context_json  = json_encode( $v[ 'context_json' ] );
            $segments = $v[ 'context_json_segment_ids' ];

            foreach ( $segments as $id_segment ) {
                $insert_values[] = array( $id_project, $id_segment, $context_json );
            }

        }

        $chunked = array_chunk( $insert_values, $chunk_size ) ;
        Log::doLog( "Notes: Total Rows to insert: " . count( $chunked ) );

        $conn = Database::obtain()->getConnection();

        try {

            foreach( $chunked as $i => $chunk ) {
                $values_sql_array = array_fill( 0, count($chunk), " ( ?, ?, ? ) " ) ;
                $stmt = $conn->prepare( $template . implode( ', ', $values_sql_array )) ;
                $flattened_values = array_reduce( $chunk, 'array_merge', array() );
                $stmt->execute( $flattened_values ) ;
                Log::doLog( "Notes: Executed Query " . ( $i + 1 ) );
            }

        } catch ( Exception $e ){
            Log::doLog( "Trans-Unit Context Groups import - DB Error: " . $e->getMessage() . " - \n" );
            /** @noinspection PhpUndefinedVariableInspection */
            Log::doLog( "Trans-Unit Context Groups import - Statement: " . $stmt->queryString . "\n" );
            Log::doLog( "Trans-Unit Context Groups Chunk Dump: " . var_export( $chunk , true ) . "\n" );
            /** @noinspection PhpUndefinedVariableInspection */
            Log::doLog( "Trans-Unit Context Groups Flattened Values Dump: " . var_export( $flattened_values , true ) . "\n" );
            throw new Exception( "Notes import - DB Error: " . $e->getMessage(), 0 , $e );
        }

    }

}