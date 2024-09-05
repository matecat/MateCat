<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:45
 */

namespace Teams;

use Database;
use Exception;
use PDO;
use ReflectionException;
use Users\MetadataDao;
use Users_UserDao;
use Users_UserStruct;
use Utils;

class MembershipDao extends \DataAccess_AbstractDao {

    const TABLE       = "teams_users";
    const STRUCT_TYPE = "\\Teams\\MembershipStruct";

    protected static $auto_increment_field = [ 'id' ];
    protected static $primary_keys         = [ 'id' ];

    protected static $_query_team_from_uid_and_id = " SELECT teams.* FROM teams
              JOIN teams_users ON teams_users.id_team = teams.id
            WHERE teams_users.uid = ? AND teams.id = ?
            ";

    protected static $_query_team_from_id_and_name = " SELECT teams.* FROM teams
              JOIN teams_users ON teams_users.id_team = teams.id
            WHERE teams.id = ? AND teams.name = ?
            ";

    protected static $_query_user_teams = " 
          SELECT teams.* FROM teams
              JOIN teams_users ON teams_users.id_team = teams.id
            WHERE teams_users.uid = :uid 
    ";

    protected static $_query_member_list = "
          SELECT ou.id, ou.id_team, ou.uid, ou.is_admin
          FROM teams_users ou
          WHERE ou.id_team = :id_team
    ";

    protected static $_delete_member = "
        DELETE FROM teams_users WHERE uid = :uid AND id_team = :id_team
    ";

    public function findById( $id ) {
        $sql  = " SELECT * FROM " . self::TABLE . " WHERE id = ? ";
        $stmt = $this->getDatabaseHandler()->getConnection()->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, self::STRUCT_TYPE );
        $stmt->execute( [ $id ] );

