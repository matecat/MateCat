<?php

namespace Model\Jobs;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use Model\DataAccess\IDaoStruct;
use Model\DataAccess\TransactionalTrait;
use ReflectionException;

class MetadataDao extends AbstractDao
{

    use TransactionalTrait;

    const string TABLE = 'job_metadata';

    const string _query_metadata_by_job_id_key = "SELECT * FROM job_metadata WHERE id_job = :id_job AND `key` = :key ";
    const string _query_metadata_by_job_password = "SELECT * FROM job_metadata WHERE id_job = :id_job AND password = :password ";
    const string _query_metadata_by_job_password_key = "SELECT * FROM job_metadata WHERE id_job = :id_job AND password = :password AND `key` = :key ";

    /**
     * @param int $id_job
     * @param string $key
     * @param int $ttl
     *
     * @return IDaoStruct[]|MetadataStruct[]
     * @throws ReflectionException
     */
    public function getByIdJob(int $id_job, string $key, int $ttl = 0): array
    {
        $stmt = $this->_getStatementForQuery(self::_query_metadata_by_job_id_key);

        return $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, MetadataStruct::class, [
            'id_job' => $id_job,
            'key' => $key
        ]);
    }

    /**
     * @throws ReflectionException
     */
    public function destroyCacheByJobId(int $id_job, string $key): bool
    {
        $stmt = $this->_getStatementForQuery(self::_query_metadata_by_job_id_key);

        return $this->_destroyObjectCache($stmt, MetadataStruct::class, ['id_job' => $id_job, 'key' => $key]);
    }

    /**
     * @param int $id_job
     * @param string $password
     * @param int $ttl
     *
     * @return MetadataStruct[]
     * @throws ReflectionException
     */
    public function getByJobIdAndPassword(int $id_job, string $password, int $ttl = 0): array
    {
        $stmt = $this->_getStatementForQuery(self::_query_metadata_by_job_password);

        $list = $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, MetadataStruct::class, [
            'id_job' => $id_job,
            'password' => $password,
        ]) ?? [];

        foreach ($list as $metadata) {
            $metadata->value = JobsMetadataMarshaller::unMarshall($metadata);
        }

        return $list;
    }

    /**
     * @throws ReflectionException
     */
    public function destroyCacheByJobAndPassword(int $id_job, string $password): bool
    {
        $stmt = $this->_getStatementForQuery(self::_query_metadata_by_job_password);

        return $this->_destroyObjectCache($stmt, MetadataStruct::class, ['id_job' => $id_job, 'password' => $password]);
    }

    /**
     * @param int $id_job
     * @param string $password
     * @param string $key
     * @param int $ttl
     *
     * @return MetadataStruct|null
     * @throws ReflectionException
     */
    public function get(int $id_job, string $password, string $key, int $ttl = 0): ?MetadataStruct
    {
        $stmt = $this->_getStatementForQuery(self::_query_metadata_by_job_password_key);

        return $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, MetadataStruct::class, [
            'id_job' => $id_job,
            'password' => $password,
            'key' => $key
        ])[0] ?? null;
    }

    /**
     * @throws ReflectionException
     */
    public function destroyCacheByJobAndPasswordAndKey(int $id_job, string $password, string $key): bool
    {
        $stmt = $this->_getStatementForQuery(self::_query_metadata_by_job_password_key);

        return $this->_destroyObjectCache($stmt, MetadataStruct::class, [
            'id_job' => $id_job,
            'password' => $password,
            'key' => $key
        ]);
    }

    /**
     * @param int $id_job
     * @param string $password
     * @param string $key
     * @param string $value
     *
     * @return ?MetadataStruct
     * @throws ReflectionException
     */
    public function set(int $id_job, string $password, string $key, string $value): ?MetadataStruct
    {
        $sql = "INSERT INTO job_metadata " .
            " ( `id_job`, `password`, `key`, `value` ) " .
            " VALUES " .
            " ( :id_job, :password, :key, :value ) " .
            " ON DUPLICATE KEY UPDATE `value` = :value ";

        $this->openTransaction(); // because we have to invalidate the cache after the insert, use the transactional trait
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'id_job' => $id_job,
            'password' => $password,
            'key' => $key,
            'value' => $value
        ]);

        $this->destroyCacheByJobAndPassword($id_job, $password);
        $this->destroyCacheByJobAndPasswordAndKey($id_job, $password, $key);

        $result = $this->get($id_job, $password, $key);
        $this->commitTransaction(); // commit only if everything went fine

        return $result;
    }

    /**
     * @param int $id_job
     * @param string $password
     * @param array<string, string> $metadata
     *
     * @throws ReflectionException
     */
    public function bulkSet(int $id_job, string $password, array $metadata): void
    {
        if (empty($metadata)) {
            return;
        }

        $placeholders = [];
        $params = [];
        $i = 0;

        foreach ($metadata as $key => $value) {
            $placeholders[] = "(:id_job_$i, :password_$i, :key_$i, :value_$i)";
            $params["id_job_$i"] = $id_job;
            $params["password_$i"] = $password;
            $params["key_$i"] = $key;
            $params["value_$i"] = $value;
            $i++;
        }

        $sql = "INSERT INTO job_metadata (`id_job`, `password`, `key`, `value`) VALUES "
            . implode(', ', $placeholders)
            . " ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)";

        $this->openTransaction();
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        $this->destroyCacheByJobAndPassword($id_job, $password);
        foreach ($metadata as $key => $value) {
            $this->destroyCacheByJobAndPasswordAndKey($id_job, $password, $key);
        }
        $this->commitTransaction();
    }

    /**
     * @throws ReflectionException
     */
    public function delete($id_job, $password, $key): void
    {
        $sql = "DELETE FROM job_metadata " .
            " WHERE id_job = :id_job AND password = :password " .
            " AND `key` = :key ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'id_job' => $id_job,
            'password' => $password,
            'key' => $key,
        ]);

        $this->destroyCacheByJobAndPassword($id_job, $password);
        $this->destroyCacheByJobAndPasswordAndKey($id_job, $password, $key);
    }

    protected function _buildResult(array $array_result)
    {
    }

    /**
     * @param int $id_job
     * @param string $password
     *
     * @return ?array empty array if the subfiltering_handlers metadata is not set,
     *                  null when all handlers are disabled
     */
    public function getSubfilteringCustomHandlers(int $id_job, string $password): ?array
    {
        try {
            $subfiltering = $this->get($id_job, $password, JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value, 86400);

            return json_decode($subfiltering?->value ?? '[]'); //null coalescing with an empty array for project backward compatibility, load all handlers by default
        } catch (Exception) {
            return [];
        }
    }

}