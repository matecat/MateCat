<?php

class Jobs_JobDao extends DataAccess_AbstractDao {

    const TABLE       = "jobs";
    const STRUCT_TYPE = "Jobs_JobStruct";

    protected static $auto_increment_fields = [ 'id' ];
    protected static $primary_keys          = [ 'id', 'password' ];

    protected static $_sql_update_password = "UPDATE jobs SET password = :new_password WHERE id = :id AND password = :old_password ";

    /**
     * This method is not static and used to cache at Redis level the values for this Job
     *
     * Use when counters of the job value are not important but only the metadata are needed
     *
     * XXX: Be careful, used by the ContributionStruct
     *
     * @see \AsyncTasks\Workers\SetContributionWorker
     * @see \Contribution\ContributionStruct
     *
     * @param Jobs_JobStruct $jobQuery
     *
     * @return DataAccess_IDaoStruct[]|Jobs_JobStruct[]
     */
    public function read( \Jobs_JobStruct $jobQuery ){

        $stmt = $this->_getStatementForCache();
        return $this->_fetchObject( $stmt,
                $jobQuery,
                array(
                        'id_job' => $jobQuery->id,
                        'password' => $jobQuery->password
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
                "SELECT * FROM jobs WHERE " .
                " id = :id_job AND password = :password "
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
     * @param Jobs_JobStruct $jobQuery
     *
     * @return bool
     * @throws Exception
     */
    public function destroyCache( \Jobs_JobStruct $jobQuery ){
        /*
        * build the query
        */
        $stmt = $this->_getStatementForCache();
        return $this->_destroyObjectCache( $stmt,
                array(
                        'id_job' => $jobQuery->id,
                        'password' => $jobQuery->password
                )
        );
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

    public static function getByProjectId( $id_project, $ttl = 0 ) {

        $thisDao = new self();
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM jobs WHERE id_project = ? ORDER BY id, job_first_segment ASC;" );

        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new Jobs_JobStruct(), [ $id_project ] );

    }

    /**
     *
     * @param int $id_job
     *
     * @param int $ttl
     *
     * @return DataAccess_IDaoStruct[]|Jobs_JobStruct[]
     */
    public static function getById( $id_job, $ttl = 0 ) {

        $thisDao = new self();
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ? ORDER BY job_first_segment");
        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new Jobs_JobStruct(), [ $id_job ] );

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

        //clean null values
        foreach( $values as $k => $val ){
            if( is_null( $val ) ){
                unset( $values[ $k ] );
                unset( $columns[ $k ] );
            }
        }

        //reindex the array
        $columns = array_values( $columns );
        $values  = array_values( $values );

        \Database::obtain()->begin();

        $stmt = $conn->prepare( 'INSERT INTO `jobs` ( ' . implode( ',', $columns ) . ' ) VALUES ( ' . implode( ',' , array_fill( 0, count( $values ), '?' ) ) . ' )' );

        foreach( $values as $k => $v ){
            $stmt->bindValue( $k +1, $v ); //Columns/Parameters are 1-based
        }

        $stmt->execute();

        $job = static::getById( $conn->lastInsertId() )[0];

        $conn->commit();

        return $job;

    }

    /**
     * @param Projects_ProjectStruct $project
     * @param Users_UserStruct $user
     * @return int the number of rows affected by the statement
     */
    public function updateOwner( Projects_ProjectStruct $project, Users_UserStruct $user ) {
        $sql = " UPDATE jobs SET owner = :email WHERE id_project = :id_project ";

        $stmt = $this->con->getConnection()->prepare( $sql ) ;
        $stmt->execute(array('email' => $user->email, 'id_project' => $project->id ) ) ;

        return $stmt->rowCount();
    }

    public function changePassword( Jobs_JobStruct $jStruct, $new_password ){

        if( empty( $new_password ) ) throw new PDOException( "Invalid empty value: password." );

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( self::$_sql_update_password );
        $stmt->execute( [
                'id'           => $jStruct->id,
                'new_password' => $new_password,
                'old_password' => $jStruct->password
        ] );

        $jStruct->password = $new_password;

        return $jStruct;

    }

}
