<?php

namespace Model\Projects;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\Jobs\ChunkDao;
use Model\Jobs\ChunkOptionsModel;
use Model\Jobs\JobStruct;
use ReflectionException;

class MetadataDao extends AbstractDao
{
    const string FEATURES_KEY = 'features';
    const string TABLE = 'project_metadata';

    const string WORD_COUNT_TYPE_KEY = 'word_count_type';

    const string WORD_COUNT_RAW = 'raw';
    const string WORD_COUNT_EQUIVALENT = 'equivalent';

    const string SPLIT_EQUIVALENT_WORD_TYPE = 'eq_word_count';
    const string SPLIT_RAW_WORD_TYPE = 'raw_word_count';

    const string MT_QUALITY_VALUE_IN_EDITOR = 'mt_quality_value_in_editor';
    const string MT_EVALUATION = 'mt_evaluation';
    const string MT_QE_WORKFLOW_ENABLED = 'mt_qe_workflow_enabled';
    const string MT_QE_WORKFLOW_PARAMETERS = 'mt_qe_workflow_parameters';
    const string SUBFILTERING_HANDLERS = 'subfiltering_handlers';
    const string ICU_ENABLED = 'icu_enabled';
    const string FROM_API = 'from_api';
    const string PRETRANSLATE_101 = 'pretranslate_101';
    const string XLIFF_PARAMETERS = 'xliff_parameters';
    const string FILTERS_EXTRACTION_PARAMETERS = 'filters_extraction_parameters';

    protected static string $_query_get_metadata = "SELECT * FROM project_metadata WHERE id_project = :id_project ";
    protected static string $_query_get_metadata_by_key = "SELECT * FROM project_metadata WHERE id_project = :id_project AND `key` = :key ";

    /**
     * @param int $id
     * @return MetadataStruct[]
     * @throws ReflectionException
     */
    public function allByProjectId(int $id): array
    {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(self::$_query_get_metadata);

        /**
         * @var $list MetadataStruct[]
         */
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
     * @throws ReflectionException
     */
    public function get(int $id_project, string $key): ?MetadataStruct
    {
        $stmt = $this->_getStatementForQuery(self::$_query_get_metadata_by_key);

        /**
         * @var $result MetadataStruct
         */
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

    /**
     * Clean up the chunks options before the job merging
     *
     * @param $jobs array Associative array with the Jobs
     *
     * @throws ReflectionException
     * @throws NotFoundException
     */
    public function cleanupChunksOptions(array $jobs): void
    {
        foreach ($jobs as $job) {
            $chunk = ChunkDao::getByIdAndPassword($job['id'], $job['password']);

            foreach (ChunkOptionsModel::$valid_keys as $key) {
                $this->delete(
                    $chunk->id_project,
                    self::buildChunkKey($key, $chunk)
                );
            }
        }
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
            $subfiltering = $this->setCacheTTL(86400)->get($id_project, MetadataDao::SUBFILTERING_HANDLERS);

            return json_decode($subfiltering->value ?? '[]'); //null coalescing with an empty array for project backward compatibility, load all handlers by default
        } catch (Exception) {
            return [];
        }
    }
}
