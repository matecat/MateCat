<?php

class OwnerFeatures_OwnerFeatureDao extends DataAccess_AbstractDao {
    public function create( $obj ) {
        $conn = Database::obtain()->getConnection();

        $obj->create_date = date('Y-m-d H:i:s');
        $obj->last_update = date('Y-m-d H:i:s');

        $stmt = $conn->prepare( "INSERT INTO owner_features " .
            " ( uid, feature_code, options, create_date, last_update, enabled )" .
            " VALUES " .
            " ( :uid, :feature_code, :options, :create_date, :last_update, :enabled );"
        );

        $values = array_diff_key( $obj->toArray(), array('id' => null) );

        $stmt->execute( $values );
        return $this->getById( $conn->lastInsertId() );
    }

    public static function getById( $id ) {
        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare(" SELECT * FROM owner_features WHERE id = ? ");
        $stmt->execute( array( $id ) );
        $stmt->setFetchMode(PDO::FETCH_CLASS, 'OwnerFeatures_OwnerFeatureStruct');
        return $stmt->fetch();
    }

    public static function getByOwnerEmailAndCode( $feature_code, $email ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM owner_features " .
            " INNER JOIN users ON users.uid = owner_features.uid " .
            " WHERE users.email = :email " .
            " AND owner_features.feature_code = :feature_code " .
            " AND owner_features.enabled "
        );

        $stmt->execute( array(
            'email' =>  $email ,
            'feature_code' => $feature_code
        ) );

        $stmt->setFetchMode(PDO::FETCH_CLASS, 'OwnerFeatures_OwnerFeatureStruct');
        return $stmt->fetch();
    }

    protected function _buildResult( $array_result ) {

    }

}
