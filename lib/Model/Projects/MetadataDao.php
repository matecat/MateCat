<?php

namespace Model\Projects;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use Model\Jobs\JobStruct;
use PDOException;
use ReflectionException;

class MetadataDao extends AbstractDao
{
    const string TABLE = 'project_metadata';

    protected static string $_query_get_metadata = "SELECT * FROM project_metadata WHERE id_project = :id_project ";
    protected static string $_query_get_metadata_by_key = "SELECT * FROM project_metadata WHERE id_project = :id_project AND `key` = :key ";

    /**
     * @param int $id
     * @return MetadataStruct[]
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function allByProjectId(int $id): array
    {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(self::$_query_get_metadata);

        /** @var MetadataStruct[] $list */
        $list = $this->_fetchObjectMap($stmt, MetadataStruct::class, ['id_project' => $id]);
        foreach ($list as $metaStruct) {
            $metaStruct->value = ProjectsMetadataMarshaller::unMarshall($metaStruct);
        }

        return $list;
    }


    /**
     * @param int $project_id The ID of the project for which the metadata cache will be destroyed.
     * @param string|null $metadataKey An optional metadata key to target specific metadata within the project's cache. If null, the entire project's metadata cache will be destroyed.
     *
     * @return bool Returns true if the metadata cache was successfully destroyed, otherwise false.
     * @throws PDOException
     * @throws ReflectionException
     */
    public function destroyMetadataCache(int $project_id, ?string $metadataKey = null): bool
    {
        $query = self::$_query_get_metadata;
        $bindParams = [
            'id_project' => $project_id
        ];

        if ($metadataKey !== null) {
            $query = self::$_query_get_metadata_by_key;
            $bindParams['key'] = $metadataKey;
        }

        $stmt = $this->_getStatementForQuery($query);

        return $this->_destroyObjectCache($stmt, MetadataStruct::class, $bindParams);
    }

    /**
     * @param int $id_project
     * @param string $key
     * @return MetadataStruct|null
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function get(int $id_project, string $key): ?MetadataStruct
    {
        $stmt = $this->_getStatementForQuery(self::$_query_get_metadata_by_key);

        /** @var MetadataStruct|null $result */
        $result = $this->_fetchObjectMap($stmt, MetadataStruct::class, [
            'id_project' => $id_project,
            'key' => $key
        ])[0] ?? null;

        if ($result) {
            $result->value = ProjectsMetadataMarshaller::unMarshall($result);
        }

        return $result;
    }

    /**
     * @param int $id_project
     * @param string $key
     * @param string $value
     *
     * @return boolean
     * @throws PDOException
     * @throws ReflectionException
     */
    public function set(int $id_project, string $key, string $value): bool
    {
        $sql = "INSERT INTO project_metadata " .
            " ( id_project, `key`, value ) " .
            " VALUES " .
            " ( :id_project, :key, :value ) " .
            " ON DUPLICATE KEY UPDATE value = :value ";
        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'id_project' => $id_project,
            'key' => $key,
            'value' => $value
        ]);

        $this->destroyMetadataCache($id_project);
        $this->destroyMetadataCache($id_project, $key);

        return $conn->lastInsertId();
    }

    /**
     * Bulk insert/update multiple metadata key-value pairs in a single query.
     *
     * @param int $id_project
     * @param array<string, string> $metadata key => value pairs to upsert
     *
     * @throws PDOException
     * @throws ReflectionException
     */
    public function bulkSet(int $id_project, array $metadata): void
    {
        if (empty($metadata)) {
            return;
        }

        $placeholders = [];
        $params = [];
        $i = 0;

        foreach ($metadata as $key => $value) {
            $placeholders[] = "(:id_project_{$i}, :key_$i, :value_$i)";
            $params["id_project_$i"] = $id_project;
            $params["key_$i"] = $key;
            $params["value_$i"] = $value;
            $i++;
        }

        $sql = "INSERT INTO project_metadata (id_project, `key`, value) VALUES "
            . implode(', ', $placeholders)
            . " ON DUPLICATE KEY UPDATE value = VALUES(value)";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        $this->destroyMetadataCache($id_project);
        foreach ($metadata as $key => $value) {
            $this->destroyMetadataCache($id_project, $key);
        }
    }


    /**
     * @throws PDOException
     * @throws ReflectionException
     */
    public function delete(int $id_project, string $key): void
    {
        $sql = "DELETE FROM project_metadata " .
            " WHERE id_project = :id_project " .
            " AND `key` = :key ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'id_project' => $id_project,
            'key' => $key,
        ]);

        $this->destroyMetadataCache($id_project);
        $this->destroyMetadataCache($id_project, $key);
    }

    public static function buildChunkKey(string $key, JobStruct $chunk): string
    {
        return "{$key}_chunk_{$chunk->id}_$chunk->password";
    }

    protected function _buildResult(array $array_result)
    {
    }

    /**
     * @param int $id_project
     *
     * @return array|null
     */
    public function getProjectStaticSubfilteringCustomHandlers(int $id_project): ?array
    {
        try {
            $subfiltering = $this->setCacheTTL(86400)->get($id_project, ProjectsMetadataMarshaller::SUBFILTERING_HANDLERS->value);

            return $subfiltering?->value ?? []; //null coalescing with an empty array for project backward compatibility, load all handlers by default
        } catch (Exception) {
            return [];
        }
    }
}
