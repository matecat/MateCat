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

        Log::doLog( $obj->attributes() );

        $values = array_diff_key( $obj->attributes(), array('id' => null) );

        $stmt->execute( $values );
        return $this->getById( $conn->lastInsertId() );
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

    /**
     * @param $feature_code
     * @param $email
     *
     * @return OwnerFeatures_OwnerFeatureStruct
     */
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

    /**
     * @param $feature_code
     * @param $email
     *
     * @return bool
     */
    public function isFeatureEnabled( $feature_code, $email ) {
        $feature = self::getByOwnerEmailAndCode( $feature_code, $email );

        if($feature != null) {
            return !!$feature->enabled;
        }

        return false;
    }

    protected function _buildResult( $array_result ) {

    }

}
