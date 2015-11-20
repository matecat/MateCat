<?php

class Projects_MetadataDao {
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

  public function get($id_project, $key) {
      $conn = Database::obtain()->getConnection();
      $stmt = $conn->prepare(
          "SELECT * FROM project_metadata WHERE " .
          " id_project = :id_project " .
          " AND `key` = :key "
      );

      $stmt->execute( array(
          'id_project' => $id_project,
          'key' => $key
      ) );

      $stmt->setFetchMode(PDO::FETCH_CLASS, 'Projects_MetadataStruct');
      return $stmt->fetch();
  }

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
