<?php

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectAccessValidator;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Exception;
use InvalidArgumentException;
use Model\FeaturesBase\Hook\Event\Run\FilterProjectNameModifiedEvent;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Teams\MembershipDao;
use Model\Users\UserStruct;
use Throwable;
use Utils\Tools\CatUtils;

class ChangeProjectNameController extends KleinController
{

    private ?ProjectStruct $project;

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));

        $projectAccessValidator = new ProjectPasswordValidator($this);
        $this->appendValidator(
            $projectAccessValidator->onSuccess(
                function () use ($projectAccessValidator) {
                    $this->project = $projectAccessValidator->getProject();
                }
            )
        );
    }

    /**
     * @throws Throwable
     */
    public function changeName(): void
    {
        $id = filter_var($this->request->param('id_project'), FILTER_SANITIZE_NUMBER_INT);
        $password = filter_var($this->request->param('password'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $name = filter_var($this->request->param('name'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW]);

        if (
            empty($id) or
            empty($password)
        ) {
            throw new InvalidArgumentException('Missing required parameters [`id `, `password`]');
        }

        $name = CatUtils::sanitizeOrFallbackProjectName(is_string($name) ? $name : '');

        $project = $this->project ?? throw new \RuntimeException('Project not loaded');
        (new ProjectAccessValidator($this, $project))->validate();
        $ownerEmail = $project->id_customer;

        $this->changeProjectName((int)$id, (string)$password, $name);
        $this->featureSet->dispatch(new FilterProjectNameModifiedEvent((int)$id, $name, $password, $ownerEmail));

        $this->response->status()->setCode(200);
        $this->response->json([
            'id' => $id,
            'name' => $name,
        ]);
    }

    /**
     * @param int $id
     * @param string $password
     * @param string $name
     *
     * @throws Exception
     */
    private function changeProjectName(int $id, string $password, string $name): void
    {
        $pStruct = (new ProjectDao($this->db()))->findByIdAndPassword($id, $password);

        $this->checkUserPermissions($pStruct, $this->getUser());

        $pDao = new ProjectDao($this->db());
        $pDao->changeName($pStruct, $name);
        $pDao->destroyFetchByIdCache($id, ProjectStruct::class);
        $projectId = $pStruct->id ?? throw new Exception('Project not found');
        $pDao->destroyCacheForProjectData((int)$projectId, $pStruct->password);
    }

    /**
     * Check if the logged user has the permissions to change the password
     *
     * @param ProjectStruct $project
     * @param UserStruct $user
     *
     * @throws Exception
     */
    private function checkUserPermissions(ProjectStruct $project, UserStruct $user): void
    {
        // check if user is belongs to the project team
        $team = $project->getTeam();
        if ($team === null) {
            throw new Exception('Project has no team', 403);
        }
        $teamId = $team->id ?? throw new Exception('Project has no team', 403);
        $check = (new MembershipDao($this->db()))->findTeamByIdAndUser($teamId, $user);

        if ($check === null) {
            throw new Exception('The logged user does not belong to the right team', 403);
        }
    }
}
