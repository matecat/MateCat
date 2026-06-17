<?php

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Model\FeaturesBase\Hook\Event\Run\JobPasswordChangedEvent;
use Model\FeaturesBase\Hook\Event\Run\ReviewPasswordChangedEvent;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Teams\MembershipDao;
use Model\Users\UserStruct;
use Plugins\Features\ReviewExtended\ReviewUtils;
use Utils\Tools\CatUtils;
use Utils\Tools\Utils;

class ChangePasswordController extends KleinController
{
    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws Exception
     */
    public function changePassword(): void
    {
        $res = filter_var($this->request->param('res'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $id = filter_var($this->request->param('id'), FILTER_SANITIZE_NUMBER_INT);
        $password = filter_var($this->request->param('password'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $new_password = filter_var($this->request->param('new_password'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $revision_number = filter_var($this->request->param('revision_number'), FILTER_SANITIZE_NUMBER_INT);
        $undo = filter_var($this->request->param('undo'), FILTER_VALIDATE_BOOLEAN);

        if (empty($id) || empty($password)) {
            throw new InvalidArgumentException('Missing required parameters [`id `, `password`]');
        }

        if ($undo) {
            // in this case new_password is mandatory
            if (empty($new_password)) {
                throw new InvalidArgumentException('Missing required parameters [`new_password`]');
            }

            $new_pwd = $new_password;
            $actual_pwd = $password;
        } else {
            $new_pwd = Utils::randomString();
            $actual_pwd = $password;
        }

        if (!empty($revision_number) and !in_array($revision_number, [1, 2])) {
            throw new InvalidArgumentException('Invalid value for parameter `revision_number`. Allowed values [1, 2]');
        }

        $res = (!empty($res)) ? $res : 'job';

        if (!in_array($res, ['prj', 'job'])) {
            throw new InvalidArgumentException('Invalid value for parameter `res`. Allowed values [`prj`, `job`]');
        }

        $user = $this->getUser();
        $revisionNumberInt = ($revision_number !== false && $revision_number !== '') ? (int)$revision_number : null;
        $this->changeThePassword($user, (string)$res, (int)$id, (string)$actual_pwd, (string)$new_pwd, $revisionNumberInt);

        $this->response->status()->setCode(200);
        $this->response->json([
            'id' => $id,
            'new_pwd' => $new_pwd,
            'old_pwd' => $actual_pwd,
        ]);
    }

    /**
     * @param UserStruct $user
     * @param string $res
     * @param int $id
     * @param string $actual_pwd
     * @param string $new_password
     * @param int|null $revision_number
     *
     * @throws Exception
     */
    private function changeThePassword(UserStruct $user, string $res, int $id, string $actual_pwd, string $new_password, ?int $revision_number = null): void
    {
        // change project password
        if ($res == "prj") {
            $pStruct = (new ProjectDao($this->getDatabase()))->findByIdAndPassword($id, $actual_pwd);

            $this->checkUserPermissions($pStruct, $user);

            $pDao = new ProjectDao($this->getDatabase());
            $pDao->changePassword($pStruct, $new_password);
            $pDao->destroyFetchByIdCache($id, ProjectStruct::class);
            $pDao->destroyCacheForProjectData($pStruct->id ?? throw new \RuntimeException('Missing project id'), $pStruct->password);
        } else { // change job passwords

            $this->getDatabase()->begin();

            if ($revision_number) { // change job revision password

                $jStruct = (new CatUtils())->getJobFromIdAndAnyPassword($id, $actual_pwd);

                if ($jStruct === null) {
                    throw new Exception('Job not found');
                }

                $this->checkUserPermissions($jStruct->getProject(), $user);

                $source_page = ReviewUtils::revisionNumberToSourcePage($revision_number);
                $dao = new ChunkReviewDao($this->getDatabase());
                $dao->updateReviewPassword($id, $actual_pwd, $new_password, $source_page);
                $dao->destroyCacheForJobIdReviewPasswordAndSourcePage($id, $actual_pwd, $source_page);
                $jStruct->getProject()
                    ->getFeaturesSet()->dispatch(new ReviewPasswordChangedEvent($id, $actual_pwd, $new_password, $revision_number));

            } else { // change job password
                $jDao = new JobDao($this->getDatabase());
                $jStruct = $jDao->getByIdAndPassword($id, $actual_pwd);

                if ($jStruct === null) {
                    throw new Exception('Job not found');
                }

                $this->checkUserPermissions($jStruct->getProject(), $user);

                $jDao->changePassword($jStruct, $new_password);
                $jStruct->getProject()
                    ->getFeaturesSet()->dispatch(new JobPasswordChangedEvent($jStruct, $actual_pwd));
            }

            // invalidate ChunkReviewDao cache for the job
            $chunkReviewDao = new ChunkReviewDao($this->getDatabase());
            $chunkReviewDao->destroyCacheForFindChunkReviews($jStruct);

            // invalidate cache for ProjectData
            $pDao = new ProjectDao($this->getDatabase());
            $projectId = $jStruct->getProject()->id ?? throw new Exception('Project not found');
            $pDao->destroyCacheForProjectData((int)$projectId, $jStruct->getProject()->password);
            $pDao->destroyFetchByIdCache($jStruct->getProject()->id, ProjectStruct::class);

            $this->getDatabase()->commit();
        }
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
        $check = (new MembershipDao($this->getDatabase()))->findTeamByIdAndUser($teamId, $user);

        if ($check === null) {
            throw new Exception('The logged user does not belong to the right team', 403);
        }
    }
}
