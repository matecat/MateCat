<?php

class OwnerFeatures_OwnerFeatureDao extends DataAccess_AbstractDao {

    public function findFromUserOrTeam( Users_UserStruct $user, \Teams\TeamStruct $team ) {
       // TODO:
    }

    public function getByTeam( \Teams\TeamStruct $team ) {
        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( "SELECT * FROM owner_features " .
            " WHERE owner_features.id_team = :id_team " .
            " AND owner_features.enabled "
        );
        $stmt->execute( array( 'id_team' => $team->id) );
        $stmt->setFetchMode(PDO::FETCH_CLASS, 'OwnerFeatures_OwnerFeatureStruct');
        return $stmt->fetchAll();
    }

    public function create( OwnerFeatures_OwnerFeatureStruct $obj ) {
        $conn = Database::obtain()->getConnection();

        $conn->beginTransaction();

        $obj->create_date = date('Y-m-d H:i:s');
        $obj->last_update = date('Y-m-d H:i:s');

        $stmt = $conn->prepare( "INSERT INTO owner_features " .
            " ( uid, feature_code, options, create_date, last_update, enabled, id_team )" .
            " VALUES " .
            " ( :uid, :feature_code, :options, :create_date, :last_update, :enabled, :id_team );"
        );

        Log::doLog( $obj->attributes() );

        $values = array_diff_key( $obj->attributes(), array('id' => null) );

        $stmt->execute( $values );
        $record = $this->getById( $conn->lastInsertId() );
        $conn->commit() ;

        return $record ;
    }

    /**
     * @param $id_customer
     *
     * @return OwnerFeatures_OwnerFeatureStruct[]
     */
    public static function getByIdCustomer( $id_customer ) {
        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( "SELECT * FROM owner_features " .
            " INNER JOIN users ON users.uid = owner_features.uid " .
            " WHERE users.email = :id_customer " .
            " AND owner_features.enabled "
        );
        $stmt->execute( array( 'id_customer' => $id_customer) );
        $stmt->setFetchMode(PDO::FETCH_CLASS, 'OwnerFeatures_OwnerFeatureStruct');
        return $stmt->fetchAll();
    }

    public static function getById( $id ) {
        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare(" SELECT * FROM owner_features WHERE id = ? ");
        $stmt->execute( array( $id ) );
        $stmt->setFetchMode(PDO::FETCH_CLASS, 'OwnerFeatures_OwnerFeatureStruct');
        return $stmt->fetch();
    }

    protected function _buildResult( $array_result ) {

    }

}
