<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:04
 */

namespace Organizations;

use Database;
use PDO;
use Users_UserStruct;

class OrganizationDao extends \DataAccess_AbstractDao {

    const TABLE = "organizations";
    const STRUCT_TYPE = "OrganizationStruct";

    protected static $auto_increment_fields = array('id');
    protected static $primary_keys = array('id');

    protected static $_query_find_by_id = " SELECT * FROM organizations WHERE id = :id " ;
    protected static $_query_get_personal_by_id = " SELECT * FROM organizations WHERE created_by = :created_by AND `type` = :type " ;
    protected static $_query_get_user_organizations = " SELECT * FROM organizations WHERE created_by = :created_by " ;
    protected static $_update_organization_by_id = " UPDATE organizations SET name = :name WHERE id = :id " ;

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
     * @param Users_UserStruct $user
     * @param array            $params
     *
     * @return OrganizationStruct
     */
    public function createUserOrganization( \Users_UserStruct $user, $params = array() ) {

        $organizationStruct = new OrganizationStruct(array(
            'name' => $params['name'],
            'created_by' =>  $user->uid,
            'created_at' => \Utils::mysqlTimestamp( time() ),
            'type' => $params['type']
        )) ;

        $orgId = OrganizationDao::insertStruct( $organizationStruct  ) ;
        $organizationStruct->id = $orgId ;

        $membershipStruct = new MembershipStruct(array(
            'id_organization' => $orgId,
            'uid' => $user->uid,
            'is_admin' => TRUE
        ));

        $membershipId = MembershipDao::insertStruct( $membershipStruct );

        return $organizationStruct ;
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

    public function getPersonalByUser( Users_UserStruct $user ){
        return $this->getPersonalByUid( $user->uid );
    }

    public function getPersonalByUid( $uid ){
        $stmt = $this->_getStatementForCache( self::$_query_get_personal_by_id );
        $organizationQuery = new OrganizationStruct();
        $organizationQuery->created_by = $uid;
        return $this->_fetchObject( $stmt,
                $organizationQuery,
                array(
                        'created_by' => $organizationQuery->created_by,
                        'type' => \Constants_Organizations::PERSONAL
                )
        )[ 0 ];
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

    public function findUserCreatedOrganizations( \Users_UserStruct $user ) {

        $stmt = $this->_getStatementForCache( self::$_query_get_user_organizations );

        $organizationQuery = new OrganizationStruct();
        $organizationQuery->created_by = $user->uid;
        return static::resultOrNull( $this->_fetchObject( $stmt,
                $organizationQuery,
                array(
                        'created_by' => $organizationQuery->created_by,
                )
        )[ 0 ] );

    }

    public function destroyCacheUserCreatedOrganizations( \Users_UserStruct $user ){
        $stmt = $this->_getStatementForCache( self::$_query_get_user_organizations );

        $organizationQuery = new OrganizationStruct();
        $organizationQuery->created_by = $user->uid;
        return $this->_destroyObjectCache( $stmt,
                array(
                        'created_by' => $organizationQuery->created_by,
                )
        );
    }

    public function updateOrganizationName( OrganizationStruct $org ){

        $conn = Database::obtain()->begin();

        $stmt = $conn->prepare( self::$_update_organization_by_id );
        $stmt->bindValue(':id', $org->id, PDO::PARAM_INT);
        $stmt->bindValue(':name', $org->name, PDO::PARAM_STR);

        $stmt->execute();
        $org = $this->findById( $org->id );
        $conn->commit();

        return $org;
    }

}