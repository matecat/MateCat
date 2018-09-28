<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 10/04/17
 * Time: 20.01
 *
 */

namespace Translators;


use Jobs_JobStruct;

class JobsTranslatorsDao extends \DataAccess_AbstractDao {

    const TABLE       = "jobs_translators";
    const STRUCT_TYPE = "JobsTranslatorsStruct";

    protected static $auto_increment_field = array();
    protected static $primary_keys         = array( 'id_job', 'job_password' );

    protected static $_query_all_by_id          = "SELECT * FROM jobs_translators WHERE id_job = :id_job ;";
    protected static $_query_by_id_and_password = "SELECT * FROM jobs_translators WHERE id_job = :id_job and job_password = :password ;";

    public function findByJobIdAndPassword( $id_job, $password ){

        $jobStruct = new Jobs_JobStruct();
        $jobStruct->id = $id_job;
        $jobStruct->password = $password;
        return $this->findByJobsStruct( $jobStruct );

    }

    public function findByJobId( $id_job ){

        $jobStruct = new Jobs_JobStruct();
        $jobStruct->id = $id_job;
        return $this->findByJobsStruct( $jobStruct );

    }

    /**
     * @param Jobs_JobStruct $jobStruct
     *
     * @return \DataAccess_IDaoStruct[]|JobsTranslatorsStruct[]
     */
    public function findByJobsStruct( Jobs_JobStruct $jobStruct ){

        if( !empty( $jobStruct->password ) ){
            $query = self::$_query_by_id_and_password;
            $data = [ 'id_job' => $jobStruct->id, 'password' => $jobStruct->password ];
        } else {
            $query = self::$_query_all_by_id;
            $data = [ 'id_job' => $jobStruct->id ];
        }

        $stmt                     = $this->_getStatementForCache( $query );
        $jobsTranslatorsQuery     = new JobsTranslatorsStruct();

        return $this->_fetchObject( $stmt,
                $jobsTranslatorsQuery,
                $data
        );

    }

    public function destroyCacheByJobStruct( Jobs_JobStruct $jobStruct ){

        if( !empty( $jobStruct->password ) ){
            $query = self::$_query_by_id_and_password;
            $data = [ 'id_job' => $jobStruct->id, 'password' => $jobStruct->password ];
        } else {
            $query = self::$_query_all_by_id;
            $data = [ 'id_job' => $jobStruct->id ];
        }

        $stmt = $this->_getStatementForCache( $query );
        return $this->_destroyObjectCache( $stmt, $data );

    }

}