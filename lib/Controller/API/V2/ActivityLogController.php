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
use Controller\API\Commons\Validators\ProjectAccessValidator;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Model\ActivityLog\ActivityLogDao;
use Model\ActivityLog\ActivityLogStruct;
use Model\Projects\ProjectDao;
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

        $this->guardProjectAccess($validator->getProject());

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

        $this->guardProjectAccess($validator->getProject());

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

        $this->guardProjectAccess($validator->getChunk()->getProject(new ProjectDao($this->getDatabase())));

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
     * A project password, or a job password, only proves the caller reached the project through a
     * shared link. Every read on this controller returns activity records carrying the name, email and
     * IP of the people who worked on the project, so all three are restricted the same way: to the
     * project's owner, or to a member of the team it sits in. That matches the "View Project Logs"
     * entry the editor offers only to that team, and the manage page, whose callers are already
     * members.
     *
     * The owner is let through without a membership lookup by ProjectAccessValidator, because a
     * project outlives its owner's membership of the team it sits in — see the reasoning there.
     *
     * @throws AuthorizationError if the caller is not logged in, or is neither the project's owner nor
     *                            a member of its team — including when the project has no team at all,
     *                            since there is then nobody the membership check could match
     * @throws Throwable
     */
    private function guardProjectAccess(?ProjectStruct $project): void
    {
        if ($project === null) {
            throw new AuthorizationError('Not Authorized', 401);
        }

        (new ProjectAccessValidator($this, $project, $this->getUser()))->validate();
    }

    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

}