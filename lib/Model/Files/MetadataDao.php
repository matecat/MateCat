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
use PDOException;
use ReflectionException;
use TypeError;

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
     * The second, coarser door: every metadata row of a file, whatever its keys. FilesInfoUtility
     * clears a whole file after a write it does not describe key by key, and no single struct says
     * that, so this stays public alongside destroyCache().
     *
     * @throws PDOException
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
     * @throws PDOException
     * @throws ReflectionException
     */
    private function destroyCacheByProjectFileAndKey(int $id_project, int $id_file, string $key, ?int $filePartsId = null): bool
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
     * The one eviction a caller needs. A row here answers three reads, and two of them differ only in
     * whether files_parts_id is bound: the same row, at two addresses. A write reaches whichever side
     * it named — the UPDATE has no files_parts_id clause — so the part ids are read back from the table
     * rather than taken from the caller, which never has them on both paths.
     *
     * @throws PDOException
     * @throws ReflectionException
     * @throws TypeError when the struct names less than a whole address
     */
    public function destroyCache(MetadataStruct $metadata): void
    {
        if (!isset($metadata->id_project, $metadata->id_file, $metadata->key)) {
            throw new TypeError('MetadataStruct must carry id_project, id_file and key');
        }

        $this->destroyCacheByJobIdProjectAndIdFile($metadata->id_project, $metadata->id_file);
        $this->destroyCacheByProjectFileAndKey($metadata->id_project, $metadata->id_file, $metadata->key);

        $partIds = $this->filePartIdsHolding($metadata->id_project, $metadata->id_file, $metadata->key);
        if ($metadata->files_parts_id !== null) {
            $partIds[] = $metadata->files_parts_id;
        }

        foreach (array_unique($partIds) as $filePartsId) {
            $this->destroyCacheByProjectFileAndKey(
                $metadata->id_project,
                $metadata->id_file,
                $metadata->key,
                $filePartsId
            );
        }
    }

    /**
     * The part ids under which this key is stored right now. A row removed before the door runs is not
     * in here, which is why the caller's own id is added on top.
     *
     * @return list<int>
     * @throws PDOException
     */
    private function filePartIdsHolding(int $id_project, int $id_file, string $key): array
    {
        $stmt = $this->database->getConnection()->prepare(
            "SELECT DISTINCT files_parts_id FROM file_metadata " .
            " WHERE id_project = :id_project AND id_file = :id_file AND `key` = :key " .
            " AND files_parts_id IS NOT NULL "
        );
        $stmt->execute(['id_project' => $id_project, 'id_file' => $id_file, 'key' => $key]);

        return array_values(array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN)));
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
     * @throws TypeError
     */
    public function insert(int $id_project, int $id_file, string $key, string $value, ?int $filePartsId = null): ?MetadataStruct
    {
        $sql = "INSERT INTO file_metadata " .
            " ( id_project, id_file, `key`, `value`, `files_parts_id` ) " .
            " VALUES " .
            " ( :id_project, :id_file, :key, :value, :files_parts_id ); ";

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'id_project' => $id_project,
            'id_file' => $id_file,
            'files_parts_id' => $filePartsId,
            'key' => $key,
            'value' => $value
        ]);

        $this->destroyCache(new MetadataStruct([
            'id_project'     => $id_project,
            'id_file'        => $id_file,
            'key'            => $key,
            'files_parts_id' => $filePartsId,
        ]));

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
     * @throws TypeError
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

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);


        $stmt->execute($args);

        $this->destroyCache(new MetadataStruct([
            'id_project'     => $id_project,
            'id_file'        => $id_file,
            'key'            => $key,
            'files_parts_id' => $filePartsId,
        ]));

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
     * @throws TypeError
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
            $conn = $this->database->getConnection();
            $stmt = $conn->prepare($sql);

            $result = $stmt->execute($bind_values);

            foreach ($metadata as $key => $value) {
                if ($value !== null and $value !== '') {
                    $this->destroyCache(new MetadataStruct([
                        'id_project'     => $id_project,
                        'id_file'        => $id_file,
                        'key'            => $key,
                        'files_parts_id' => $filePartsId,
                    ]));
                }
            }

            return $result;
        }

        return null;
    }
}
