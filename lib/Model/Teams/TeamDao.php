<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:04
 */

namespace Model\Teams;

use Constants_Teams;
use Exception;
use Model\DataAccess\AbstractDao;
use Model\Database;
use Model\Users\UserStruct;
use PDO;
use ReflectionException;
use Utils;

class TeamDao extends AbstractDao {

    const TABLE       = "teams";
    const STRUCT_TYPE = "TeamStruct";

    protected static array $auto_increment_field = [ 'id' ];
    protected static array $primary_keys         = [ 'id' ];

    protected static string $_query_find_by_id         = " SELECT * FROM teams WHERE id = :id ";
    protected static string $_query_get_personal_by_id = " SELECT * FROM teams WHERE created_by = :created_by AND `type` = :type ";
    protected static string $_query_get_user_teams     = " SELECT * FROM teams WHERE created_by = :created_by ";
    protected static string $_update_team_by_id        = " UPDATE teams SET name = :name WHERE id = :id ";

    protected static string $_query_get_assignee_with_projects = "
        SELECT COUNT(1) AS projects, id_assignee AS uid
        FROM projects 
        WHERE 
        id_team = :id_team
        GROUP BY id_assignee;
    ";

    protected static string $_sql_delete_empty_team = "
        DELETE FROM teams 
        WHERE id = :id_team and type != 'personal' 
    ";

    /**
     * Delete a team
     *
     * @param TeamStruct $teamStruct
     *
     * @return int
     */
    public function delete( TeamStruct $teamStruct ): int {
        $sql  = " DELETE FROM teams WHERE id = ? ";
        $stmt = $this->getDatabaseHandler()->getConnection()->prepare( $sql );
        $stmt->execute( [ $teamStruct->id ] );

        return $stmt->rowCount();
    }

    /**
     * @param $id
     *
     * @return TeamStruct
     * @throws ReflectionException
     */
    public function findById( $id ): ?TeamStruct {

        $stmt          = $this->_getStatementForQuery( self::$_query_find_by_id );

        /** @var $res TeamStruct */
        $res = $this->_fetchObjectMap( $stmt,
                TeamStruct::class,
                [
                        'id' => $id,
                ]
        )[ 0 ] ?? null;

        return $res;

    }

    /**
     * @param UserStruct $user
     *
     * @return TeamStruct
     * @throws ReflectionException
     */
    public function createPersonalTeam( UserStruct $user ): TeamStruct {
        return $this->createUserTeam( $user, [
                'name' => 'Personal',
                'type' => Constants_Teams::PERSONAL
        ] );
    }

    /**
     * @param UserStruct $orgCreatorUser
     * @param array      $params
     *
     * @return  TeamStruct
     * @throws ReflectionException
     * @throws Exception
     */
    public function createUserTeam( UserStruct $orgCreatorUser, array $params = [] ): TeamStruct {

        $teamStruct = new TeamStruct( [
                'name'       => $params[ 'name' ],
                'created_by' => $orgCreatorUser->uid,
                'created_at' => Utils::mysqlTimestamp( time() ),
                'type'       => $params[ 'type' ]
        ] );

        $orgId          = TeamDao::insertStruct( $teamStruct );
        $teamStruct->id = $orgId;


        //add the creator to the list of members
        $params[ 'members' ][] = $orgCreatorUser->email;

        // wrap createList() in a transaction
        if ( false === Database::obtain()->getConnection()->inTransaction() ) {
            Database::obtain()->getConnection()->beginTransaction();
        }

        // get fresh cache from the master database
        ( new TeamDao() )->setCacheTTL( 60 * 60 * 24 )->findById( $teamStruct->id );

        $membersList = ( new MembershipDao )->createList( [
                'team'    => $teamStruct,
                'members' => $params[ 'members' ]
        ] );
        $teamStruct->setMembers( $membersList );

        if ( false === Database::obtain()->getConnection()->inTransaction() ) {
            Database::obtain()->getConnection()->commit();
        }

        return $teamStruct;

    }

