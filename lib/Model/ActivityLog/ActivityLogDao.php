<?php
/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 13/06/16
 * Time: 17:59
 */

namespace ActivityLog;

use DataAccess_AbstractDao;
use DataAccess_IDaoStruct;
use Database;
use Exception;
use PDO;
use PDOStatement;

class ActivityLogDao extends DataAccess_AbstractDao {

    public function create( ActivityLogStruct $activityStruct ) {

        $conn = Database::obtain()->getConnection();
        $jobStructToArray = $activityStruct->toArray(
                array(
                        'id_job',
                        'id_project',
                        'uid',
                        'action',
                        'ip',
                        'event_date',
                        'memory_key'
                )
        );
        $columns = array_keys( $jobStructToArray );
        $values = array_values( $jobStructToArray );

        $stmt = $conn->prepare( 'INSERT INTO `activity_log` ( ' . implode( ',', $columns ) . ' ) VALUES ( ' . implode( ',' , array_fill( 0, count( $values ), '?' ) ) . ' )' );

        foreach( $values as $k => $v ){
            $stmt->bindValue( $k +1, $v ); //Columns/Parameters are 1-based
        }

        $stmt->execute();

        return $conn->lastInsertId();
        
    }

    /**
     * This method is not static and used to cache at Redis level the values for this Job
     *
     * Use when counters of the job value are not important but only the metadata are needed
     *
     * XXX: Be careful, used by the ActivityLogStruct
     *
     * @see      \AsyncTasks\Workers\ActivityLogWorker
     * @see      \ActivityLog\ActivityLogStruct
     *
     * @param ActivityLogStruct $activityQuery
     *
     * @return ActivityLogStruct[]
     */
    public function read( ActivityLogStruct $activityQuery ){

        $stmt = $this->_getStatementForCache();
        return $this->_fetchObject( $stmt,
                $activityQuery,
                array(
                        'id_project' => $activityQuery->id_project
                )
        );

    }

    /**
     *
     * @return PDOStatement
     */
    protected function _getStatementForCache() {

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
                "SELECT * FROM activity_log " .
                " LEFT JOIN users USING( uid ) " .
                " WHERE " .
                " id_project = :id_project "
        );

        return $stmt;
    }

    /**
     * @param array $array_result
     *
     * @return DataAccess_IDaoStruct|DataAccess_IDaoStruct[]|void
     */
    protected function _buildResult( $array_result ){}

    /**
     * Destroy a cached object
     *
     * @param ActivityLogStruct $activityQuery
     *
     * @return bool
     * @throws Exception
     */
    public function destroyCache( ActivityLogStruct $activityQuery ){
        /*
        * build the query
        */
        $stmt = $this->_getStatementForCache();
        return $this->_destroyObjectCache( $stmt,
                array(
                        'id_project' => $activityQuery->id_project
                )
        );
    }

    public static function getByID( $activity_id ){

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare("SELECT * FROM activity_log WHERE id = ?");
        $stmt->setFetchMode( PDO::FETCH_CLASS, 'ActivityLogStruct' );
        $stmt->execute( array( $activity_id ) );

        return $stmt->fetch();

    }

}