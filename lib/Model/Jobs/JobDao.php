<?php

class Jobs_JobDao extends DataAccess_AbstractDao {

    /**
     * This method is not static and used to cache at Redis level the values for this Job
     *
     * Use when counters of the job value are not important but only the metadata are needed
     *
     * @param Jobs_JobStruct $job
     *
     * @return Jobs_JobStruct[]
     */
    public function read( \Jobs_JobStruct $job ){

        $sql = $this->_buildQueryForJobStructCache( $job );
        $arr_result = $this->_fetch_array( $sql );
        return $this->_buildResult( $arr_result );

    }

    /**
     * @param array $array_result
     *
     * @return array|Jobs_JobStruct|Jobs_JobStruct[]
     */
    protected function _buildResult( $array_result ) {
        $result = array();
        foreach ( $array_result as $item ) {
            $obj = new Jobs_JobStruct( $item );
            $result[ ] = $obj;
        }
        return $result;
    }

    /**
     * @param Jobs_JobStruct $job
     *
     * @return string
     */
    protected function _buildQueryForJobStructCache( \Jobs_JobStruct $job ) {
        /*
         * build the query
         */
        $sql = "SELECT * FROM jobs WHERE ID = " . (int)$job->id;
        if ( !empty( $job->password ) ) {
            $sql .= " AND password = '" . Database::obtain()->escape( $job->password ) . "'";
        }
        return $sql;
    }

    /**
     * Destroy a cached object
     *
     * @param Jobs_JobStruct $job
     *
     * @return bool
     * @throws Exception
     */
    public function destroyCache( \Jobs_JobStruct $job ){
        /*
        * build the query
        */
        $sql = $this->_buildQueryForJobStructCache( $job );
        return $this->_destroyCache( $sql );
    }

    /**
     * @param $id_job
     * @param $password
     *
     * @return Jobs_JobStruct
     */
    public static function getByIdAndPassword( $id_job, $password ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
                "SELECT * FROM jobs WHERE " .
                " id = :id_job AND password = :password "
        );
        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Jobs_JobStruct' );
        $stmt->execute( array( 'id_job' => $id_job, 'password' => $password ) );

        return $stmt->fetch();
    }

    public static function getByProjectId( $id_project ) {
        $conn = Database::obtain()->getConnection();
        // TODO: this query should return the minimal data
        // required for the JobStruct class, to not be confused
        // with the ChunkStruct class.
        // Ideally it should return:
        // - id_project
        // - id
        // - source
        // - target
        //
        // about job_first_segment and job_last_segment it should return
        // the whole segments interval.
        // Extend this query considering that fields not defined in the
        // JobStruct class will be ignored.
        $stmt = $conn->prepare(
                "SELECT * FROM ( " .
                " SELECT * FROM jobs " .
                " WHERE id_project = ? " .
                " ORDER BY id DESC ) t GROUP BY id ; " );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Jobs_JobStruct' );
        $stmt->execute( array( $id_project ) );

        return $stmt->fetchAll();
    }

    /**
     * @param $id_job
     *
     * @return Jobs_JobStruct
     */
    public static function getById( $id_job ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ?");
        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Jobs_JobStruct' );
        $stmt->execute( array( $id_job ) );

        return $stmt->fetch();
    }

    /**
     * For now this is used by tests
     *
     * TODO Upgrade Project manager class with this method
     *
     * @param Jobs_JobStruct $jobStruct
     *
     * @return Jobs_JobStruct
     */
    public static function createFromStruct( Jobs_JobStruct $jobStruct ){

        $conn = Database::obtain()->getConnection();

        $jobStructToArray = $jobStruct->toArray();
        $columns = array_keys( $jobStructToArray );
        $values = array_values( $jobStructToArray );

        $stmt = $conn->prepare( 'INSERT INTO `jobs` ( ' . implode( ',', $columns ) . ' ) VALUES ( ' . implode( ',' , array_fill( 0, count( $values ), '?' ) ) . ' )' );

        foreach( $values as $k => $v ){
            $stmt->bindValue( $k +1, $v ); //Columns/Parameters are 1-based
        }

        $stmt->execute();

        return static::getById( $conn->lastInsertId() );

    }

}
