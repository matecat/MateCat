<?php

class OwnerFeatures_OwnerFeatureDao extends DataAccess_AbstractDao {

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

        $stmt->setFetchMode(PDO::FETCH_CLASS, 'OwnerFeatures_OwnerFeaturesStruct');
        return $stmt->fetch();
    }

    protected function _buildResult( $array_result ) {

    }

}
