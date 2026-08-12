<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 12/12/16
 * Time: 12.13
 *
 */

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Controller\API\Commons\Validators\TeamAccessValidator;
use Model\ActivityLog\ActivityLogDao;
use Model\ActivityLog\ActivityLogStruct;
use Model\Projects\ProjectStruct;
use ReflectionException;
use Throwable;
use View\API\V2\Json\Activity;

class ActivityLogController extends KleinController
{

    /**
     * @throws Throwable
     */
    public function allOnProject(): void
    {
        $validator = new ProjectPasswordValidator($this);
        $validator->validate();

        $this->guardProjectTeamMembership($validator->getProject());

        $activityLogDao = new ActivityLogDao($this->getDatabase());
        $rawContent = $activityLogDao->getAllForProject($validator->getIdProject());

        $formatted = new Activity($rawContent, $this->featureSet);
        $this->response->json($formatted->render());
    }

    /**
     * @throws Throwable
     */
    public function lastOnProject(): void
    {
        $validator = new ProjectPasswordValidator($this);
        $validator->validate();

        $this->guardProjectTeamMembership($validator->getProject());

        $activityLogDao = new ActivityLogDao($this->getDatabase());
        $rawContent = $activityLogDao->getLastActionInProject($validator->getIdProject());

        $formatted = new Activity($rawContent, $this->featureSet);
        $this->response->json(['activity' => $formatted->render()]);
    }

    /**
     * @throws ReflectionException
     * @throws Throwable
     */
    public function lastOnJob(): void
    {
        $validator = new ChunkPasswordValidator($this);
        $validator->validate();

        $activityLogDao = new ActivityLogDao($this->getDatabase());
        $activityLogDao->whereConditions = ' id_job = :id_job ';
        $activityLogDao->epilogueString = " ORDER BY ID DESC LIMIT 1";
        /** @var ActivityLogStruct[] $rawLogContent */
        $rawLogContent = $activityLogDao->read(
            new ActivityLogStruct(),
            ['id_job' => $validator->getJobId()]
        );

        $formatted = new Activity($rawLogContent, $this->featureSet);
        $this->response->json(['activity' => $formatted->render()]);
    }

    /**
     * The project id and password only prove the caller reached the project through a shared link.
     * The activity log lists the name, email and IP of everyone who worked on the project, so it is
     * restricted to the team that owns the project, matching the "View Project Logs" entry the editor
     * offers only to that team. lastOnJob is deliberately not guarded this way: it is scoped to a
     * single job by its own password, the working capability of a collaborator on that job.
     *
     * @throws AuthorizationError if the project has no team, since a project without a team has no
     *                            members and there is nobody the membership check could match
     * @throws Throwable
     */
    private function guardProjectTeamMembership(?ProjectStruct $project): void
    {
        if ($project === null || $project->id_team === null) {
            throw new AuthorizationError('Not authorized', 401);
        }

        $teamValidator = new TeamAccessValidator($this);
        $teamValidator->setIdTeam($project->id_team);
        $teamValidator->validate();
    }

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

}