        return $stmt->fetch();
    }

    /**
     * Find ONE team for the given user. This is to enforce the temporary requirement to
     * have just one team per user.
     *
     * @param Users_UserStruct $user
     *
     * @return null|TeamStruct[]
     * @throws ReflectionException
     */
    public function findUserTeams( Users_UserStruct $user ) {

        $stmt      = $this->_getStatementForQuery( self::$_query_user_teams );
        $teamQuery = new TeamStruct();

        return static::resultOrNull( $this->_fetchObject( $stmt,
                $teamQuery,
                [
                        'uid' => $user->uid,
                ]
        ) );

    }

    /**
     * Cache deletion for @param Users_UserStruct $user
     *
     * @return bool|int
     * @throws ReflectionException
     * @see MembershipDao::findUserTeams
     *
     */
    public function destroyCacheUserTeams( Users_UserStruct $user ) {
        $stmt = $this->_getStatementForQuery( self::$_query_user_teams );

        return $this->_destroyObjectCache( $stmt,
                [
                        'uid' => $user->uid,
                ]
        );
    }

    /**
     * Finds an team in user scope.
     *
     * @param int $id
     * @param Users_UserStruct $user
     *
     * @return null|TeamStruct
     * @throws ReflectionException
     */
    public function findTeamByIdAndUser( $id, Users_UserStruct $user ) {
        $stmt = $this->_getStatementForQuery( self::$_query_team_from_uid_and_id );

        return static::resultOrNull( $this->_fetchObject( $stmt, ( new TeamStruct() ), [ $user->uid, $id ] )[ 0 ] );
    }

    /**
     * @param $id
     * @param $name
     * @param Users_UserStruct $user
     * @return mixed|null
     * @throws ReflectionException
     */
    public function findTeamByIdAndName($id, $name) {
        $stmt = $this->_getStatementForQuery( self::$_query_team_from_id_and_name );

        return static::resultOrNull( $this->_fetchObject( $stmt, ( new TeamStruct() ), [ $id, $name ] )[ 0 ] );
    }

    /**
     * Cache deletion for @param int $id
     *
     * @param Users_UserStruct $user
     *
     * @return bool|int
     * @throws ReflectionException
     * @see MembershipDao::findTeamByIdAndUser
     *
     */
    public function destroyCacheTeamByIdAndUser( $id, Users_UserStruct $user ) {
        $stmt = $this->_getStatementForQuery( self::$_query_team_from_uid_and_id );

        return $this->_destroyObjectCache( $stmt, [ $user->uid, $id ] );
    }

    /**
     * @param $id_team
     * @param $traverse
     *
     * @return \DataAccess_IDaoStruct[]|MembershipStruct[]
     * @throws ReflectionException
     */
    public function getMemberListByTeamId( $id_team, $traverse = true ) {
        $stmt             = $this->_getStatementForQuery( self::$_query_member_list );
        $membershipStruct = new MembershipStruct();

        /**
         * @var $members MembershipStruct[]
         */
        $members = $this->_fetchObject( $stmt,
                $membershipStruct,
                [
                        'id_team' => $id_team,
                ]
        );

        if ( $traverse ) {

            $membersUIDs = [];
            foreach ( $members as $member ) {
                $membersUIDs[] = $member->uid;
            }

            $users    = ( new Users_UserDao() )->setCacheTTL( 60 * 60 * 24 )->getByUids( $membersUIDs );
            $metadata = ( new MetadataDao() )->setCacheTTL( 60 * 60 * 24 )->getAllByUidList( $membersUIDs );

            foreach ( $members as $member ) {
                $member->setUser( $users[ $member->uid ] );

                if ( isset( $metadata[ $member->uid ] ) and is_array( $metadata[ $member->uid ] ) ) {
                    $member->setUserMetadata( $metadata[ $member->uid ] );
                }
            }

        }

        return $members;
    }


    /**
     * Destroy cache for @param $id_team
     *
     * @return bool|int
     * @see MembershipDao::getMemberListByTeamId()
     *
     */
    public function destroyCacheForListByTeamId( $id_team ) {
        $stmt = $this->_getStatementForQuery( self::$_query_member_list );

        return $this->_destroyObjectCache( $stmt,
                [
                        'id_team' => $id_team,
                ]
        );
    }

    /**
     * @param $uid
     * @param $teamId
     *
     * @return Users_UserStruct|null
     */
    public function deleteUserFromTeam( $uid, $teamId ) {
        $user = ( new Users_UserDao() )->setCacheTTL( 3600 )->getByUid( $uid );

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( self::$_delete_member );
        $stmt->execute( [
                'uid'     => $uid,
                'id_team' => $teamId
        ] );

        $this->destroyCacheForListByTeamId( $teamId );
        $this->destroyCacheUserTeams( $user );
        if ( $stmt->rowCount() ) {
            return $user;
        } else {
            return null;
        }
    }


    /**
     * This method takes a list of email addresses as argument.
     * If email corresponds to existing users, a membership is created into the team.
     *
     * @param array [
     *            'team'     => TeamStruct,
     *            'members'  => emails[]
     *            ] $obj_arr
     *
     * @return MembershipStruct[]
     * @throws Exception
     */
    public function createList( array $obj_arr ) {

        if ( !Database::obtain()->getConnection()->inTransaction() ) {
            throw new Exception( 'this method requires to be wrapped in a transaction' );
        }

        $obj_arr = Utils::ensure_keys( $obj_arr, [ 'members', 'team' ] );

        $users = ( new Users_UserDao )->getByEmails( $obj_arr[ 'members' ] );

        if ( empty( $users ) ) {
            return [];
        }

        $teamStruct = $obj_arr[ 'team' ];

        $membersList = [];

        foreach ( $users as $user ) {
            // try to make an insert and ignore pkey errors
            $membershipStruct = ( new MembershipStruct( [
                    'id_team'  => $teamStruct->id,
                    'uid'      => $user->uid,
                    'is_admin' => ( $teamStruct->created_by == $user->uid ? true : false )
            ] ) );

            $lastId = self::insertStruct( $membershipStruct, [ 'ignore' => true ] );

            if ( $lastId ) {
                $membershipStruct->id = $lastId;
                $membershipStruct->setUser( $user );
                $membersList[] = $membershipStruct;

                $this->destroyCacheUserTeams( $user );
            }
        }

        if ( count( $membersList ) ) {
            $this->destroyCacheForListByTeamId( $teamStruct->id );
        }

        return $membersList;
    }

}