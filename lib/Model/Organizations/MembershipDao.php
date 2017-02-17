<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:45
 */

namespace Organizations;

use API\V2\Json\User;
use PDO ;
use Users_UserDao;

class MembershipDao extends \DataAccess_AbstractDao
{

    const TABLE = "organizations_users";
    const STRUCT_TYPE = "\\Organizations\\MembershipStruct";

    protected static $auto_increment_fields = array('id');
    protected static $primary_keys = array('id');

    protected static $_query_organization_from_uid_and_id = " SELECT organizations.* FROM organizations
              JOIN organizations_users ON organizations_users.id_organization = organizations.id
            WHERE organizations_users.uid = ? AND organizations.id = ?
            " ;

    protected static $_query_user_organizations = " 
          SELECT organizations.* FROM organizations
              JOIN organizations_users ON organizations_users.id_organization = organizations.id
            WHERE organizations_users.uid = :uid 
    ";

    protected static $_query_member_list = "
          SELECT ou.id, ou.id_organization, ou.uid, ou.is_admin
          FROM organizations_users ou
          WHERE ou.id_organization = :id_organization
    ";

    protected static $_delete_member = "
        DELETE FROM organizations_users WHERE uid = :uid AND id_organization = :id_organization
    ";

    public function findById( $id ) {
        $sql = " SELECT * FROM " . self::TABLE . " WHERE id = ? " ;
        $stmt = $this->getConnection()->getConnection()->prepare( $sql ) ;
        $stmt->setFetchMode( PDO::FETCH_CLASS, self::STRUCT_TYPE );
        $stmt->execute( array( $id ) );

        return $stmt->fetch() ;
    }

    /**
     * Find ONE team for the given user. This is to enforce the temporary requirement to
     * have just one team per user.
     *
     * @param \Users_UserStruct $user
     *
     * @return null|OrganizationStruct[]
     */
    public function findUserOrganizations( \Users_UserStruct $user ) {

        $stmt = $this->_getStatementForCache( self::$_query_user_organizations );
        $organizationQuery = new OrganizationStruct();
        return static::resultOrNull( $this->_fetchObject( $stmt,
                $organizationQuery,
                array(
                        'uid' => $user->uid,
                )
        ) );

    }

    /**
     * Cache deletion for @see MembershipDao::findUserOrganizations
     *
     * @param \Users_UserStruct $user
     *
     * @return bool|int
     */
    public function destroyCacheUserOrganizations( \Users_UserStruct $user ){
        $stmt = $this->_getStatementForCache( self::$_query_user_organizations );
        return $this->_destroyObjectCache( $stmt,
                array(
                        'uid' => $user->uid,
                )
        );
    }

    /**
     * Finds an organization in user scope.
     *
     * @param $id
     * @param \Users_UserStruct $user
     * @return null|OrganizationStruct
     */
    public function findOrganizationByIdAndUser( $id, \Users_UserStruct $user ) {

        $stmt = $this->getConnection()->getConnection()->prepare( self::$_query_organization_from_uid_and_id ) ;
        $stmt->setFetchMode( PDO::FETCH_CLASS, get_class( new OrganizationStruct() ) );
        $stmt->execute( array( $user->uid, $id ) ) ;

        return static::resultOrNull( $stmt->fetch() );
    }

    /**
     * @param $id_organization
     *
     * @return \DataAccess_IDaoStruct|\DataAccess_IDaoStruct[]|MembershipStruct[]
     */
    public function getMemberListByOrganizationId( $id_organization ){
        $stmt = $this->_getStatementForCache( self::$_query_member_list );
        $membershipStruct = new MembershipStruct();

        /**
         * @var $members MembershipStruct[]
         */
        $members = $this->_fetchObject( $stmt,
                $membershipStruct,
                array(
                        'id_organization' => $id_organization,
                )
        );

        foreach( $members as $member ) {
            $member->setUser( ( new Users_UserDao())->setCacheTTL( 60 * 10 )->getByUid( $member->uid ) );
        }

        return $members ;
    }


    /**
     * Destroy cache for @see MembershipDao::getMemberListByOrganizationId()
     *
     * @param $id_organization
     *
     * @return bool|int
     */
    public function destroyCacheForListByOrganizationId( $id_organization ) {
        $stmt = $this->_getStatementForCache( self::$_query_member_list );
        return $this->_destroyObjectCache( $stmt,
                array(
                        'id_organization' => $id_organization,
                )
        );
    }

    /**
     * @param $uid
     * @param $organizationId
     *
     * @return \Users_UserStruct|null
     */
    public function deleteUserFromOrganization( $uid, $organizationId ) {
        $user = ( new Users_UserDao()) ->setCacheTTL(3600)->getByUid( $uid ) ;

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( self::$_delete_member );
        $stmt->execute( [
                'uid' => $uid,
                'id_organization' => $organizationId
        ] );

        $this->destroyCacheForListByOrganizationId( $organizationId );
        $this->destroyCacheUserOrganizations( $user );
        if ( $stmt->rowCount() ) {
            return $user ;
        }
        else {
            return null ;
        }
    }


    /**
     * This method takes a list of email addresses as argument.
     * If email corresponds to existing users, a membership is created into the organization.
     *
     * @param [
     *            'organization' => organizationStruct,
     *            'members'      => emails[]
     *        ] $obj_arr
     *
     * @return MembershipStruct[]
     */
    public function createList( Array $obj_arr ) {
        $obj_arr = \Utils::ensure_keys($obj_arr, array('members', 'organization') );

        $users = ( new Users_UserDao )->getByEmails( $obj_arr[ 'members' ] );
        if ( empty( $users ) ) return array();

        $organizationStruct = $obj_arr[ 'organization' ];

        $membersList = [];

        foreach ( $users as $user ) {
            // try to make an insert and ignore pkey errors
            $membershipStruct = ( new MembershipStruct( [
                    'id_organization' => $organizationStruct->id,
                    'uid'             => $user->uid,
                    'is_admin'        => ( $organizationStruct->created_by == $user->uid ? true : false )
            ] ) ) ;

            $lastId = self::insertStruct( $membershipStruct, ['ignore' => true ] )  ;

            if ( $lastId ) {
                $membershipStruct->id = $lastId ;
                $membershipStruct->setUser( $user ) ;
                $membersList[] = $membershipStruct ;

                $this->destroyCacheUserOrganizations( $user ) ;
            }
        }

        if ( count( $membersList ) ) {
            $this->destroyCacheForListByOrganizationId( $organizationStruct->id ) ;
        }

        return $membersList;
    }

}