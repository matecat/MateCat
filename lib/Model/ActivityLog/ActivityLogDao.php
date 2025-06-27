<?php
/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 13/06/16
 * Time: 17:59
 */

namespace Model\ActivityLog;

use Database;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\IDaoStruct;
use PDO;
use ReflectionException;

class ActivityLogDao extends AbstractDao {

    public string $epilogueString  = "";
    public string $whereConditions = " id_project = :id_project ";

    public function getAllForProject( $id_project ): array {
        $conn = Database::obtain()->getConnection();
        $sql  = "SELECT users.uid, users.email, users.first_name, users.last_name, activity_log.* FROM activity_log
          JOIN users on activity_log.uid = users.uid WHERE id_project = :id_project ORDER BY activity_log.event_date DESC ";

        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, ActivityLogStruct::class );

        $stmt->execute( [ 'id_project' => $id_project ] );

        return $stmt->fetchAll();
    }

    public function getLastActionInProject( $id_project ): array {
        $conn = Database::obtain()->getConnection();
        $sql  = "SELECT users.uid, users.email, users.first_name, users.last_name, activity_log.* FROM activity_log
          JOIN (
           SELECT MAX(id) AS id FROM activity_log WHERE id_project = :id_project GROUP BY id_job
          ) t ON t.id = activity_log.id JOIN users on activity_log.uid = users.uid ORDER BY activity_log.event_date DESC ";

        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, ActivityLogStruct::class );

        $stmt->execute( [ 'id_project' => $id_project ] );

        return $stmt->fetchAll();
    }

    public function create( ActivityLogStruct $activityStruct ): int {

        $conn             = Database::obtain()->getConnection();
        $jobStructToArray = $activityStruct->toArray(
                [
                        'id_job',
                        'id_project',
                        'uid',
                        'action',
                        'ip',
                        'event_date',
                        'memory_key'
                ]
        );
        $columns          = array_keys( $jobStructToArray );
        $values           = array_values( $jobStructToArray );

        $stmt = $conn->prepare( 'INSERT INTO `activity_log` ( ' . implode( ',', $columns ) . ' ) VALUES ( ' . implode( ',', array_fill( 0, count( $values ), '?' ) ) . ' )' );

        foreach ( $values as $k => $v ) {
            $stmt->bindValue( $k + 1, $v ); //Columns/Parameters are 1-based
        }

        $stmt->execute();

        return $conn->lastInsertId();

    }

    /**
     * This method is not static and used to cache at Redis level the values for this Job
     *
     * Use when counters of the job value are not important but only the metadata are needed
     *
     * @param IDaoStruct $activityQuery
     * @param array      $whereKeys
     *
     * @return IDaoStruct[]
     * @throws ReflectionException
     * @see      \AsyncTasks\Workers\ActivityLogWorker
     * @see      ActivityLogStruct
     */
    public function read( IDaoStruct $activityQuery, array $whereKeys = [ 'id_project' => 0 ] ): array {

        $stmt = $this->_getStatementForQuery(
                "SELECT * FROM activity_log " .
                " LEFT JOIN users USING( uid ) " .
                " WHERE " .
                $this->whereConditions . " " .
                $this->epilogueString
        );

        return $this->_fetchObjectMap( $stmt,
                get_class( $activityQuery ),
                $whereKeys
        );

    }

    /**
     * @param array $array_result
     *
     * @return void
     */
    protected function _buildResult( array $array_result ) {
    }

    /**
     * @param $activity_id
     *
     * @return ActivityLogStruct|null
     */
    public static function getByID( $activity_id ) {

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM activity_log WHERE id = ?" );
        $stmt->setFetchMode( PDO::FETCH_CLASS, ActivityLogStruct::class );
        $stmt->execute( [ $activity_id ] );

        return $stmt->fetch();

    }

}