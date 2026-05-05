<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 10/04/17
 * Time: 20.01
 *
 */

namespace Model\Translators;


use Model\DataAccess\AbstractDao;
use Model\Jobs\JobStruct;
use ReflectionException;

class JobsTranslatorsDao extends AbstractDao
{

    const string TABLE = "jobs_translators";
    const string STRUCT_TYPE = JobsTranslatorsStruct::class;

    protected static array $auto_increment_field = [];
    protected static array $primary_keys = ['id_job', 'job_password'];

    protected static string $_query_all_by_id = "SELECT * FROM jobs_translators WHERE id_job = :id_job ;";
    protected static string $_query_by_id_and_password = "SELECT * FROM jobs_translators WHERE id_job = :id_job and job_password = :password ;";

    /**
     * @param JobStruct $jobStruct
     *
     * @return JobsTranslatorsStruct[]
     * @throws ReflectionException
     */
    public function findByJobsStruct(JobStruct $jobStruct): ?array
    {
        if (!empty($jobStruct->password)) {
            $query = self::$_query_by_id_and_password;
            $data = ['id_job' => $jobStruct->id, 'password' => $jobStruct->password];
        } else {
            $query = self::$_query_all_by_id;
            $data = ['id_job' => $jobStruct->id];
        }

        $stmt = $this->_getStatementForQuery($query);

        return $this->_fetchObjectMap(
            $stmt,
            self::STRUCT_TYPE,
            $data
        );
    }

    /**
     * @throws ReflectionException
     */
    public function destroyCacheByJobStruct(JobStruct $jobStruct): bool
    {
        if (!empty($jobStruct->password)) {
            $query = self::$_query_by_id_and_password;
            $data = ['id_job' => $jobStruct->id, 'password' => $jobStruct->password];
        } else {
            $query = self::$_query_all_by_id;
            $data = ['id_job' => $jobStruct->id];
        }

        $stmt = $this->_getStatementForQuery($query);

        return $this->_destroyObjectCache($stmt, JobsTranslatorsStruct::class, $data);
    }

}