<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:04
 */

namespace Organizations;

use PDO;

class OrganizationDao extends \DataAccess_AbstractDao {

    const TABLE = "organizations";
    const STRUCT_TYPE = "OrganizationStruct";

    protected static $auto_increment_fields = array('id');
    protected static $primary_keys = array('id');

    /**
     * @param $id
     * @return OrganizationStruct
     */
    public function findById( $id ) {
        $sql = " SELECT * FROM organizations WHERE id = ? " ;
        $stmt = $this->getConnection()->getConnection()->prepare( $sql ) ;
        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Organizations\OrganizationStruct' );
        $stmt->execute(array( $id)) ;

        return $stmt->fetch() ;
    }



}