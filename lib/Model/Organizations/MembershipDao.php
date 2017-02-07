<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:45
 */

namespace Organizations;

use PDO ;

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
     * @param MembershipStruct[] $obj_arr
     *
     * @return array
     */
    public function createList( Array $obj_arr ) {

        $arrayCount = count( $obj_arr );
        $rowCount = ( $arrayCount  ? $arrayCount - 1 : 0);

        $placeholders = sprintf( "(?,?,?)%s", str_repeat(",(?,?,?)", $rowCount ));
        $sql = "INSERT IGNORE INTO " . self::TABLE . " ( id_organization , uid, is_admin ) VALUES " . $placeholders;

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $values = [];
        foreach( $obj_arr as $membershipStruct ){
            $values[] = $membershipStruct->id_organization;
            $values[] = $membershipStruct->uid;
            $values[] = $membershipStruct->is_admin;
        }

        $stmt->execute( $values );

        $i = 0;
        foreach( $obj_arr as &$membershipStruct ){
            $membershipStruct[ 'id' ] = (int)$conn->lastInsertId() + $i;
            $i++;
        }

        return $obj_arr;


    }

}