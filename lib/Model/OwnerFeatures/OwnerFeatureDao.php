<?php

use Teams\TeamStruct;

class OwnerFeatures_OwnerFeatureDao extends DataAccess_AbstractDao {

    const query_by_user_email = " SELECT * FROM owner_features INNER JOIN users ON users.uid = owner_features.uid WHERE users.email = :id_customer AND owner_features.enabled ORDER BY id ";

    public function findFromUserOrTeam( Users_UserStruct $user, TeamStruct $team ) {
       // TODO:
    }

    public function getByTeam( TeamStruct $team ) {
        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( "SELECT * FROM owner_features " .
            " WHERE owner_features.id_team = :id_team " .
            " AND owner_features.enabled "
        );
        $stmt->execute( array( 'id_team' => $team->id) );
        $stmt->setFetchMode(PDO::FETCH_CLASS, 'OwnerFeatures_OwnerFeatureStruct');
        return $stmt->fetchAll();
    }

    /**
     * @param DataAccess_IDaoStruct|OwnerFeatures_OwnerFeatureStruct $obj
     *
     * @return int
     */
    public function create( DataAccess_IDaoStruct $obj ) {

        $conn = Database::obtain()->getConnection();

        \Database::obtain()->begin();

        /**
         * @var OwnerFeatures_OwnerFeatureStruct $obj
         */
        $obj->create_date = date('Y-m-d H:i:s');
        $obj->last_update = date('Y-m-d H:i:s');

        $stmt = $conn->prepare( "INSERT INTO owner_features " .
            " ( uid, feature_code, options, create_date, last_update, enabled, id_team )" .
            " VALUES " .
            " ( :uid, :feature_code, :options, :create_date, :last_update, :enabled, :id_team );"
        );

        Log::doJsonLog( $obj->toArray() );

        $values = array_diff_key( $obj->toArray(), array('id' => null) );

        $stmt->execute( $values );
        $record = $this->getById( $conn->lastInsertId() );
        $conn->commit() ;

        return $record ;
    }

    /**
     * @param     $id_customer
     *
     * @param int $ttl
     *
     * @return DataAccess_IDaoStruct[]|OwnerFeatures_OwnerFeatureStruct[]
     */
    public static function getByIdCustomer( $id_customer, $ttl = 3600 ) {
        $conn = Database::obtain()->getConnection();
        $thisDao = new self();
        $stmt = $conn->prepare( self::query_by_user_email );
        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new OwnerFeatures_OwnerFeatureStruct(), [
                'id_customer' => $id_customer
        ] );
    }

    /**
     * Destroy a cached object
     *
     * @param $id_customer
     *
     * @return bool|int
     */
    public static function destroyCacheByIdCustomer( $id_customer ){
        $thisDao = new self();
        $stmt = $thisDao->_getStatementForCache( self::query_by_user_email );
        return $thisDao->_destroyObjectCache( $stmt, [ 'id_customer' => $id_customer ] );
    }

    public static function getById( $id ) {
        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare(" SELECT * FROM owner_features WHERE id = ? ");
        $stmt->execute( array( $id ) );
        $stmt->setFetchMode(PDO::FETCH_CLASS, 'OwnerFeatures_OwnerFeatureStruct');
        return $stmt->fetch();
    }

}
