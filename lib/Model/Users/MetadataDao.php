<?php

namespace Model\Users;

use Model\DataAccess\AbstractDao;
use Model\Database;
use PDO;
use ReflectionException;

class MetadataDao extends AbstractDao {

    const TABLE = 'user_metadata';

    const _query_metadata_by_uid_key = "SELECT * FROM user_metadata WHERE uid = :uid AND `key` = :key ";

    /**
     * @throws ReflectionException
     */
    public function getAllByUidList( array $UIDs ): array {

        if ( empty( $UIDs ) ) {
            return [];
        }

        $stmt = $this->_getStatementForQuery(
                "SELECT * FROM user_metadata WHERE " .
                " uid IN( " . str_repeat( '?,', count( $UIDs ) - 1 ) . '?' . " ) "
        );

        /**
         * @var $rs MetadataStruct[]
         */
        $rs = $this->_fetchObjectMap(
                $stmt,
                MetadataStruct::class,
                $UIDs
        );

        $resultSet = [];
        foreach ( $rs as $metaDataRow ) {
            $resultSet[ $metaDataRow->uid ][] = $metaDataRow;
        }

        return $resultSet;

    }

    public function getAllByUid( $uid ): array {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
                "SELECT * FROM user_metadata WHERE " .
                " uid = :uid "
        );
        $stmt->execute( [ 'uid' => $uid ] );
        $stmt->setFetchMode( PDO::FETCH_CLASS, MetadataStruct::class );

        return $stmt->fetchAll();
    }

    /**
     * @param $uid
     * @param $key
     *
     * @return MetadataStruct
     * @throws ReflectionException
     */
    public function get( $uid, $key ): ?MetadataStruct {
        $stmt = $this->_getStatementForQuery( self::_query_metadata_by_uid_key );
        /** @var $result MetadataStruct */
        $result = $this->_fetchObjectMap( $stmt, MetadataStruct::class, [
                'uid' => $uid,
                'key' => $key
        ] );

        return $result[ 0 ] ?? null;
    }

    /**
     * @throws ReflectionException
     */
    public function destroyCacheKey( $uid, $key ): bool {
        $stmt = $this->_getStatementForQuery( self::_query_metadata_by_uid_key );

        return $this->_destroyObjectCache( $stmt, MetadataStruct::class, [ 'uid' => $uid, 'key' => $key ] );
    }

    /**
     * @param int          $uid
     * @param string       $key
     * @param array|string $value
     *
     * @return MetadataStruct
     * @throws ReflectionException
     */
    public function set( int $uid, string $key, $value ): MetadataStruct {
        $sql = "INSERT INTO user_metadata " .
                " ( uid, `key`, value ) " .
                " VALUES " .
                " ( :uid, :key, :value ) " .
                " ON DUPLICATE KEY UPDATE value = :value ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'uid'   => $uid,
                'key'   => $key,
                'value' => ( is_array( $value ) ) ? serialize( $value ) : $value,
        ] );

        $this->destroyCacheKey( $uid, $key );

        return new MetadataStruct( [
                'id'    => $conn->lastInsertId(),
                'uid'   => $uid,
                'key'   => $key,
                'value' => $value
        ] );

    }


    /**
     * @param int    $uid
     * @param string $key
     *
     * @throws ReflectionException
     */
    public function delete( int $uid, string $key ) {
        $sql = "DELETE FROM user_metadata " .
                " WHERE uid = :uid " .
                " AND `key` = :key ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'uid' => $uid,
                'key' => $key,
        ] );
        $this->destroyCacheKey( $uid, $key );
    }

    protected function _buildResult( array $array_result ) {
        // TODO: Implement _buildResult() method.
    }

}