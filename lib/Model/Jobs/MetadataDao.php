<?php

namespace Model\Jobs;

use Exception;
use Model\DataAccess\AbstractDao;
use PDOException;
use ReflectionException;
use Throwable;

class MetadataDao extends AbstractDao
{

    const string TABLE = 'job_metadata';

    const string _query_metadata_by_job_id_key = "SELECT * FROM job_metadata WHERE id_job = :id_job AND `key` = :key ";
    const string _query_metadata_by_job_password = "SELECT * FROM job_metadata WHERE id_job = :id_job AND password = :password ";
    const string _query_metadata_by_job_password_key = "SELECT * FROM job_metadata WHERE id_job = :id_job AND password = :password AND `key` = :key ";

    /**
     * @param int $id_job
     * @param string $key
     * @param int $ttl
     *
     * @return MetadataStruct[]
     * @throws Exception
     * @throws PDOException
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
     * @throws PDOException
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
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function getByJobIdAndPassword(int $id_job, string $password, int $ttl = 0): array
    {
        $stmt = $this->_getStatementForQuery(self::_query_metadata_by_job_password);

        $list = $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, MetadataStruct::class, [
            'id_job' => $id_job,
            'password' => $password,
        ]);

        foreach ($list as $metadata) {
            $metadata->value = JobsMetadataMarshaller::unMarshall($metadata);
        }

        return $list;
    }

    /**
     * @throws PDOException
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
     * @param int $ttl Zero means "do not read from cache", and set() depends on that default: it
     *                 re-reads the row it has just written, inside the transaction where the
     *                 eviction it issued is still queued for the commit. Give this parameter a
     *                 non-zero default and set() starts returning the pre-write value.
     *
     * @return MetadataStruct|null
     * @throws Exception
     * @throws PDOException
     *
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
     * @throws PDOException
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
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     * @throws Throwable the write runs inside a transaction scope, which aborts the transaction on
     *                   any throw and re-throws the original, whatever its type
     */
    public function set(int $id_job, string $password, string $key, string $value): ?MetadataStruct
    {
        $sql = "INSERT INTO job_metadata " .
            " ( `id_job`, `password`, `key`, `value` ) " .
            " VALUES " .
            " ( :id_job, :password, :key, :value ) " .
            " ON DUPLICATE KEY UPDATE `value` = :value ";

        // The scope exists so the evictions below are queued and run after the commit rather than
        // before it. It also undoes the write here rather than leaving it to the end of the request:
        // a worker holds its connection across messages, so a transaction left open by a failure
        // here would still be open when the next message starts writing.
        return $this->database->transaction(function () use ($id_job, $password, $key, $value, $sql): ?MetadataStruct {
            $conn = $this->database->getConnection();
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                'id_job' => $id_job,
                'password' => $password,
                'key' => $key,
                'value' => $value
            ]);

            $this->destroyCacheByJobAndPassword($id_job, $password);
            $this->destroyCacheByJobAndPasswordAndKey($id_job, $password, $key);

            return $this->get($id_job, $password, $key);
        });
    }

    /**
     * @param int $id_job
     * @param string $password
     * @param array<string, string> $metadata
     *
     * @throws PDOException
     * @throws ReflectionException
     * @throws Throwable the write runs inside a transaction scope, which aborts the transaction on
     *                   any throw and re-throws the original, whatever its type
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

        $this->database->transaction(function () use ($id_job, $password, $metadata, $params, $sql): void {
            $conn = $this->database->getConnection();
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

            $this->destroyCacheByJobAndPassword($id_job, $password);
            foreach ($metadata as $key => $value) {
                $this->destroyCacheByJobAndPasswordAndKey($id_job, $password, $key);
            }
        });
    }

    /**
     * @param int $id_job
     * @param string $password
     * @param string $key
     * @throws PDOException
     * @throws ReflectionException
     */
    public function delete(int $id_job, string $password, string $key): void
    {
        $sql = "DELETE FROM job_metadata " .
            " WHERE id_job = :id_job AND password = :password " .
            " AND `key` = :key ";

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'id_job' => $id_job,
            'password' => $password,
            'key' => $key,
        ]);

        $this->destroyCacheByJobAndPassword($id_job, $password);
        $this->destroyCacheByJobAndPasswordAndKey($id_job, $password, $key);
    }

    /**
     * @param int $id_job
     * @param string $password
     *
     * @return array<int|string, mixed>|null empty array if the subfiltering_handlers metadata is not set,
     *                  null when all handlers are disabled
     */
    public function getSubfilteringCustomHandlers(int $id_job, string $password): ?array
    {
        try {
            $subfiltering = $this->get($id_job, $password, JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value, 86400);

            return json_decode($subfiltering->value ?? '[]'); //null coalescing with an empty array for project backward compatibility, load all handlers by default
        } catch (Exception) {
            return [];
        }
    }

}
