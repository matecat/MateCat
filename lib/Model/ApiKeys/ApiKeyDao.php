<?php

class ApiKeys_ApiKeyDao extends DataAccess_AbstractDao {

    /**
     * @param       $key
     * @param array $options
     *
     * @return ApiKeys_ApiKeyStruct
     */
    static function findByKey( $key, $options = [] ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM api_keys WHERE enabled AND api_key = :key " );
        $stmt->execute( [ 'key' => $key ] );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'ApiKeys_ApiKeyStruct' );

        return $stmt->fetch();
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
     * @return mixed
     */
    public function getByUid( $uid ) {
        $conn = $this->database->getConnection();

        $stmt = $conn->prepare( " SELECT * FROM api_keys WHERE uid = ? and enabled = 1 " );
        $stmt->execute( [ $uid ] );

        return @$stmt->fetchAll( PDO::FETCH_CLASS, 'ApiKeys_ApiKeyStruct' )[0];
    }

}
