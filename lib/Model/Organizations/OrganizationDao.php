<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:04
 */

namespace Organizations;

class OrganizationDao extends \DataAccess_AbstractDao {

    const TABLE = "organizations";
    const STRUCT_TYPE = "OrganizationStruct";

    protected static $auto_increment_fields = array('id');
    protected static $primary_keys = array('id');

    protected static $_query_find_by_id = " SELECT * FROM organizations WHERE id = :id " ;
    protected static $_query_get_personal_by_id = " SELECT * FROM organizations WHERE created_by = :created_by AND `type` = :type " ;

    /**
     * @param $id
     *
     * @return \DataAccess_IDaoStruct|\DataAccess_IDaoStruct[]|OrganizationStruct
     */
    public function findById( $id ) {

        $stmt = $this->_getStatementForCache( self::$_query_find_by_id );
        $organizationQuery = new OrganizationStruct();
        $organizationQuery->id = $id;

        return $this->_fetchObject( $stmt,
                $organizationQuery,
                array(
                        'id' => $organizationQuery->id,
                )
        );

    }

    /**
     * @param string $sql
     *
     * @return \PDOStatement
     */
    protected function _getStatementForCache( $sql ) {
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        return $stmt;
    }

    public function destroyCacheById( $id ){
        $stmt = $this->_getStatementForCache( self::$_query_find_by_id );
        $organizationQuery = new OrganizationStruct();
        $organizationQuery->id = $id;
        return $this->_destroyObjectCache( $stmt,
                array(
                        'id' => $organizationQuery->id,
                )
        );
    }

    public function getPersonalByUid( $uid ){
        $this->setCacheTTL( 60 * 60 * 24 * 30 );
        $stmt = $this->_getStatementForCache( self::$_query_get_personal_by_id );
        $organizationQuery = new OrganizationStruct();
        $organizationQuery->created_by = $uid;
        return $this->_fetchObject( $stmt,
                $organizationQuery,
                array(
                        'created_by' => $organizationQuery->created_by,
                        'type' => \Constants_Organizations::PERSONAL
                )
        );
    }

    public function destroyCachePersonalByUid( $uid ){
        $stmt = $this->_getStatementForCache( self::$_query_get_personal_by_id );
        $organizationQuery = new OrganizationStruct();
        $organizationQuery->created_by = $uid;
        return $this->_destroyObjectCache( $stmt,
                array(
                        'created_by' => $organizationQuery->created_by,
                        'type' => \Constants_Organizations::PERSONAL
                )
        );
    }

}