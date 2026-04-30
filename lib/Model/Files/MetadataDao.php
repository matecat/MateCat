<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 09/09/2020
 * Time: 19:34
 */

namespace Model\Files;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use PDOException;
use ReflectionException;

class MetadataDao extends AbstractDao
{

    const string  TABLE = 'file_metadata';
    const string  _query_metadata_by_project_id_file = "SELECT * FROM " . self::TABLE . " WHERE id_project = :id_project AND id_file = :id_file ";
    const string  _query_get_by_key                  = "SELECT * FROM " . self::TABLE . " WHERE id_project = :id_project AND id_file = :id_file AND `key` = :key ";
    const string  _query_get_by_key_and_parts        = "SELECT * FROM " . self::TABLE . " WHERE id_project = :id_project AND id_file = :id_file AND `key` = :key AND `files_parts_id` = :files_parts_id ";

    /**
     * @param int $id_project
     * @param int $id_file
     * @param int $ttl
     *
     * @return MetadataStruct[]|null
     * @throws ReflectionException
     * @throws Exception
     */
    public function getByJobIdProjectAndIdFile(int $id_project, int $id_file, int $ttl = 0): ?array
    {
        $stmt = $this->_getStatementForQuery(self::_query_metadata_by_project_id_file);

        $list = $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, MetadataStruct::class, [
            'id_project' => $id_project,
            'id_file' => $id_file,
        ]);

        if ($list) {
            foreach ($list as $metaStruct) {
                $metaStruct->value = FilesMetadataMarshaller::unMarshall($metaStruct);
            }
        }

        return $list;
    }

    /**
     * @throws ReflectionException
     */
    public function destroyCacheByJobIdProjectAndIdFile(int $id_project, int $id_file): bool
    {
        $stmt = $this->_getStatementForQuery(self::_query_metadata_by_project_id_file);

        return $this->_destroyObjectCache($stmt, MetadataStruct::class, ['id_project' => $id_project, 'id_file' => $id_file,]);
    }

    /**
     * Destroy cached result for a specific get() call.
     *
     * Must reconstruct the same query and params used in get() so that
     * _destroyObjectCache computes the matching cache key.
     *
     * @throws ReflectionException
     */
    public function destroyGetCache(int $id_project, int $id_file, string $key, ?int $filePartsId = null): bool
    {
        $params = [
            'id_project' => $id_project,
            'id_file'    => $id_file,
            'key'        => $key,
        ];

        if ($filePartsId) {
            $query = self::_query_get_by_key_and_parts;
            $params['files_parts_id'] = $filePartsId;
        } else {
            $query = self::_query_get_by_key;
        }

        $stmt = $this->_getStatementForQuery($query);

        return $this->_destroyObjectCache($stmt, MetadataStruct::class, $params);
    }

    /**
     * @param int $id_project
     * @param int $id_file
     * @param string $key
     * @param int|null $filePartsId
     * @param int $ttl
     *
     * @return MetadataStruct|null
     * @throws ReflectionException
     * @throws Exception
     */
    public function get(int $id_project, int $id_file, string $key, ?int $filePartsId = null, int $ttl = 0): ?MetadataStruct
    {
        $params = [
            'id_project' => $id_project,
            'id_file' => $id_file,
            'key' => $key
        ];

        if ($filePartsId) {
            $query = self::_query_get_by_key_and_parts;
            $params['files_parts_id'] = $filePartsId;
        } else {
            $query = self::_query_get_by_key;
        }

        $stmt = $this->_getStatementForQuery($query);

        $result = $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, MetadataStruct::class, $params)[0] ?? null;

        if ($result) {
            $result->value = FilesMetadataMarshaller::unMarshall($result);
        }

        return $result;
    }

    /**
     * @param int $id_project
     * @param int $id_file
     * @param string $key
     * @param string $value
     * @param int|null $filePartsId
     *
     * @return MetadataStruct|null
     * @throws ReflectionException
     * @throws Exception
     */
    public function insert(int $id_project, int $id_file, string $key, string $value, ?int $filePartsId = null): ?MetadataStruct
    {
        $sql = "INSERT INTO file_metadata " .
            " ( id_project, id_file, `key`, `value`, `files_parts_id` ) " .
            " VALUES " .
            " ( :id_project, :id_file, :key, :value, :files_parts_id ); ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'id_project' => $id_project,
            'id_file' => $id_file,
            'files_parts_id' => $filePartsId,
            'key' => $key,
            'value' => $value
        ]);

        $this->destroyCacheByJobIdProjectAndIdFile($id_project, $id_file);
        $this->destroyGetCache($id_project, $id_file, $key, $filePartsId);

        return $this->get($id_project, $id_file, $key, $filePartsId);
    }

    /**
     * @param int $id_project
     * @param int $id_file
     * @param string $key
     * @param string $value
     * @param int|null $filePartsId
     *
     * @return MetadataStruct|null
     * @throws ReflectionException
     * @throws Exception
     */
    public function update(int $id_project, int $id_file, string $key, string $value, ?int $filePartsId = null): ?MetadataStruct
    {
        $sql = "UPDATE file_metadata SET `value` = :value WHERE id_project = :id_project AND id_file = :id_file AND `key` = :key  ";

        $args = [
            'id_project' => $id_project,
            'id_file' => $id_file,
            'key' => $key,
            'value' => $value
        ];

        if (!empty($filePartsId)) {
            $sql .= "AND `files_parts_id` = :files_parts_id";
            $args['files_parts_id'] = $filePartsId;
        }

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);


        $stmt->execute($args);

        $this->destroyCacheByJobIdProjectAndIdFile($id_project, $id_file);
        $this->destroyGetCache($id_project, $id_file, $key, $filePartsId);

        return $this->get($id_project, $id_file, $key, $filePartsId);
    }

    /**
     * @param int $id_project
     * @param int $id_file
     * @param array<string, string|null> $metadata
     * @param int|null $filePartsId
     *
     * @return bool|null
     * @throws ReflectionException
     * @throws PDOException
     */
    public function bulkInsert(int $id_project, int $id_file, array $metadata = [], ?int $filePartsId = null): bool|null
    {
        $sql = "INSERT INTO file_metadata ( id_project, id_file, `key`, `value`, `files_parts_id` ) VALUES ";
        $bind_values = [];

        $index = 1;
        foreach ($metadata as $key => $value) {
            $isLast = ($index === count($metadata));

            if ($value !== null and $value !== '') {
                $sql .= "(?,?,?,?,?)";

                if (!$isLast) {
                    $sql .= ',';
                }

                $bind_values[] = $id_project;
                $bind_values[] = $id_file;
                $bind_values[] = $key;
                $bind_values[] = $value;
                $bind_values[] = $filePartsId;
            }
            $index++;
        }

        if (!empty($bind_values)) {
            $conn = Database::obtain()->getConnection();
            $stmt = $conn->prepare($sql);

            $result = $stmt->execute($bind_values);

            $this->destroyCacheByJobIdProjectAndIdFile($id_project, $id_file);
            foreach ($metadata as $key => $value) {
                if ($value !== null and $value !== '') {
                    $this->destroyGetCache($id_project, $id_file, $key, $filePartsId);
                }
            }

            return $result;
        }

        return null;
    }
}
