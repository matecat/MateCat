<?php

namespace Model\Jobs;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\IDatabase;
use Model\DataAccess\TransactionalTrait;
use PDOException;
use ReflectionException;

class MetadataDao extends AbstractDao
{

    use TransactionalTrait;

    protected function getTransactionalDatabase(): IDatabase
    {
        return $this->database;
    }

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
     * @param int $ttl
     *
     * @return MetadataStruct|null
     * @throws Exception
     * @throws PDOException
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
     */
    public function set(int $id_job, string $password, string $key, string $value): ?MetadataStruct
    {
        $sql = "INSERT INTO job_metadata " .
            " ( `id_job`, `password`, `key`, `value` ) " .
            " VALUES " .
            " ( :id_job, :password, :key, :value ) " .
            " ON DUPLICATE KEY UPDATE `value` = :value ";

        $this->openTransaction(); // because we have to invalidate the cache after the insert, use the transactional trait
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

        $result = $this->get($id_job, $password, $key);
        $this->commitTransaction(); // commit only if everything went fine

        return $result;
    }

    /**
     * @param int $id_job
     * @param string $password
     * @param array<string, string> $metadata
     *
     * @throws PDOException
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
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        $this->destroyCacheByJobAndPassword($id_job, $password);
        foreach ($metadata as $key => $value) {
            $this->destroyCacheByJobAndPasswordAndKey($id_job, $password, $key);
        }
        $this->commitTransaction();
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
     * Every row of one chunk as a `key => value` map, with the values exactly as stored.
     *
     * getByJobIdAndPassword() cannot serve a copy: it runs every value through
     * JobsMetadataMarshaller::unMarshall(), so a boolean comes back as `true` and a JSON list as an
     * array. Writing that back would store `1` or `Array` in place of the text every reader parses.
     * The statement is the same one, so this shares its cache address and adds no read of its own.
     *
     * The empty password is an address like any other here, and it is not this one: MMT stores the
     * MT context under it and reads it with getByIdJob(), which ignores the password, so that row is
     * already shared by every chunk of the job. Binding the password is what keeps it out of a copy.
     *
     * @return array<string, string>
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function getRawMapByJobIdAndPassword(int $id_job, string $password): array
    {
        $stmt = $this->_getStatementForQuery(self::_query_metadata_by_job_password);

        /** @var MetadataStruct[] $list */
        $list = $this->setCacheTTL(0)->_fetchObjectMap($stmt, MetadataStruct::class, [
            'id_job' => $id_job,
            'password' => $password,
        ]);

        $map = [];
        foreach ($list as $metadata) {
            $map[(string)$metadata->key] = (string)$metadata->value;
        }

        return $map;
    }

    /**
     * Delete every row of one chunk.
     *
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function deleteByJobIdAndPassword(int $id_job, string $password): void
    {
        // The keys are read before the statement runs: the cache is keyed per key, and once the rows
        // are gone there is nothing left to enumerate them from.
        $keys = array_keys($this->getRawMapByJobIdAndPassword($id_job, $password));

        if (empty($keys)) {
            return;
        }

        $sql = "DELETE FROM job_metadata " .
            " WHERE id_job = :id_job AND password = :password ";

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'id_job' => $id_job,
            'password' => $password,
        ]);

        $this->destroyCacheByJobAndPassword($id_job, $password);

        foreach ($keys as $key) {
            $this->destroyCacheByJobId($id_job, $key);
            $this->destroyCacheByJobAndPasswordAndKey($id_job, $password, $key);
        }
    }

    /**
     * Carry every row of a chunk onto the password that replaced it.
     *
     * JobDao::changePassword() renames a job credential in place, and job_metadata is keyed by
     * (id_job, password, key). Without this the rows stay at a password that no longer exists and
     * the job silently loses every setting it had — dialect_strict, mandatory_issues, the character
     * counter, the subfiltering handlers. It mirrors what ChunkReviewDao::updatePassword() does for
     * the phase rows, and like that one it is the rename that owns it, not the caller.
     *
     * A plain UPDATE is safe against the unique key: the password arriving is freshly generated, and
     * a collision would mean the same (id, password) already exists in `jobs`, which the unique key
     * there forbids.
     *
     * @return int the number of rows carried over
     *
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function movePassword(int $id_job, string $old_password, string $new_password): int
    {
        // The empty password is not a chunk credential and nothing renames it: it is the address MMT
        // stores the MT context under, shared by every chunk of the job. A rotation that swept it up
        // would take the context away from the chunks that did not rotate.
        if ($old_password === '' || $old_password === $new_password) {
            return 0;
        }

        $keys = array_keys($this->getRawMapByJobIdAndPassword($id_job, $old_password));

        if (empty($keys)) {
            return 0;
        }

        $sql = "UPDATE job_metadata SET password = :new_password " .
            " WHERE id_job = :id_job AND password = :old_password ";

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'id_job' => $id_job,
            'old_password' => $old_password,
            'new_password' => $new_password,
        ]);

        // Both ends go. The rows answer under the password they left, and under the one they arrived
        // at a lookup made before the rotation may have cached the miss it found there.
        foreach ([$old_password, $new_password] as $password) {
            $this->destroyCacheByJobAndPassword($id_job, $password);

            foreach ($keys as $key) {
                $this->destroyCacheByJobAndPasswordAndKey($id_job, $password, $key);
            }
        }

        // getByIdJob() ignores the password, so its address is the same on both sides of the move.
        foreach ($keys as $key) {
            $this->destroyCacheByJobId($id_job, $key);
        }

        return $stmt->rowCount();
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
