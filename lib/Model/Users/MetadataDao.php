<?php

namespace Users;

use Database ;
use PDO;

class MetadataDao extends \DataAccess_AbstractDao
{

    public function allByProjectId( $id ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
            "SELECT * FROM user_metadata WHERE " .
            " uid = :uid "
        );
        $stmt->execute( array( 'uid' => $id ) );
        $stmt->setFetchMode(PDO::FETCH_CLASS, '\Users\MetadataStruct');
        return $stmt->fetchAll();
    }
    /**
     * @param $uid
     * @param $key
     *
     * @return MetadataDao
     */
  public function get( $uid, $key ) {
      $stmt = $this->_getStatementForCache(
              "SELECT * FROM user_metadata WHERE " .
              " uid = :uid " .
              " AND `key` = :key "
      );

      $result = $this->_fetchObject( $stmt, new MetadataStruct(), array(
              'uid' => $uid,
              'key' => $key
      ) );

      return @$result[0];

  }

    /**
     * @param $uid
     * @param $key
     * @param $value
     *
     * @return MetadataStruct
     */
  public function set($uid, $key, $value) {
      $sql = "INSERT INTO user_metadata " .
          " ( uid, `key`, value ) " .
          " VALUES " .
          " ( :uid, :key, :value ) " .
          " ON DUPLICATE KEY UPDATE value = :value " ;
      $conn = Database::obtain()->getConnection();
      $stmt = $conn->prepare(  $sql );
      $stmt->execute( array(
          'uid' => $uid,
          'key' => $key,
          'value' => $value
      ) );

      return $this->get($uid, $key);
  }


  public function delete($uid, $key) {
      $sql = "DELETE FROM user_metadata " .
          " WHERE uid = :uid " .
          " AND `key` = :key "  ;

      $conn = Database::obtain()->getConnection();
      $stmt = $conn->prepare(  $sql );
      $stmt->execute( array(
          'uid' => $uid,
          'key' => $key,
      ) );
  }

  protected function _buildResult($array_result)
  {
      // TODO: Implement _buildResult() method.
  }

}