<?php

namespace Controller\Views;

use Controller\Abstracts\BaseKleinViewController;
use Controller\Abstracts\IController;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Controller\API\Commons\Validators\TeamAccessValidator;
use Controller\API\Commons\ViewValidators\ViewLoginRedirectValidator;
use Exception;
use Model\ActivityLog\ActivityLogDao;
use Model\ActivityLog\ActivityLogStruct;
use ReflectionException;

/**
 * User: gremorian
 * Date: 11/05/15
 * Time: 20.37
 *
 */
class ActivityLogController extends BaseKleinViewController implements IController
{

    protected function registerValidators(): void
    {
        $this->appendValidator(new ViewLoginRedirectValidator($this));

        // The project id and password only prove the caller followed a shared link. The activity log
        // lists the name, email and IP of everyone who worked on the project, so viewing it is
        // restricted to the team that owns the project — the same rule the project menu applies when
        // it offers "View Project Logs" only to that team.
        $teamValidator = new TeamAccessValidator($this);

        // Construct first, then attach the callbacks: a closure captures `use` variables by value at
        // creation time, so $projectValidator must already be assigned before onSuccess() references it.
        $projectValidator = new ProjectPasswordValidator($this);
        $projectValidator
            ->onSuccess(function () use ($projectValidator, $teamValidator) {
                // The password validator has just resolved the project, so the owning team is taken
                // from it rather than read again.
                $project = $projectValidator->getProject();
                if ($project === null || $project->id_team === null) {
                    // A project with no team has nobody to be a member of; refuse without disclosing
                    // whether the project exists, matching the wrong-password branch below.
                    $this->setView("project_not_found.html", [], 404);
                    $this->render();
                }

                $teamValidator->setIdTeam($project->id_team);
            })
            ->onFailure(function () {
                $this->setView("project_not_found.html", [], 404);
                $this->render();
            });

        $teamValidator->onFailure(function () {
            $this->setView("project_not_found.html", [], 404);
            $this->render();
        });

        // Registration order is execution order: the password validator resolves the project and
        // seeds the team above, then the team validator confirms membership.
        $this->appendValidator($projectValidator);
        $this->appendValidator($teamValidator);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function renderView(): void
    {
        $request = $this->validateTheRequest();

        if (!is_array($request)) {
            $this->setView("project_not_found.html", [], 404);
            $this->render();
        }

        $activityLogDao = new ActivityLogDao($this->getDatabase());
        $activityLogDao->epilogueString = " LIMIT 1;";
        $rawLogContent = $activityLogDao->read(
            new ActivityLogStruct(),
            ['id_project' => $request['id_project']]
        );

        //NO ACTIVITY DATA FOR THIS PROJECT
        if (empty($rawLogContent)) {
            $this->setView("activity_log_not_found.html", [
                'projectID' => $request['id_project'],
            ]);
            $this->render();
        }

        $this->setView('activity_log.html', [
            'project_id' => $request['id_project'],
            'password' => $request['password'],
        ]);
        $this->render();
    }

    /**
     * @return array<string, mixed>|false|null
     */
    protected function validateTheRequest(): false|array|null
    {
        $filterArgs = [
            'id_project' => ['filter' => FILTER_SANITIZE_NUMBER_INT],
            'password' => [
                'filter' => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            ]
        ];

        return filter_var_array($this->request->paramsNamed()->all(), $filterArgs);
    }

}