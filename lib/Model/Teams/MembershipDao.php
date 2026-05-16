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
use PDOException;
use ReflectionException;

class MembershipDao extends AbstractDao
{

    const string TABLE = "teams_users";
    const string STRUCT_TYPE = MembershipStruct::class;

    /** @var list<string> */
    protected static array $auto_increment_field = ['id'];
    /** @var list<string> */
    protected static array $primary_keys = ['id'];

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

    /**
     * Find ONE team for the given user. This is to enforce the temporary requirement to
     * have just one team per user.
     *
     * @param UserStruct $user
     *
     * @return null|TeamStruct[]
     * @throws ReflectionException
     * @throws Exception
     */
    public function findUserTeams(UserStruct $user): ?array
    {
        $stmt = $this->_getStatementForQuery(self::$_query_user_teams);

        return $this->_fetchObjectMap(
            $stmt,
            TeamStruct::class,
            [
                'uid' => $user->uid,
            ]
        );
    }

    /**
     * Cache deletion for @param UserStruct $user
     *
     * @return bool
     * @throws ReflectionException
     * @throws PDOException
     * @see MembershipDao::findUserTeams
     *
     */
    public function destroyCacheUserTeams(UserStruct $user): bool
    {
        $stmt = $this->_getStatementForQuery(self::$_query_user_teams);

        return $this->_destroyObjectCache(
            $stmt,
            TeamStruct::class,
            [
                'uid' => $user->uid,
            ]
        );
    }

    /**
     * Finds a team in user scope.
     *
     * @param int $id
     * @param UserStruct $user
     *
     * @return null|TeamStruct
     * @throws ReflectionException
     * @throws Exception
     */
    public function findTeamByIdAndUser(int $id, UserStruct $user): ?TeamStruct
    {
        $stmt = $this->_getStatementForQuery(self::$_query_team_from_uid_and_id);

        return $this->_fetchObjectMap($stmt, TeamStruct::class, [$user->uid, $id])[0] ?? null;
    }

    /**
     * @param int $id
     * @param string $name
     *
     * @return TeamStruct|null
     * @throws ReflectionException
     * @throws Exception
     */
    public function findTeamByIdAndName(int $id, string $name): ?TeamStruct
    {
        $stmt = $this->_getStatementForQuery(self::$_query_team_from_id_and_name);

        return $this->_fetchObjectMap($stmt, TeamStruct::class, [$id, $name])[0] ?? null;
    }

    /**
     * Cache deletion for @param int $id
     *
     * @param UserStruct $user
     *
     * @return bool
     * @throws ReflectionException
     * @throws PDOException
     * @see MembershipDao::findTeamByIdAndUser
     *
     */
    public function destroyCacheTeamByIdAndUser(int $id, UserStruct $user): bool
    {
        $stmt = $this->_getStatementForQuery(self::$_query_team_from_uid_and_id);

        return $this->_destroyObjectCache($stmt, TeamStruct::class, [$user->uid, $id]);
    }

    /**
     * @param int $id_team
     * @param bool $traverse
     *
     * @return MembershipStruct[]
     * @throws ReflectionException
     * @throws Exception
     */
    public function getMemberListByTeamId(int $id_team, bool $traverse = true): array
    {
        $stmt = $this->_getStatementForQuery(self::$_query_member_list);

        /** @var MembershipStruct[] $members */
        $members = $this->_fetchObjectMap(
            $stmt,
            MembershipStruct::class,
            [
                'id_team' => $id_team,
            ]
        );

        if ($traverse) {
            $membersUIDs = [];
            foreach ($members as $member) {
                $membersUIDs[] = $member->uid;
            }

            $memberUIDs = array_values(array_filter($membersUIDs, fn($v) => $v !== null));

            $users = (new UserDao())->setCacheTTL(60 * 60 * 24)->getByUids($memberUIDs);
            $metadata = (new MetadataDao())->setCacheTTL(60 * 60 * 24)->getAllByUidList($memberUIDs);

            foreach ($members as $member) {
                if ($member->uid !== null && isset($users[$member->uid])) {
                    $member->setUser($users[$member->uid]);
                }

                if ($member->uid !== null && isset($metadata[$member->uid]) and is_array($metadata[$member->uid])) {
                    $member->setUserMetadata($metadata[$member->uid]);
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
     * @throws PDOException
     * @throws ReflectionException  @see MembershipDao::getMemberListByTeamId()
     */
    public function destroyCacheForListByTeamId(int $id_team): bool
    {
        $stmt = $this->_getStatementForQuery(self::$_query_member_list);

        return $this->_destroyObjectCache(
            $stmt,
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
     * @return UserStruct|null
     * @throws ReflectionException
     * @throws Exception
     * @throws PDOException
     */
    public function deleteUserFromTeam(int $uid, int $teamId): ?UserStruct
    {
        $user = (new UserDao())->setCacheTTL(3600)->getByUid($uid);

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(self::$_delete_member);
        $stmt->execute([
            'uid' => $uid,
            'id_team' => $teamId
        ]);

        $this->destroyCacheForListByTeamId($teamId);
        if ($user !== null) {
            $this->destroyCacheUserTeams($user);
            $this->destroyCacheTeamByIdAndUser($teamId, $user);
        }
        if ($stmt->rowCount()) {
            return $user;
        } else {
            return null;
        }
    }


    /**
     * This method takes a list of email addresses as an argument.
     * If email corresponds to existing users, a membership is created into the team.
     *
     * @param array<int, IDaoStruct>|array{team: TeamStruct, members: list<string>} $obj_arr
     *
     *
     * @return MembershipStruct[]
     * @throws Exception
     */
    public function createList(array $obj_arr): array
    {
        if (!Database::obtain()->getConnection()->inTransaction()) {
            throw new Exception('this method requires to be wrapped in a transaction');
        }

        if (!isset($obj_arr['members'], $obj_arr['team'])) {
            throw new Exception('Missing required keys: members, team');
        }

        if (!is_array($obj_arr['members'])) {
            throw new Exception('members must be an array of email strings');
        }

        if (!$obj_arr['team'] instanceof TeamStruct) {
            throw new Exception('team must be a TeamStruct instance');
        }

        /** @var list<string> $members */
        $members = $obj_arr['members'];
        $teamStruct = $obj_arr['team'];

        $users = (new UserDao)->getByEmails($members);

        if (empty($users)) {
            return [];
        }

        $membersList = [];

        foreach ($users as $user) {
            // try to make an insert and ignore pkey errors
            $membershipStruct = (new MembershipStruct([
                'id_team' => $teamStruct->id,
                'uid' => $user->uid,
                'is_admin' => $teamStruct->created_by == $user->uid
            ]));

            $lastId = $this->insertStruct($membershipStruct, ['ignore' => true]);

            if ($lastId) {
                $membershipStruct->id = $lastId;
                $membershipStruct->setUser($user);
                $membersList[] = $membershipStruct;

                $this->destroyCacheUserTeams($user);
                if ($teamStruct->id !== null) {
                    $this->destroyCacheTeamByIdAndUser($teamStruct->id, $user);
                }
            }
        }

        if (count($membersList) && $teamStruct->id !== null) {
            $this->destroyCacheForListByTeamId($teamStruct->id);
        }

        return $membersList;
    }

}
