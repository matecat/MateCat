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

class MetadataDao extends AbstractDao {
    const FEATURES_KEY = 'features';
    const TABLE        = 'project_metadata';

    const WORD_COUNT_TYPE_KEY = 'word_count_type';

    const WORD_COUNT_RAW        = 'raw';
    const WORD_COUNT_EQUIVALENT = 'equivalent';

    const SPLIT_EQUIVALENT_WORD_TYPE = 'eq_word_count';
    const SPLIT_RAW_WORD_TYPE        = 'raw_word_count';

    const MT_QUALITY_VALUE_IN_EDITOR = 'mt_quality_value_in_editor';
    const MT_EVALUATION              = 'mt_evaluation';
    const MT_QE_WORKFLOW_ENABLED     = 'mt_qe_workflow_enabled';
    const MT_QE_WORKFLOW_PARAMETERS  = 'mt_qe_workflow_parameters';

    protected static string $_query_get_metadata = "SELECT * FROM project_metadata WHERE id_project = :id_project ";

    /**
     * @param $id
     *
     * @return MetadataStruct[]
     * @throws ReflectionException
     */
    public static function getByProjectId( $id ): array {
        $dao = new MetadataDao();

        return $dao->setCacheTTL( 60 * 60 )->allByProjectId( $id );
    }

    /**
     * @param $id
     *
     * @return MetadataStruct[]
     * @throws ReflectionException
     */
    public function allByProjectId( $id ): array {

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( self::$_query_get_metadata );

        /**
         * @var MetadataStruct[]
         */
        return $this->_fetchObjectMap( $stmt, MetadataStruct::class, [ 'id_project' => $id ] );

    }

    /**
     * @throws ReflectionException
     */
    public function destroyMetadataCache( $id ): bool {
        $stmt = $this->_getStatementForQuery( self::$_query_get_metadata );

        return $this->_destroyObjectCache( $stmt, MetadataStruct::class, [ 'id_project' => $id ] );
    }

    /**
     * @param int    $id_project
     * @param string $key
     * @param int    $ttl
     *
     * @return MetadataStruct|null
     * @throws ReflectionException
     */
    public function get( int $id_project, string $key, int $ttl = 0 ): ?MetadataStruct {
        $stmt = $this->setCacheTTL( $ttl )->_getStatementForQuery(
                "SELECT * FROM project_metadata WHERE " .
                " id_project = :id_project " .
                " AND `key` = :key "
        );

        /**
         * @var $result MetadataStruct[]
         */
        $result = $this->_fetchObjectMap( $stmt, MetadataStruct::class, [
                'id_project' => $id_project,
                'key'        => $key
        ] );

        return !empty( $result ) ? $result[ 0 ] : null;

    }

    /**
     * @param int    $id_project
     * @param string $key
     * @param string $value
     *
     * @return boolean
     * @throws ReflectionException
     */
    public function set( int $id_project, string $key, string $value ): bool {
        $sql  = "INSERT INTO project_metadata " .
                " ( id_project, `key`, value ) " .
                " VALUES " .
                " ( :id_project, :key, :value ) " .
                " ON DUPLICATE KEY UPDATE value = :value ";
        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id_project' => $id_project,
                'key'        => $key,
                'value'      => $value
        ] );

        $this->destroyMetadataCache( $id_project );

        return $conn->lastInsertId();

    }


    /**
     * @throws ReflectionException
     */
    public function delete( $id_project, $key ) {
        $sql = "DELETE FROM project_metadata " .
                " WHERE id_project = :id_project " .
                " AND `key` = :key ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id_project' => $id_project,
                'key'        => $key,
        ] );

        $this->destroyMetadataCache( $id_project );

    }

    public static function buildChunkKey( $key, JobStruct $chunk ): string {
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
    public function cleanupChunksOptions( array $jobs ) {
        foreach ( $jobs as $job ) {
            $chunk = ChunkDao::getByIdAndPassword( $job[ 'id' ], $job[ 'password' ] );

            foreach ( ChunkOptionsModel::$valid_keys as $key ) {
                $this->delete(
                        $chunk->id_project,
                        self::buildChunkKey( $key, $chunk )
                );
            }
        }
    }

    protected function _buildResult( array $array_result ) {
    }

    /**
     * @param $id_project
     *
     * @return array
     */
    public function getSubfilteringCustomHandlers( int $id_project ): array {

        try {
            $customHandlers = [];
            $subfiltering   = $this->get( $id_project, 'subfiltering', 86400 );

            if ( !empty( $subfiltering->value ) ) {
                $customHandlers = explode( ",", $subfiltering->value );
            }

            return $customHandlers;
        } catch ( Exception $exception ) {
            return [];
        }
    }
}
