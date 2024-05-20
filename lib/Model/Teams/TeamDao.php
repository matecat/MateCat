<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:04
 */

namespace Teams;

use Constants_Teams;
use Database;
use PDO;
use Users_UserStruct;
use Utils;

class TeamDao extends \DataAccess_AbstractDao {

    const TABLE       = "teams";
    const STRUCT_TYPE = "TeamStruct";

    protected static $auto_increment_field = array( 'id' );
    protected static $primary_keys         = array( 'id' );

    protected static $_query_find_by_id         = " SELECT * FROM teams WHERE id = :id ";
    protected static $_query_get_personal_by_id = " SELECT * FROM teams WHERE created_by = :created_by AND `type` = :type ";
    protected static $_query_get_user_teams     = " SELECT * FROM teams WHERE created_by = :created_by ";
    protected static $_update_team_by_id        = " UPDATE teams SET name = :name WHERE id = :id ";

    protected static $_query_get_assignee_with_projects = "
        SELECT COUNT(1) AS projects, id_assignee AS uid
        FROM projects 
        WHERE 
        id_team = :id_team
        GROUP BY id_assignee;
    ";

    protected static $_sql_delete_empty_team = "
        DELETE FROM teams 
        WHERE id = :id_team and type != 'personal' 
    ";

    /**
     * Delete a team
     *
     * @param TeamStruct $teamStruct
     *
     * @return int|void
     */
    public function delete( TeamStruct $teamStruct ) {
        $sql  = " DELETE FROM teams WHERE id = ? ";
        $stmt = $this->getDatabaseHandler()->getConnection()->prepare( $sql );
        $stmt->execute( array( $teamStruct->id ) );

        return $stmt->rowCount();
    }

    /**
     * @param $id
     *
     * @return \DataAccess_IDaoStruct|\DataAccess_IDaoStruct[]|TeamStruct
     */
    public function findById( $id ) {

        $stmt          = $this->_getStatementForCache( self::$_query_find_by_id );
        $teamQuery     = new TeamStruct();
        $teamQuery->id = $id;

        return $this->_fetchObject( $stmt,
                $teamQuery,
                array(
                        'id' => $teamQuery->id,
                )
        )[ 0 ];

    }

    /**
     * @param Users_UserStruct $user
     *
     * @return TeamStruct
     */
    public function createPersonalTeam( Users_UserStruct $user ) {
        return $this->createUserTeam( $user, array(
                'name' => 'Personal',
                'type' => Constants_Teams::PERSONAL
        ) );
    }

    /**
     * @param Users_UserStruct $orgCreatorUser
     * @param array            $params
     *
     * @return  TeamStruct
     */
    public function createUserTeam( Users_UserStruct $orgCreatorUser, $params = array() ) {

        $teamStruct = new TeamStruct( array(
                'name'       => $params[ 'name' ],
                'created_by' => $orgCreatorUser->uid,
                'created_at' => Utils::mysqlTimestamp( time() ),
                'type'       => $params[ 'type' ]
        ) );

        $orgId          = TeamDao::insertStruct( $teamStruct );
        $teamStruct->id = $orgId;


        //add the creator to the list of members
        $params[ 'members' ][] = $orgCreatorUser->email;

        // wrap createList() in a transaction
        if(false === Database::obtain()->getConnection()->inTransaction()){
            Database::obtain()->getConnection()->beginTransaction();
        }

        // get fresh cache from the master database
        ( new TeamDao() )->setCacheTTL( 60 * 60 * 24 )->findById( $teamStruct->id );

        $membersList = ( new MembershipDao )->createList( [
                'team'    => $teamStruct,
                'members' => $params[ 'members' ]
        ] );
        $teamStruct->setMembers( $membersList );

        if(false === Database::obtain()->getConnection()->inTransaction()){
            Database::obtain()->getConnection()->commit();
        }

        return $teamStruct;

    }

    /**
     * @param TeamStruct $team
     *
     * @return \DataAccess_IDaoStruct[]|MembershipStruct[]
     */
    public function getAssigneeWithProjectsByTeam( TeamStruct $team ){

        $stmt = $this->_getStatementForCache( self::$_query_get_assignee_with_projects );
        return $this->_fetchObject( $stmt,
                new MembershipStruct(),
                array(
                        'id_team' => $team->id,
                )
        );

    }

    /**
     * @param TeamStruct $team
     *
     * @return bool|int
     */
    public function destroyCacheAssignee( TeamStruct $team ){
        $stmt = $this->_getStatementForCache( self::$_query_get_assignee_with_projects );
        return $this->_destroyObjectCache( $stmt,
                array(
                        'id_team' => $team->id,
                )
        );
    }

    public function destroyCacheById( $id ) {
        $stmt          = $this->_getStatementForCache( self::$_query_find_by_id );
        $teamQuery     = new TeamStruct();
        $teamQuery->id = $id;

        return $this->_destroyObjectCache( $stmt,
                array(
                        'id' => $teamQuery->id,
                )
        );
    }

    public function getPersonalByUser( Users_UserStruct $user ) {
        return $this->getPersonalByUid( $user->uid );
    }

    public function getPersonalByUid( $uid ) {
        $stmt                  = $this->_getStatementForCache( self::$_query_get_personal_by_id );
        $teamQuery             = new TeamStruct();
        $teamQuery->created_by = $uid;

        return $this->_fetchObject( $stmt,
                $teamQuery,
                array(
                        'created_by' => $teamQuery->created_by,
                        'type'       => Constants_Teams::PERSONAL
                )
        )[ 0 ];
    }

    public function destroyCachePersonalByUid( $uid ) {
        $stmt                  = $this->_getStatementForCache( self::$_query_get_personal_by_id );
        $teamQuery             = new TeamStruct();
        $teamQuery->created_by = $uid;

        return $this->_destroyObjectCache( $stmt,
                array(
                        'created_by' => $teamQuery->created_by,
                        'type'       => Constants_Teams::PERSONAL
                )
        );
    }

    public function findUserCreatedTeams( \Users_UserStruct $user ) {

        $stmt = $this->_getStatementForCache( self::$_query_get_user_teams );

        $teamQuery             = new TeamStruct();
        $teamQuery->created_by = $user->uid;

        return static::resultOrNull( $this->_fetchObject( $stmt,
                $teamQuery,
                array(
                        'created_by' => $teamQuery->created_by,
                )
        )[ 0 ] );

    }

    public function destroyCacheUserCreatedTeams( \Users_UserStruct $user ) {
        $stmt = $this->_getStatementForCache( self::$_query_get_user_teams );

        $teamQuery             = new TeamStruct();
        $teamQuery->created_by = $user->uid;

        return $this->_destroyObjectCache( $stmt,
                array(
                        'created_by' => $teamQuery->created_by,
                )
        );
    }

    public function updateTeamName( TeamStruct $org ) {
        Database::obtain()->begin();
        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( self::$_update_team_by_id );
        $stmt->bindValue( ':id', $org->id, PDO::PARAM_INT );
        $stmt->bindValue( ':name', $org->name, PDO::PARAM_STR );

        $stmt->execute();
        $conn->commit();

        return $org;
    }

    /**
     * @param TeamStruct $team
     *
     * @return int
     */
    public function deleteTeam( TeamStruct $team ){
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( static::$_sql_delete_empty_team ) ;
        $stmt->execute( [
                'id_team'       => $team->id
        ] );

        return $stmt->rowCount();
    }

}