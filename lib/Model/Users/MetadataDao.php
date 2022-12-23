<?php

namespace Users;

use Database;
use PDO;

class MetadataDao extends \DataAccess_AbstractDao {

    const TABLE = 'user_metadata' ;

    const _query_metadata_by_uid_key = "SELECT * FROM user_metadata WHERE uid = :uid AND `key` = :key ";

    public function getAllByUidList( Array $UIDs ) {

        if( empty( $UIDs ) ){
            return [];
        }

        $stmt = $this->_getStatementForCache(
                "SELECT * FROM user_metadata WHERE " .
                " uid IN( " . str_repeat( '?,', count( $UIDs ) - 1 ) . '?' . " ) "
        );

        /**
         * @var $rs MetadataStruct[]
         */
        $rs = $this->_fetchObject(
                $stmt,
                new MetadataStruct(),
                $UIDs
        );

        $resultSet = [];
        foreach( $rs as $metaDataRow ){
            $resultSet[ $metaDataRow->uid ][] = $metaDataRow;
        }

        return $resultSet;

    }

    public function getAllByUid( $uid ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
            "SELECT * FROM user_metadata WHERE " .
            " uid = :uid "
        );
        $stmt->execute( array( 'uid' => $uid ) );
        $stmt->setFetchMode(PDO::FETCH_CLASS, '\Users\MetadataStruct');
        return $stmt->fetchAll();
    }
    /**
     * @param $uid
     * @param $key
     *
     * @return MetadataStruct
     */
  public function get( $uid, $key ) {
      $stmt = $this->_getStatementForCache( self::_query_metadata_by_uid_key );
      $result = $this->_fetchObject( $stmt, new MetadataStruct(), [
              'uid' => $uid,
              'key' => $key
      ] );
      return @$result[0];
  }

  public function destroyCacheKey( $uid, $key ){
      $stmt = $this->_getStatementForCache( self::_query_metadata_by_uid_key );
      return $this->_destroyObjectCache( $stmt, [ 'uid' => $uid, 'key' => $key ] );
  }

    /**
     * @param $uid
     * @param $key
     * @param $value
     *
     * @return MetadataStruct
     */
  public function set($uid, $key, $value) {
      $sql  = "INSERT INTO user_metadata " .
              " ( uid, `key`, value ) " .
              " VALUES " .
              " ( :uid, :key, :value ) " .
              " ON DUPLICATE KEY UPDATE value = :value ";
      $conn = Database::obtain()->getConnection();
      $stmt = $conn->prepare( $sql );
      $stmt->execute( array(
              'uid'   => $uid,
              'key'   => $key,
              'value' => $value
      ) );

      $this->destroyCacheKey( $uid, $key );

      return new MetadataStruct( [
              'id'    => $conn->lastInsertId(),
              'uid'   => $uid,
              'key'   => $key,
              'value' => $value
      ] );

  }


    /**
     * @param int $uid
     * @param string $key
     */
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
      $this->destroyCacheKey( $uid, $key );
  }

  protected function _buildResult($array_result)
  {
      // TODO: Implement _buildResult() method.
  }

}