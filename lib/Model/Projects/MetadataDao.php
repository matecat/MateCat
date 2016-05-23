<?php

class Projects_MetadataDao extends DataAccess_AbstractDao {

    const WORD_COUNT_RAW = 'raw';
    const WORD_COUNT_EQUIVALENT = 'equivalent';

    /**
     * @param $id
     *
     * @return Projects_MetadataStruct[]
     */
  public static function getByProjectId( $id ) {
      $dao = new Projects_MetadataDao(Database::obtain());
      return $dao->allByProjectId( $id );
  }

    /**
     * @param $id
     *
     * @return Projects_MetadataStruct[]
     */
  public function allByProjectId( $id ) {
      $conn = Database::obtain()->getConnection();
      $stmt = $conn->prepare(
          "SELECT * FROM project_metadata WHERE " .
          " id_project = :id_project "
      );
      $stmt->execute( array( 'id_project' => $id ) );
      $stmt->setFetchMode(PDO::FETCH_CLASS, 'Projects_MetadataStruct');
      return $stmt->fetchAll();
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

      $result = $this->_fetchObject( $stmt, new Projects_MetadataStruct(), array(
              'id_project' => $id_project,
              'key' => $key
      ) );

      return @$result[0];

  }

    /**
     * @param $id_project
     * @param $key
     * @param $value
     *
     * @return Projects_MetadataStruct
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

      return $this->get($id_project, $key);
  }


  public function delete($id_project, $key) {
      $sql = "DELETE FROM project_metadata " .
          " WHERE id_project = :id_project " .
          " AND `key` = :key "  ;

      $conn = Database::obtain()->getConnection();
      $stmt = $conn->prepare(  $sql );
      $stmt->execute( array(
          'id_project' => $id_project,
          'key' => $key,
      ) );

  }

  protected function _buildResult( $array_result ) {
  }
}
