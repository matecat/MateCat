<?php

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use InvalidArgumentException;
use Model\FeaturesBase\FeatureSet;
use Model\FeaturesBase\Hook\Event\Run\JobPasswordChangedEvent;
use Model\FeaturesBase\Hook\Event\Run\ReviewPasswordChangedEvent;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Teams\MembershipDao;
use Model\Teams\TeamDao;
use Model\Users\UserStruct;
use Plugins\Features\ReviewExtended\ReviewUtils;
use Throwable;
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

        $res = (!empty($res)) ? $res : 'job';

        if (!in_array($res, ['prj', 'job'])) {
            throw new InvalidArgumentException('Invalid value for parameter `res`. Allowed values [`prj`, `job`]');
        }

        $user = $this->getUser();
        $this->changeThePassword($user, (string)$res, (int)$id, (string)$actual_pwd, (string)$new_pwd);

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
     * @param string $actual_pwd The current password of the resource being changed. For a job it may
     *                           be either the translate password or the review password of one
     *                           phase, and that choice — not a client-declared revision_number —
     *                           decides which password is rotated (GHSA-7q94-2fmr-3p42).
     * @param string $new_password
     *
     * @throws Exception
     * @throws Throwable
     */
    private function changeThePassword(UserStruct $user, string $res, int $id, string $actual_pwd, string $new_password): void
    {
        // change project password
        if ($res == "prj") {
            $pStruct = (new ProjectDao($this->getDatabase()))->findByIdAndPassword($id, $actual_pwd);

            $this->checkUserPermissions($pStruct, $user);

            $pDao = new ProjectDao($this->getDatabase());
            $pDao->changePassword($pStruct, $new_password);
            $pDao->destroyFetchByIdCache($id, ProjectStruct::class);

            // Project data is cached for a day and embeds the passwords, so both the credential that
            // was replaced and the one replacing it are evicted, along with the variant no password
            // is passed to.
            $projectId = $pStruct->id ?? throw new \RuntimeException('Missing project id');
            $pDao->destroyCacheForProjectData($projectId);
            $pDao->destroyCacheForProjectData($projectId, $actual_pwd);
            $pDao->destroyCacheForProjectData($projectId, $new_password);
        } else { // change job passwords

            $this->getDatabase()->begin();

            $jDao = new JobDao($this->getDatabase());
            $jStruct = $jDao->getByIdAndPassword($id, $actual_pwd);

            if ($jStruct !== null) { // the translate password was presented: change the job password

                $pDao = new ProjectDao($this->getDatabase());
                $this->checkUserPermissions($jStruct->getProject($pDao), $user);

                $jDao->changePassword($jStruct, $new_password);
                FeatureSet::forProject($jStruct->getProject($pDao), $this->getDatabase())
                    ->dispatch(new JobPasswordChangedEvent($jStruct, $actual_pwd));

            } else { // a review password was presented: rotate the phase that password belongs to

                // The phase is read from the review row the password matched, never from a
                // client-declared revision_number: the caller must present the current password of
                // the resource being changed (GHSA-7q94-2fmr-3p42). This also removes a silent
                // no-op, since updateReviewPassword() already filtered on review_password and
                // matched nothing when the declared phase disagreed with the credential.
                $dao = new ChunkReviewDao($this->getDatabase());
                $chunkReview = $dao->findByReviewPasswordAndJobId($actual_pwd, $id);

                if ($chunkReview === null) {
                    throw new Exception('Job not found');
                }

                $jStruct = $chunkReview->getChunk($jDao);
                $pDao = new ProjectDao($this->getDatabase());
                $this->checkUserPermissions($jStruct->getProject($pDao), $user);

                $source_page = $chunkReview->source_page;
                $revision_number = ReviewUtils::sourcePageToRevisionNumber($source_page)
                    ?? throw new Exception('The matched review row does not belong to a revision phase');

                $dao->updateReviewPassword($id, $actual_pwd, $new_password, $source_page);
                FeatureSet::forProject($jStruct->getProject($pDao), $this->getDatabase())
                    ->dispatch(new ReviewPasswordChangedEvent($id, $actual_pwd, $new_password, $revision_number));
            }

            // Invalidate every ChunkReviewDao read keyed on a job credential, for the password that
            // was replaced and for the one replacing it: changing a password has to shut the editor
            // on the previous link straight away, not when the cache expires.
            $chunkReviewDao = new ChunkReviewDao($this->getDatabase());
            $chunkReviewDao->destroyCacheForJobPassword($id, $actual_pwd);
            $chunkReviewDao->destroyCacheForJobPassword($id, $new_password);

            // The chunk rows carry the review passwords, so the entry keyed on the job password is
            // stale after a review password rotation as well.
            $chunkReviewDao->destroyCacheForFindChunkReviews($jStruct);

            // invalidate cache for ProjectData, which embeds the job passwords
            $pDao = new ProjectDao($this->getDatabase());
            $projectId = $jStruct->getProject($pDao)->id ?? throw new Exception('Project not found');
            $pDao->destroyCacheForProjectData($projectId);
            $pDao->destroyCacheForProjectData($projectId, $jStruct->getProject($pDao)->password);
            $pDao->destroyFetchByIdCache($jStruct->getProject($pDao)->id, ProjectStruct::class);

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
