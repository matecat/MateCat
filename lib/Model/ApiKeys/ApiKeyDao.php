<?php

class ApiKeys_ApiKeyDao extends DataAccess_AbstractDao {

    /**
     * @param       $key
     *
     * @return ApiKeys_ApiKeyStruct|null
     */
    static function findByKey( $key ): ?ApiKeys_ApiKeyStruct {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM api_keys WHERE enabled AND api_key = :key " );
        $stmt->execute( [ 'key' => $key ] );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'ApiKeys_ApiKeyStruct' );

        return $stmt->fetch() ?? null;
    }

    public function create( $obj ) {
        $conn = $this->database->getConnection();

        $obj->create_date = date( 'Y-m-d H:i:s' );
        $obj->last_update = date( 'Y-m-d H:i:s' );

        $stmt = $conn->prepare( "INSERT INTO api_keys " .
                " ( uid, api_key, api_secret, create_date, last_update, enabled ) " .
                " VALUES " .
                " ( :uid, :api_key, :api_secret, :create_date, :last_update, :enabled ) "
        );

        $values = array_diff_key( $obj->toArray(), [ 'id' => null ] );

        $this->database->begin();
        $stmt->execute( $values );
        $result = $this->getById( $conn->lastInsertId() );
        $this->database->commit();

        return $result[ 0 ];
    }

    /**
     * @param $id
     *
     * @return ApiKeys_ApiKeyStruct[]
     */
    public function getById( $id ) {
        $conn = $this->database->getConnection();

        $stmt = $conn->prepare( " SELECT * FROM api_keys WHERE id = ? " );
        $stmt->execute( [ $id ] );

        return $stmt->fetchAll( PDO::FETCH_CLASS, 'ApiKeys_ApiKeyStruct' );
    }

    /**
     * @param $uid
     *
     * @return ApiKeys_ApiKeyStruct
     */
    public function getByUid( $uid ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM api_keys WHERE enabled AND uid = :uid " );
        $stmt->execute( [ 'uid' => $uid ] );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'ApiKeys_ApiKeyStruct' );

        return $stmt->fetch();
    }

    public function deleteByUid( $uid ) {

        $apiKey = $this->getByUid( $uid );

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "DELETE FROM api_keys WHERE id = :id " );
        $stmt->execute( [ 'id' => $apiKey->id ] );

        return $stmt->rowCount();
    }
}
