<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:45
 */

namespace Model\Teams;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use Model\DataAccess\IDaoStruct;
use Model\Users\MetadataDao;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PDO;
use ReflectionException;
use Utils\Tools\Utils;

class MembershipDao extends AbstractDao {

    const TABLE       = "teams_users";
    const STRUCT_TYPE = "\\Teams\\MembershipStruct";

    protected static array $auto_increment_field = [ 'id' ];
    protected static array $primary_keys         = [ 'id' ];

    protected static string $_query_team_from_uid_and_id = " SELECT teams.* FROM teams
              JOIN teams_users ON teams_users.id_team = teams.id
            WHERE teams_users.uid = ? AND teams.id = ?
            ";

    protected static string $_query_team_from_id_and_name = " SELECT teams.* FROM teams
              JOIN teams_users ON teams_users.id_team = teams.id
            WHERE teams.id = ? AND teams.name = ?
            ";

    protected static string $_query_user_teams = " 
          SELECT teams.* FROM teams
              JOIN teams_users ON teams_users.id_team = teams.id
            WHERE teams_users.uid = :uid 
    ";

    protected static string $_query_member_list = "
          SELECT ou.id, ou.id_team, ou.uid, ou.is_admin
          FROM teams_users ou
          WHERE ou.id_team = :id_team
    ";

    protected static string $_delete_member = "
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
     * @param UserStruct $user
     *
     * @return null|TeamStruct[]
     * @throws ReflectionException
     */
    public function findUserTeams( UserStruct $user ): ?array {

        $stmt = $this->_getStatementForQuery( self::$_query_user_teams );

        return static::resultOrNull( $this->_fetchObjectMap( $stmt,
                TeamStruct::class,
                [
                        'uid' => $user->uid,
                ]
        ) );

    }

    /**
     * Cache deletion for @param UserStruct $user
     *
     * @return bool
     * @throws ReflectionException
     * @see MembershipDao::findUserTeams
     *
     */
    public function destroyCacheUserTeams( UserStruct $user ): bool {
        $stmt = $this->_getStatementForQuery( self::$_query_user_teams );

        return $this->_destroyObjectCache( $stmt,
                TeamStruct::class,
                [
                        'uid' => $user->uid,
                ]
        );
    }

    /**
     * Finds an team in user scope.
     *
     * @param int                     $id
     * @param \Model\Users\UserStruct $user
     *
     * @return null|TeamStruct
     * @throws ReflectionException
     */
    public function findTeamByIdAndUser( int $id, UserStruct $user ): ?TeamStruct {
        $stmt = $this->_getStatementForQuery( self::$_query_team_from_uid_and_id );

        return static::resultOrNull( $this->_fetchObjectMap( $stmt, TeamStruct::class, [ $user->uid, $id ] )[ 0 ] ?? null );
    }

    /**
     * @param int    $id
     * @param string $name
     *
     * @return TeamStruct|null
     * @throws ReflectionException
     */
    public function findTeamByIdAndName( int $id, string $name ): ?TeamStruct {
        $stmt = $this->_getStatementForQuery( self::$_query_team_from_id_and_name );

        return static::resultOrNull( $this->_fetchObjectMap( $stmt, TeamStruct::class, [ $id, $name ] )[ 0 ] );
    }

    /**
     * Cache deletion for @param int $id
     *
     * @param \Model\Users\UserStruct $user
     *
     * @return bool
     * @throws ReflectionException
     * @see MembershipDao::findTeamByIdAndUser
     *
     */
    public function destroyCacheTeamByIdAndUser( int $id, UserStruct $user ): bool {
        $stmt = $this->_getStatementForQuery( self::$_query_team_from_uid_and_id );

        return $this->_destroyObjectCache( $stmt, TeamStruct::class, [ $user->uid, $id ] );
    }

    /**
     * @param int  $id_team
     * @param bool $traverse
     *
     * @return IDaoStruct[]|MembershipStruct[]
     * @throws ReflectionException
     */
    public function getMemberListByTeamId( int $id_team, bool $traverse = true ): array {
        $stmt = $this->_getStatementForQuery( self::$_query_member_list );

        /**
         * @var $members MembershipStruct[]
         */
        $members = $this->_fetchObjectMap( $stmt,
                MembershipStruct::class,
                [
                        'id_team' => $id_team,
                ]
        );

        if ( $traverse ) {

            $membersUIDs = [];
            foreach ( $members as $member ) {
                $membersUIDs[] = $member->uid;
            }

            $users    = ( new UserDao() )->setCacheTTL( 60 * 60 * 24 )->getByUids( $membersUIDs );
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
     * Destroy cache for
     *
     * @param int $id_team
     *
     * @return bool
     * @throws ReflectionException  @see MembershipDao::getMemberListByTeamId()
     */
    public function destroyCacheForListByTeamId( int $id_team ): bool {
        $stmt = $this->_getStatementForQuery( self::$_query_member_list );

        return $this->_destroyObjectCache( $stmt,
                MembershipStruct::class,
                [
                        'id_team' => $id_team,
                ]
        );
    }

    /**
     * @param int $uid
     * @param int $teamId
     *
     * @return \Model\Users\UserStruct|null
     * @throws ReflectionException
     */
    public function deleteUserFromTeam( int $uid, int $teamId ): ?UserStruct {
        $user = ( new UserDao() )->setCacheTTL( 3600 )->getByUid( $uid );

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( self::$_delete_member );
        $stmt->execute( [
                'uid'     => $uid,
                'id_team' => $teamId
        ] );

        $this->destroyCacheForListByTeamId( $teamId );
        $this->destroyCacheUserTeams( $user );
        $this->destroyCacheTeamByIdAndUser( $teamId, $user );
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
     * @param $obj_arr array [
     *            'team'     => TeamStruct,
     *            'members'  => emails[]
     *            ]
     *
     * @return MembershipStruct[]
     * @throws Exception
     */
    public function createList( array $obj_arr ): array {

        if ( !Database::obtain()->getConnection()->inTransaction() ) {
            throw new Exception( 'this method requires to be wrapped in a transaction' );
        }

        $obj_arr = Utils::ensure_keys( $obj_arr, [ 'members', 'team' ] );

        $users = ( new UserDao )->getByEmails( $obj_arr[ 'members' ] );

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
                    'is_admin' => $teamStruct->created_by == $user->uid
            ] ) );

            $lastId = self::insertStruct( $membershipStruct, [ 'ignore' => true ] );

            if ( $lastId ) {
                $membershipStruct->id = $lastId;
                $membershipStruct->setUser( $user );
                $membersList[] = $membershipStruct;

                $this->destroyCacheUserTeams( $user );
                $this->destroyCacheTeamByIdAndUser( $teamStruct->id, $user );
            }
        }

        if ( count( $membersList ) ) {
            $this->destroyCacheForListByTeamId( $teamStruct->id );
        }

        return $membersList;
    }

}