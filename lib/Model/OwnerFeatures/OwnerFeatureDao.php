<?php

namespace Model\OwnerFeatures;

use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use Model\DataAccess\IDaoStruct;
use PDO;
use ReflectionException;
use Utils\Logger\LoggerFactory;

class OwnerFeatureDao extends AbstractDao {

    const string query_by_user_email = " SELECT * FROM owner_features INNER JOIN users ON users.uid = owner_features.uid WHERE users.email = :id_customer AND owner_features.enabled ORDER BY id ";
    const string query_user_id       = "SELECT * FROM owner_features WHERE uid = :uid ORDER BY id";

    /**
     * @param IDaoStruct|OwnerFeatureStruct $obj
     *
     * @return OwnerFeatureStruct
     */
    public function create( IDaoStruct $obj ) {

        $conn = Database::obtain()->getConnection();

        Database::obtain()->begin();

        /**
         * @var OwnerFeatureStruct $obj
         */
        $obj->create_date = date( 'Y-m-d H:i:s' );
        $obj->last_update = date( 'Y-m-d H:i:s' );

        $stmt = $conn->prepare( "INSERT INTO owner_features " .
                " ( uid, feature_code, options, create_date, last_update, enabled, id_team )" .
                " VALUES " .
                " ( :uid, :feature_code, :options, :create_date, :last_update, :enabled, :id_team );"
        );

        LoggerFactory::doJsonLog( $obj->toArray() );

        $values = array_diff_key( $obj->toArray(), [ 'id' => null ] );

        $stmt->execute( $values );
        $record = $this->getById( $conn->lastInsertId() );
        $conn->commit();

        return $record;
    }

    /**
     * @param string $id_customer
     *
     * @param int    $ttl
     *
     * @return IDaoStruct[]|OwnerFeatureStruct[]
     * @throws ReflectionException
     */
    public static function getByIdCustomer( string $id_customer, int $ttl = 3600 ): array {
        $conn    = Database::obtain()->getConnection();
        $thisDao = new self();
        $stmt    = $conn->prepare( self::query_by_user_email );

        return $thisDao->setCacheTTL( $ttl )->_fetchObjectMap( $stmt, OwnerFeatureStruct::class, [
                'id_customer' => $id_customer
        ] ) ?? [];
    }

    /**
     * Destroy a cached object
     *
     * @param $id_customer
     *
     * @return bool
     * @throws ReflectionException
     */
    public static function destroyCacheByIdCustomer( $id_customer ): bool {
        $thisDao = new self();
        $stmt    = $thisDao->_getStatementForQuery( self::query_by_user_email );

        return $thisDao->_destroyObjectCache( $stmt, OwnerFeatureStruct::class, [ 'id_customer' => $id_customer ] );
    }

    /**
     * @throws ReflectionException
     */
    public static function getByUserId( ?int $uid, int $ttl = 3600 ): array {

        if ( empty( $uid ) ) {
            return [];
        }

        $conn    = Database::obtain()->getConnection();
        $thisDao = new self();
        $stmt    = $conn->prepare( self::query_user_id );

        return $thisDao->setCacheTTL( $ttl )->_fetchObjectMap( $stmt, OwnerFeatureStruct::class, [
                'uid' => $uid
        ] ) ?? [];
    }

    /**
     * @throws ReflectionException
     */
    public static function destroyCacheByUserId( int $uid ): bool {
        $thisDao = new self();
        $stmt    = $thisDao->_getStatementForQuery( self::query_user_id );

        return $thisDao->_destroyObjectCache( $stmt, OwnerFeatureStruct::class, [ 'uid' => $uid ] );
    }

    /**
     * Get owner feature by ID
     *
     * @param int $id
     *
     * @return ?OwnerFeatureStruct
     */
    public static function getById( int $id ): ?OwnerFeatureStruct {
        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( " SELECT * FROM owner_features WHERE id = ? " );
        $stmt->execute( [ $id ] );
        $stmt->setFetchMode( PDO::FETCH_CLASS, OwnerFeatureStruct::class );

        return $stmt->fetch() ?: null;
    }

}
