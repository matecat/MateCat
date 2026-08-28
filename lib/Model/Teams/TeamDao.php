<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:04
 */

namespace Model\Teams;

use DomainException;
use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\InvalidatesUserProfileCache;
use Model\Users\UserStruct;
use PDO;
use PDOException;
use ReflectionException;
use Throwable;
use TypeError;
use Utils\Constants\Teams;
use Utils\Tools\Utils;

class TeamDao extends AbstractDao
{

    use InvalidatesUserProfileCache;

    const string TABLE = "teams";
    const string STRUCT_TYPE = TeamStruct::class;

    protected static array $auto_increment_field = ['id'];
    protected static array $primary_keys = ['id'];

    protected static string $_query_get_personal_by_id = " SELECT * FROM teams WHERE created_by = :created_by AND `type` = :type ";
    protected static string $_query_get_user_teams = " SELECT * FROM teams WHERE created_by = :created_by ";
    protected static string $_update_team_by_id = " UPDATE teams SET name = :name WHERE id = :id ";

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
     * @throws ReflectionException
     * @throws Exception
     */
    public function findById(int $id): ?TeamStruct
    {
        /** @var ?TeamStruct */
        return $this->fetchById($id, TeamStruct::class);
    }

    /**
     * Delete a team
     *
     * @param TeamStruct $teamStruct
     *
     * @return int
     * @throws PDOException
     */
    public function delete(TeamStruct $teamStruct): int
    {
        $sql = " DELETE FROM teams WHERE id = ? ";
        $stmt = $this->getDatabaseHandler()->getConnection()->prepare($sql);
        $stmt->execute([$teamStruct->id]);

        return $stmt->rowCount();
    }

    /**
     * @param UserStruct $user
     *
     * @return TeamStruct
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function createPersonalTeam(UserStruct $user): TeamStruct
    {
        return $this->createUserTeam($user, [
            'name' => 'Personal',
            'type' => Teams::PERSONAL
        ]);
    }

    /**
     * @param UserStruct $orgCreatorUser
     * @param array{name: string, type: string, members?: array<int, string|null>} $params
     *
     * @return  TeamStruct
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws Throwable the write runs inside a transaction scope, which aborts the transaction on
     *                   any throw and re-throws the original, whatever its type
     */
    public function createUserTeam(UserStruct $orgCreatorUser, array $params): TeamStruct
    {
        $teamStruct = new TeamStruct([
            'name' => $params['name'],
            'created_by' => $orgCreatorUser->uid,
            'created_at' => Utils::mysqlTimestamp(time()),
            'type' => $params['type']
        ]);

        $orgId = $this->insertStruct($teamStruct);
        $teamStruct->id = (int)$orgId;


        //add the creator to the list of members
        $params['members'][] = $orgCreatorUser->email;

        // addMembersByEmail() writes one membership row per member, so it runs in a scope. The scope
        // also undoes a partial list here rather than leaving it to the end of the request: a worker
        // holds its connection across messages, so a transaction left open by a failure here would
        // still be open when the next message starts writing. Entered while the caller already holds
        // a transaction it is a guest and closes nothing.
        $this->database->transaction(function () use ($teamStruct, $params): void {
            /** @var list<string> $members */
            $members = array_values(array_filter($params['members'], fn($member) => $member !== null));

            $membersList = (new MembershipDao($this->database))->addMembersByEmail($teamStruct, $members);
            $teamStruct->setMembers($membersList);
        });

        return $teamStruct;
    }

    /**
     * @param TeamStruct $team
     *
     * @return MembershipStruct[]
     * @throws ReflectionException
     * @throws Exception
     */
    public function getAssigneeWithProjectsByTeam(TeamStruct $team): array
    {
        $stmt = $this->_getStatementForQuery(self::$_query_get_assignee_with_projects);

        return $this->_fetchObjectMap(
            $stmt,
            MembershipStruct::class,
            [
                'id_team' => $team->id,
            ]
        );
    }

    /**
     * The one way in from outside: a caller names the team it already holds, and every Redis-cached
     * read that could still serve the row as it stood before the write is dropped.
     *
     * The row is cached under three addresses - its id, and, for the two reads that answer "which
     * team did this user create", the creator with and without the personal type. Both halves are
     * demanded: a struct built from an id alone would leave the creator-keyed entries publishing the
     * row as it stood, which is the failure the door exists to make impossible.
     *
     * The assignee aggregate is not here. It is keyed on the team but caches rows of the projects
     * table, so it goes stale when a project moves, not when this row changes: see
     * {@see destroyCacheAssigneeWithProjectsByTeam()}, which the caller that moved the project calls.
     *
     * @throws ReflectionException
     * @throws PDOException
     * @throws TypeError
     */
    public function destroyCache(TeamStruct $team): void
    {
        $this->destroyFetchByIdCache(
            $team->id ?? throw new TypeError('TeamStruct::$id cannot be null'),
            TeamStruct::class
        );

        if (!isset($team->created_by)) {
            throw new TypeError('TeamStruct::$created_by must be set');
        }

        $this->destroyCachePersonalByUid($team->created_by);
        $this->destroyCacheUserCreatedTeams($team->created_by);
    }

