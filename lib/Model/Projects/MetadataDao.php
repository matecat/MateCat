<?php

use Exceptions\NotFoundException;
use Projects\ChunkOptionsModel;

class Projects_MetadataDao extends DataAccess_AbstractDao {
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
     * @return Projects_MetadataStruct[]
     * @throws ReflectionException
     */
    public static function getByProjectId( $id ): array {
        $dao = new Projects_MetadataDao();

        return $dao->setCacheTTL( 60 * 60 )->allByProjectId( $id );
    }

    /**
     * @param $id
     *
     * @return Projects_MetadataStruct[]
     * @throws ReflectionException
     */
    public function allByProjectId( $id ): array {

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( self::$_query_get_metadata );

        /**
         * @var Projects_MetadataStruct[]
         */
        return $this->_fetchObject( $stmt, new Projects_MetadataStruct(), [ 'id_project' => $id ] );

    }

    /**
     * @throws ReflectionException
     */
    public function destroyMetadataCache( $id ): bool {
        $stmt = $this->_getStatementForQuery( self::$_query_get_metadata );

        return $this->_destroyObjectCache( $stmt, Projects_MetadataStruct::class, [ 'id_project' => $id ] );
    }

    /**
     * @param int    $id_project
     * @param string $key
     * @param int    $ttl
     *
     * @return Projects_MetadataStruct|null
     * @throws ReflectionException
     */
    public function get( int $id_project, string $key, int $ttl = 0 ): ?Projects_MetadataStruct {
        $stmt = $this->setCacheTTL( $ttl )->_getStatementForQuery(
                "SELECT * FROM project_metadata WHERE " .
                " id_project = :id_project " .
                " AND `key` = :key "
        );

        /**
         * @var $result Projects_MetadataStruct[]
         */
        $result = $this->_fetchObject( $stmt, new Projects_MetadataStruct(), [
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

    public static function buildChunkKey( $key, Jobs_JobStruct $chunk ): string {
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
            $chunk = Chunks_ChunkDao::getByIdAndPassword( $job[ 'id' ], $job[ 'password' ] );

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
}
