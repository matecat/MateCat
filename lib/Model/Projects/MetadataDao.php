<?php

class Projects_MetadataDao extends DataAccess_AbstractDao {
    const FEATURES_KEY = 'features' ;
    const TABLE = 'project_metadata' ;

    const WORD_COUNT_RAW = 'raw';
    const WORD_COUNT_EQUIVALENT = 'equivalent';

    protected static $_query_get_metadata = "SELECT * FROM project_metadata WHERE id_project = :id_project ";

    /**
     * @param $id
     *
     * @return Projects_MetadataStruct[]
     */
  public static function getByProjectId( $id ) {
      $dao = new Projects_MetadataDao();
      return $dao->setCacheTTL( 60 * 60 )->allByProjectId( $id );
  }

    /**
     * @param $id
     *
     * @return null|Projects_MetadataStruct[]
     */
  public function allByProjectId( $id ) {

      $conn = Database::obtain()->getConnection();
      $stmt = $conn->prepare( self::$_query_get_metadata );

      /**
       * @var $metadata Projects_MetadataStruct[]
       */
      $metadata = $this->_fetchObject( $stmt, new Projects_MetadataStruct(), [ 'id_project' => $id ] );

      return $metadata;

  }

  public function destroyMetadataCache( $id ){
      $stmt = $this->_getStatementForCache( self::$_query_get_metadata );
      return $this->_destroyObjectCache( $stmt, [ 'id_project' => $id ] );
  }

    /**
     * @param $id_project
     * @param $key
     *
     * @return Projects_MetadataStruct
     */
  public function get( $id_project, $key ) {
      $stmt = $this->_getStatementForCache(
              "SELECT * FROM project_metadata WHERE " .
              " id_project = :id_project " .
              " AND `key` = :key "
      );

      /**
       * @var $result Projects_MetadataStruct[]
       */
      $result = $this->_fetchObject( $stmt, new Projects_MetadataStruct(), array(
              'id_project' => $id_project,
              'key' => $key
      ) );

      return !empty( $result) ? $result[0] : null;

  }

    /**
     * @param $id_project
     * @param $key
     * @param $value
     *
     * @return boolean
     */
  public function set($id_project, $key, $value) {
      $sql = "INSERT INTO project_metadata " .
          " ( id_project, `key`, value ) " .
          " VALUES " .
          " ( :id_project, :key, :value ) " .
          " ON DUPLICATE KEY UPDATE value = :value " ;
      $conn = Database::obtain()->getConnection();

      $stmt = $conn->prepare(  $sql );
      $stmt->execute( array(
          'id_project' => $id_project,
          'key' => $key,
          'value' => $value
      ) );

      $this->destroyMetadataCache( $id_project );

      return $conn->lastInsertId();

  }


  public function delete($id_project, $key) {
      $sql = "DELETE FROM project_metadata " .
          " WHERE id_project = :id_project " .
          " AND `key` = :key "  ;

      $conn = Database::obtain()->getConnection();
      $stmt = $conn->prepare(  $sql );
      $stmt->execute( [
              'id_project' => $id_project,
              'key'        => $key,
      ] );

      $this->destroyMetadataCache( $id_project );

  }

    public static function buildChunkKey( $key, Chunks_ChunkStruct $chunk ) {
        return "{$key}_chunk_{$chunk->id}_{$chunk->password}" ;
    }

    /**
     * Clean up the chunks options before the job merging
     *
     * @param $jobs array Associative array with the Jobs
     */
    public function cleanupChunksOptions( $jobs ) {
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

  protected function _buildResult( $array_result ) {
  }
}
