<?php

namespace Model\Projects;

use DateInterval;
use DateTime;
use Exception;
use Model\DataAccess\Database;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use PDO;
use ReflectionException;
use Utils\Constants\ProjectStatus;
use View\API\V2\Json\Project;

class ManageModel
{


    /**
     * @param                       $start                int
     * @param                       $step                 int
     * @param string|null           $search_in_pname      string
     * @param string|null           $search_source        string
     * @param string|null           $search_target        string
     * @param string|null           $search_status        string
     * @param bool|null             $search_only_completed
     * @param int|null              $project_id           int
     *
     * @param TeamStruct|null       $team
     *
     * @param UserStruct|null       $assignee
     * @param bool                  $no_assignee
     *
     * @return array
     */
    protected static function _getProjects(
            int $start,
            int $step,
            ?string $search_in_pname,
            ?string $search_source,
            ?string $search_target,
            ?string $search_status,
            ?bool $search_only_completed,
            ?int $project_id,
            ?TeamStruct $team = null,
            ?UserStruct $assignee = null,
            ?bool $no_assignee = false
    ): array {
        [$conditions, $data] = static::conditionsForProjectsQuery(
                $search_in_pname,
                $search_source,
                $search_target,
                $search_status,
                $search_only_completed
        );

        if ($project_id) {
            $conditions[]         = " p.id = :project_id ";
            $data[ 'project_id' ] = $project_id;
        }

        if (!is_null($team)) {
            $conditions[]       = " p.id_team = :id_team ";
            $data [ 'id_team' ] = $team->id;
        }

        if ($no_assignee) {
            $conditions[] = " p.id_assignee IS NULL ";
        } elseif (!is_null($assignee)) {
            $conditions[]           = " p.id_assignee = :id_assignee ";
            $data [ 'id_assignee' ] = $assignee->uid;
        }

        $conditions[]              = " p.status_analysis != :not_to_analyze ";
        $data [ 'not_to_analyze' ] = ProjectStatus::STATUS_NOT_TO_ANALYZE;

        $where_query = implode(" AND ", $conditions);

        $projectsQuery =
                "SELECT p.id
                FROM projects p
                INNER JOIN jobs j ON j.id_project = p.id
                WHERE $where_query
                GROUP BY 1
                ORDER BY 1 DESC
                LIMIT $start, $step 
            ";

        $stmt = Database::obtain()->getConnection()->prepare($projectsQuery);
        $stmt->execute($data);

        return array_map(function ($d) {
            return $d[ 'id' ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @param UserStruct      $user
     * @param int             $start
     * @param int             $step
     * @param string|null     $search_in_pname
     * @param string|null     $search_source
     * @param string|null     $search_target
     * @param string|null     $search_status
     * @param bool|null       $search_only_completed
     * @param int|null        $project_id
     * @param TeamStruct|null $team
     * @param UserStruct|null $assignee
     * @param bool            $no_assignee
     *
     * @return array
     * @throws ReflectionException
     */
    public static function getProjects(
            UserStruct $user,
            int $start,
            int $step,
            ?string $search_in_pname,
            ?string $search_source,
            ?string $search_target,
            ?string $search_status,
            ?bool $search_only_completed,
            ?int $project_id,
            ?TeamStruct $team = null,
            ?UserStruct $assignee = null,
            ?bool $no_assignee = false
    ): array {
        $id_list = static::_getProjects(
                $start,
                $step,
                $search_in_pname,
                $search_source,
                $search_target,
                $search_status,
                $search_only_completed,
                $project_id,
                $team,
                $assignee,
                $no_assignee
        );

        $_projects = new ProjectDao();
        $projects  = $_projects->getByIdList($id_list);

        $projectRenderer = new Project($projects, $search_status);
        $projectRenderer->setUser($user);

        return $projectRenderer->render();
    }

    /**
     *
     * Very bound to the query SQL which is used to retrieve project jobs or just the count
     * of records for the pagination and other stuff in manage page.
     *
     * @param string|null $search_in_pname
     * @param string|null $search_source
     * @param string|null $search_target
     * @param string|null $search_status
     * @param bool        $search_only_completed
     *
     * @return array
     */
    protected static function conditionsForProjectsQuery(
            ?string $search_in_pname,
            ?string $search_source,
            ?string $search_target,
            ?string $search_status,
            ?bool $search_only_completed = false
    ): array {
        $conditions = [];
        $data       = [];

        if ($search_in_pname) {
            $conditions[]           = " p.name LIKE :project_name ";
            $data[ 'project_name' ] = "%$search_in_pname%";
        }

        if ($search_source) {
            $conditions[]     = " j.source = :source ";
            $data[ 'source' ] = $search_source;
        }

        if ($search_target) {
            $conditions[]     = " j.target = :target  ";
            $data[ 'target' ] = $search_target;
        }

        if ($search_status) {
            $conditions[]           = " j.status_owner = :owner_status ";
            $data[ 'owner_status' ] = $search_status;
        }

        if ($search_only_completed) {
            $conditions[] = " j.completed = 1 ";
        }


        return [$conditions, $data];
    }

    /**
     * @param                        $search_in_pname
     * @param                        $search_source
     * @param                        $search_target
     * @param                        $search_status
     * @param                        $search_only_completed
     * @param TeamStruct|null        $team
     * @param UserStruct|null        $assignee
     * @param bool                   $no_assignee
     *
     * @return array
     */
    public static function getProjectsNumber(
            $search_in_pname,
            $search_source,
            $search_target,
            $search_status,
            $search_only_completed,
            TeamStruct $team = null,
            UserStruct $assignee = null,
            bool $no_assignee = false
    ): array {
        [$conditions, $data] = static::conditionsForProjectsQuery(
                $search_in_pname,
                $search_source,
                $search_target,
                $search_status,
                $search_only_completed
        );

        $query = " SELECT COUNT( distinct id_project ) AS c
                        FROM projects p
                        INNER JOIN jobs j ON j.id_project = p.id  
                  ";


        if (!is_null($team)) {
            $conditions[]       = " p.id_team = :id_team ";
            $data [ 'id_team' ] = $team->id;
        }

        if ($no_assignee) {
            $conditions[] = " p.id_assignee IS NULL ";
        } elseif (!is_null($assignee)) {
            $conditions[]           = " p.id_assignee = :id_assignee ";
            $data [ 'id_assignee' ] = $assignee->uid;
        }

        if (count($conditions)) {
            $query = $query . " AND " . implode(" AND ", $conditions);
        }

        $stmt = Database::obtain()->getConnection()->prepare($query);
        $stmt->execute($data);

        return $stmt->fetchAll();
    }

    /**
     * Formats a date for user visualization.
     *
     * @param string|null $my_date string A date in mysql format. <br/>
     *                             <b>E,g.</b> 2014-01-01 23:59:48
     *
     * @return string A formatted date
     * @throws Exception
     */
    public static function formatJobDate(?string $my_date = 'now'): string
    {
        $date          = new DateTime($my_date);
        $formattedDate = $date->format('Y M d H:i');

        $now       = new DateTime();
        $yesterday = $now->sub(new DateInterval('P1D'));

        //today
        if ($now->format('Y-m-d') == $date->format('Y-m-d')) {
            $formattedDate = "Today, " . $date->format('H:i');
        } //yesterday
        elseif ($yesterday->format('Y-m-d') == $date->format('Y-m-d')) {
            $formattedDate = 'Yesterday, ' . $date->format('H:i');
        } //this month
        elseif ($now->format('Y-m') == $date->format('Y-m')) {
            $formattedDate = $date->format('M d, H:i');
        } //this year
        elseif ($now->format('Y') == $date->format('Y')) {
            $formattedDate = $date->format('M d, H:i');
        }

        return $formattedDate;
    }

}
