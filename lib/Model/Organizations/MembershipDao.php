<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:45
 */

namespace Organizations;

use PDO ;
use Users_UserDao;

class MembershipDao extends \DataAccess_AbstractDao
{

    const TABLE = "organizations_users";
    const STRUCT_TYPE = "\\Organizations\\MembershipStruct";

    protected static $auto_increment_fields = array('id');
    protected static $primary_keys = array('id');

    protected static $_query_user_organizations = " 
          SELECT organizations.* FROM organizations
              JOIN organizations_users ON organizations_users.id_organization = organizations.id
            WHERE organizations_users.uid = :uid 
    ";

    protected static $_query_member_list = "
          SELECT ou.id, ou.id_organization, ou.uid, ou.is_admin, email, first_name, last_name FROM organizations_users ou
              JOIN users USING ( uid )
            WHERE ou.id_organization = :id_organization
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
        $sql = " SELECT organizations.* FROM organizations
              JOIN organizations_users ON organizations_users.id_organization = organizations.id
            WHERE organizations_users.uid = ? AND organizations.id = ?
            " ;

        $stmt = $this->getConnection()->getConnection()->prepare( $sql ) ;
        $stmt->setFetchMode( PDO::FETCH_CLASS, '\Organizations\OrganizationStruct' );
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
        return $this->_fetchObject( $stmt,
                $membershipStruct,
                array(
                        'id_organization' => $id_organization,
                )
        );
    }

    /**
     * @param [
     *            'organization' => organizationStruct,
     *            'members'      => emails[]
     *        ] $obj_arr
     *
     * @return array
     */
    public function createList( Array $obj_arr ) {

        $members = ( new Users_UserDao )->getByEmails( $obj_arr[ 'members' ] );
        $organizationStruct = $obj_arr[ 'organization' ];

        $membersList = [];
        foreach ( $members as $member ) {
            $_member             = ( new MembershipStruct( [
                    'id_organization' => $organizationStruct->id,
                    'uid'             => $member->uid,
                    'is_admin'        => ( $organizationStruct->created_by == $member->uid ? true : false )
            ] ) );
            $_member->email      = $member->email;
            $_member->first_name = $member->first_name;
            $_member->last_name  = $member->last_name;
            $membersList[]       = $_member;
        }

        $arrayCount = count( $membersList );
        $rowCount = ( $arrayCount  ? $arrayCount - 1 : 0);

        $placeholders = sprintf( "(?,?,?)%s", str_repeat(",(?,?,?)", $rowCount ));
        $sql = "INSERT IGNORE INTO " . self::TABLE . " ( id_organization , uid, is_admin ) VALUES " . $placeholders;

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $values = [];
        foreach( $membersList as $membershipStruct ){
            $values[] = $membershipStruct->id_organization;
            $values[] = $membershipStruct->uid;
            $values[] = $membershipStruct->is_admin;
        }

        $stmt->execute( $values );

        $i = 0; //emulate MySQL auto_increment
        foreach( $membersList as $membershipStruct ){
            $membershipStruct->id = (int)$conn->lastInsertId() + $i;
            $i++;
        }

        return $membersList;


    }

}