    /**
     * The per-assignee project counts of one team, which the team page reads.
     *
     * Public, and outside {@see destroyCache()}, because it is the projects table that is cached
     * here: the counts change when a project is created, reassigned or moved between teams, none of
     * which touches the teams row. The caller that moved the project is the only one that knows.
     *
     * @param TeamStruct $team
     *
     * @return bool
     * @throws ReflectionException
     * @throws PDOException
     * @throws Exception
     */
    public function destroyCacheAssigneeWithProjectsByTeam(TeamStruct $team): bool
    {
        $stmt = $this->_getStatementForQuery(self::$_query_get_assignee_with_projects);

        $destroyed = $this->_destroyObjectCache(
            $stmt,
            MembershipStruct::class,
            [
                'id_team' => $team->id,
            ]
        );

        // The per-member project counts this query feeds are rendered into every member's profile
        // (Membership::renderItem()'s `projects`), so moving or reassigning a project makes all of
        // them stale. ProjectModel already calls this for both the old and the new team, so hooking
        // here inherits that fan-out instead of duplicating it — the same reasoning as
        // MembershipDao::destroyCacheUserTeams().
        foreach ((new MembershipDao($this->database))->getMemberListByTeamId((int)$team->id, false) as $member) {
            if ($member->uid !== null) {
                $this->invalidateUserProfileCache($member->uid);
            }
        }

        return $destroyed;
    }

    /**
     * @param UserStruct $user
     *
     * @return TeamStruct
     * @throws ReflectionException
     * @throws Exception
     */
    public function getPersonalByUser(UserStruct $user): TeamStruct
    {
        $uid = $user->uid ?? throw new DomainException("User UID must not be null");

        return $this->getPersonalByUid($uid);
    }

    /**
     * @param int $uid
     *
     * @return TeamStruct
     * @throws ReflectionException
     * @throws Exception
     */
    public function getPersonalByUid(int $uid): TeamStruct
    {
        $stmt = $this->_getStatementForQuery(self::$_query_get_personal_by_id);

        /**
         * @var TeamStruct
         */
        return $this->_fetchObjectMap(
            $stmt,
            TeamStruct::class,
            [
                'created_by' => $uid,
                'type' => Teams::PERSONAL
            ]
        )[0];
    }

    /**
     * @param int $uid
     *
     * @return bool
     * @throws ReflectionException
     * @throws PDOException
     */
    private function destroyCachePersonalByUid(int $uid): bool
    {
        $stmt = $this->_getStatementForQuery(self::$_query_get_personal_by_id);

        return $this->_destroyObjectCache(
            $stmt,
            TeamStruct::class,
            [
                'created_by' => $uid,
                'type' => Teams::PERSONAL
            ]
        );
    }

    /**
     * @param UserStruct $user
     *
     * @return TeamStruct|null
     * @throws ReflectionException
     * @throws Exception
     */
    public function findUserCreatedTeams(UserStruct $user): ?TeamStruct
    {
        $stmt = $this->_getStatementForQuery(self::$_query_get_user_teams);

        return $this->_fetchObjectMap(
            $stmt,
            TeamStruct::class,
            [
                'created_by' => $user->uid,
            ]
        )[0] ?? null;
    }

    /**
     * @param int $uid
     *
     * @return bool
     * @throws ReflectionException
     * @throws PDOException
     */
    private function destroyCacheUserCreatedTeams(int $uid): bool
    {
        $stmt = $this->_getStatementForQuery(self::$_query_get_user_teams);

        return $this->_destroyObjectCache(
            $stmt,
            TeamStruct::class,
            [
                'created_by' => $uid,
            ]
        );
    }

    /**
     * @param TeamStruct $org
     *
     * @return TeamStruct
     * @throws PDOException
     * @throws Throwable
     */
    public function updateTeamName(TeamStruct $org): TeamStruct
    {
        $this->database->transaction(function () use ($org): void {
            $conn = $this->database->getConnection();

            $stmt = $conn->prepare(self::$_update_team_by_id);
            $stmt->bindValue(':id', $org->id, PDO::PARAM_INT);
            $stmt->bindValue(':name', $org->name);

            $stmt->execute();

            // The row is cached under `TeamDao::fetchById-<id>` for a day at a time, and a rename
            // that does not clear it is invisible to every reader that goes through the cache rather
            // than through the caller's own struct. That is how a renamed team kept announcing its
            // old name by email: MembershipStruct::getTeam() resolves the team with a 24-hour TTL,
            // so adding an existing user to a just-renamed team sent MembershipCreatedEmail carrying
            // the previous name — while inviting a new address, which is handed the struct the
            // controller fetched live, carried the new one. Evicting here rather than in the
            // controller keeps it true for every caller of this method, not just the one that was
            // noticed.
            $this->destroyCache($org);
        });

        return $org;
    }

    /**
     * @param TeamStruct $team
     *
     * @return int
     * @throws PDOException
     */
    public function deleteTeam(TeamStruct $team): int
    {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(static::$_sql_delete_empty_team);
        $stmt->execute([
            'id_team' => $team->id
        ]);

        return $stmt->rowCount();
    }

}
