<?php

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\ProjectAccessValidator;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Exception;
use InvalidArgumentException;
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

        $name = CatUtils::sanitizeOrFallbackProjectName($name ?? '');

        (new ProjectAccessValidator($this, $this->project))->validate();
        $ownerEmail = $this->project->id_customer;

        $this->changeProjectName($id, $password, $name);
        $this->featureSet->filter('filterProjectNameModified', $id, $name, $password, $ownerEmail);

        $this->response->status()->setCode(200);
        $this->response->json([
            'id' => $id,
            'name' => $name,
        ]);
    }

    /**
     * @param $id
     * @param $password
     * @param $name
     *
     * @throws Exception
     */
    private function changeProjectName($id, $password, $name): void
    {
        $pStruct = ProjectDao::findByIdAndPassword($id, $password);

        $this->checkUserPermissions($pStruct, $this->getUser());

        $pDao = new ProjectDao();
        $pDao->changeName($pStruct, $name);
        $pDao->destroyCacheById($id);
        $pDao->destroyCacheForProjectData($pStruct->id, $pStruct->password);
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
        $check = (new MembershipDao())->findTeamByIdAndUser($team->id, $user);

        if ($check === null) {
            throw new Exception('The logged user does not belong to the right team', 403);
        }
    }
}