    /**
     * @param TeamStruct $team
     *
     * @return MembershipStruct[]
     * @throws ReflectionException
     */
    public function getAssigneeWithProjectsByTeam( TeamStruct $team ): array {

        $stmt = $this->_getStatementForQuery( self::$_query_get_assignee_with_projects );

        return $this->_fetchObjectMap( $stmt,
                MembershipStruct::class,
                [
                        'id_team' => $team->id,
                ]
        );

    }

    /**
     * @param TeamStruct $team
     *
     * @return bool
     * @throws ReflectionException
     */
    public function destroyCacheAssignee( TeamStruct $team ): bool {
        $stmt = $this->_getStatementForQuery( self::$_query_get_assignee_with_projects );

        return $this->_destroyObjectCache( $stmt,
                MembershipStruct::class,
                [
                        'id_team' => $team->id,
                ]
        );
    }

    /**
     * @param int $id
     *
     * @return bool
     * @throws ReflectionException
     */
    public function destroyCacheById( int $id ): bool {
        $stmt          = $this->_getStatementForQuery( self::$_query_find_by_id );
        $teamQuery     = new TeamStruct();
        $teamQuery->id = $id;

        return $this->_destroyObjectCache( $stmt,
                TeamStruct::class,
                [
                        'id' => $teamQuery->id,
                ]
        );
    }

    /**
     * @param UserStruct $user
     *
     * @return TeamStruct
     * @throws ReflectionException
     */
    public function getPersonalByUser( UserStruct $user ): TeamStruct {
        return $this->getPersonalByUid( $user->uid );
    }

    /**
     * @param int $uid
     *
     * @return TeamStruct
     * @throws ReflectionException
     */
    public function getPersonalByUid( int $uid ): TeamStruct {
        $stmt                  = $this->_getStatementForQuery( self::$_query_get_personal_by_id );

        /**
         * @var TeamStruct
         */
        return $this->_fetchObjectMap( $stmt,
                TeamStruct::class,
                [
                        'created_by' => $uid,
                        'type'       => Constants_Teams::PERSONAL
                ]
        )[ 0 ];
    }

    /**
     * @param int $uid
     *
     * @return bool
     * @throws ReflectionException
     */
    public function destroyCachePersonalByUid( int $uid ): bool {
        $stmt                  = $this->_getStatementForQuery( self::$_query_get_personal_by_id );
        $teamQuery             = new TeamStruct();
        $teamQuery->created_by = $uid;

        return $this->_destroyObjectCache( $stmt,
                TeamStruct::class,
                [
                        'created_by' => $teamQuery->created_by,
                        'type'       => Constants_Teams::PERSONAL
                ]
        );
    }

    /**
     * @param UserStruct $user
     *
     * @return TeamStruct|null
     * @throws ReflectionException
     */
    public function findUserCreatedTeams( UserStruct $user ) {

        $stmt = $this->_getStatementForQuery( self::$_query_get_user_teams );

        return static::resultOrNull( $this->_fetchObjectMap( $stmt,
                TeamStruct::class,
                [
                        'created_by' => $user->uid,
                ]
        )[ 0 ] );

    }

    /**
     * @param UserStruct $user
     *
     * @return bool
     * @throws ReflectionException
     */
    public function destroyCacheUserCreatedTeams( UserStruct $user ) {
        $stmt = $this->_getStatementForQuery( self::$_query_get_user_teams );

        $teamQuery             = new TeamStruct();
        $teamQuery->created_by = $user->uid;

        return $this->_destroyObjectCache( $stmt,
                TeamStruct::class,
                [
                        'created_by' => $teamQuery->created_by,
                ]
        );
    }

    /**
     * @param TeamStruct $org
     *
     * @return TeamStruct
     */
    public function updateTeamName( TeamStruct $org ): TeamStruct {
        Database::obtain()->begin();
        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( self::$_update_team_by_id );
        $stmt->bindValue( ':id', $org->id, PDO::PARAM_INT );
        $stmt->bindValue( ':name', $org->name );

        $stmt->execute();
        $conn->commit();

        return $org;
    }

    /**
     * @param TeamStruct $team
     *
     * @return int
     */
    public function deleteTeam( TeamStruct $team ): int {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( static::$_sql_delete_empty_team );
        $stmt->execute( [
                'id_team' => $team->id
        ] );

        return $stmt->rowCount();
    }

}