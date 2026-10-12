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
use Model\Teams\TeamDao;
use Model\Users\UserStruct;
use Throwable;
use Utils\Tools\CatUtils;
use Utils\Validation\UserSuppliedName;

class ChangeProjectNameController extends KleinController
{

    private ?ProjectStruct $project;

    protected function registerValidators(): void
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
        // FILTER_UNSAFE_RAW: the name is stored as the user typed it and escaped by each output
        // instead. FILTER_SANITIZE_SPECIAL_CHARS here was worse than useless — it wrote entity text
        // into the column, and then sanitizeProjectName() deleted the `&` and the `;` out of the
        // entity it had just written, so a project called "A & B" was stored as "A amp B".
        $name = filter_var($this->request->param('name'), FILTER_UNSAFE_RAW);

        if (
            empty($id) or
            empty($password)
        ) {
            throw new InvalidArgumentException('Missing required parameters [`id `, `password`]');
        }

        // A rename says what the name is to become, so an empty or over-long one is refused rather
        // than replaced with a generated fallback — unlike project creation, which has an uploaded
        // filename to fall back to and no user waiting to be told why.
        $name = UserSuppliedName::validated(
            is_string($name) ? $name : null,
            'name',
            CatUtils::PROJECT_NAME_MAX_LENGTH
        );

        $project = $this->project ?? throw new \RuntimeException('Project not loaded');
        (new ProjectAccessValidator($this, $project, $this->getUser()))->validate();
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
        $pStruct = (new ProjectDao($this->getDatabase()))->findByIdAndPassword($id, $password);

        $this->checkUserPermissions($pStruct, $this->getUser());

        $pDao = new ProjectDao($this->getDatabase());
        // changeName() goes through updateField(), which evicts every project cache key
        $pDao->changeName($pStruct, $name);
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
        $team = $project->id_team !== null ? (new TeamDao($this->getDatabase()))->findById($project->id_team) : null;
        if ($team === null) {
            throw new Exception('Project has no team', 403);
        }
        $teamId = $team->id ?? throw new Exception('Project has no team', 403);
        $check = (new MembershipDao($this->getDatabase()))->findTeamByIdAndUser($teamId, $user);

        if ($check === null) {
            throw new Exception('The logged user does not belong to the right team', 403);
        }
    }
}
