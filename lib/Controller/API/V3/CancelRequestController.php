<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 13/09/2018
 * Time: 13:03
 */

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Controller\Traits\RateLimiterTrait;
use Controller\Traits\SegmentDisabledTrait;
use Exception;
use Klein\Response;
use Model\DataAccess\DaoCacheTrait;
use Model\Exceptions\NotFoundException;
use Model\Segments\SegmentMetadataDao;
use Model\Translations\SegmentTranslationDao;
use Utils\Constants\TranslationStatus;
use Utils\Tools\Utils;

class CancelRequestController extends KleinController
{
    use DaoCacheTrait;
    use RateLimiterTrait;
    use SegmentDisabledTrait;
    use ChunkNotFoundHandlerTrait;

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @throws Exception
     */
    public function enableRequest(): void
    {
        $rawIdJob = $this->request->param('id_job');
        $password = $this->request->param('password');
        $rawIdSegment = $this->request->param('id_segment');

        $id_job = filter_var($rawIdJob, FILTER_VALIDATE_INT);
        $id_segment = filter_var($rawIdSegment, FILTER_VALIDATE_INT);

        if ($id_job === false || $id_segment === false) {
            $this->response->code(400);
            $this->response->json([
                'errors' => [
                    [
                        'code' => 400,
                        'message' => 'Invalid id_job or id_segment',
                    ],
                ],
            ]);

            return;
        }

        $route = '/api/v3/jobs/'.$id_job.'/'.$password.'/segment/enable/'.$id_segment;
        $this->performChecks($id_job, $password, $id_segment, $route);

        if ($this->isSegmentDisabled($id_job, $id_segment)) {
            $this->destroySegmentDisabledCache($id_job, $id_segment);
        }

        $this->response->json([
            'id_segment' => $id_segment,
        ]);
    }

    /**
     * @throws Exception
     */
    public function cancelRequest(): void
    {
        $id_job = $this->request->param('id_job');
        $password = $this->request->param('password');
        $id_segment = $this->request->param('id_segment');
        $route = '/api/v3/jobs/'.$id_job.'/'.$password.'/segment/disable/'.$id_segment;

        $this->performChecks($id_job, $password, $id_segment, $route);

        // If the cache is empty, it means that the segment is not already disabled, so we can proceed with disabling it and
        // setting the cache to avoid multiple disable requests for the same segment in a short time frame
        if (!$this->isSegmentDisabled($id_job, $id_segment)) {
            SegmentMetadataDao::destroyGetAllCache($id_segment);
            SegmentMetadataDao::setTranslationDisabled($id_segment);
            $this->saveSegmentDisabledInCache($id_job, $id_segment);
        }

        $this->response->json([
            'id_segment' => $id_segment,
        ]);
    }

    /**
     * Performs several validation checks and rate limit handling for disabling a segment in a job.
     *
     * @param int $id_job The unique identifier of the job.
     * @param string $password The password associated with the job for authentication.
     * @param int $id_segment The unique identifier of the segment to be validated.
     * @param string $route The API route being accessed.
     *
     * @throws NotFoundException If the job or segment is not found.
     * @throws Exception If the user is not the owner or part of the team, or if the segment status is not "new".
     */
    private function performChecks(int $id_job, string $password, int $id_segment, string $route): void
    {
        $userEmail = $this->user->email ?? "BLANK_EMAIL";
        $userIp = Utils::getRealIpAddr() ?? "127.0.0.1";

        // 1. check rate limit
        $checkRateLimitEmail = $this->checkRateLimitResponse($this->response, $userEmail, $route, 5);
        $checkRateLimitIp = $this->checkRateLimitResponse($this->response, $userIp, $route, 5);

        if ($checkRateLimitIp instanceof Response) {
            $this->response = $checkRateLimitIp;

            return;
        }

        if ($checkRateLimitEmail instanceof Response) {
            $this->response = $checkRateLimitEmail;

            return;
        }

        // 2. check job id and password
        $job = $this->getJob($id_job, $password);

        if (null === $job) {
            $this->incrementRateLimitCounter($userEmail, $route);
            $this->incrementRateLimitCounter($userIp, $route);

            throw new NotFoundException('Job not found.');
        }

        // 3. check segment translation
        $segmentTranslation = SegmentTranslationDao::findBySegmentAndJob($id_segment, $id_job);

        if (empty($segmentTranslation)) {
            $this->incrementRateLimitCounter($userEmail, $route);
            $this->incrementRateLimitCounter($userIp, $route);

            throw new NotFoundException('Segment not found');
        }

        // 4. check is user is the owner of the segment
        $team = $job->getProject()->getTeam();

        if(empty($team)){
            $this->incrementRateLimitCounter($userEmail, $route);
            $this->incrementRateLimitCounter($userIp, $route);

            throw new NotFoundException('Team not found');
        }

        if(!empty($this->getUser()->uid) && $team->created_by != $this->getUser()->uid){

            // check if user is part of the team
            if (!$team->hasUser($this->getUser()->uid)){
                $this->incrementRateLimitCounter($userEmail, $route);
                $this->incrementRateLimitCounter($userIp, $route);

                throw new Exception('User is not part of the team');
            }

            $this->incrementRateLimitCounter($userEmail, $route);
            $this->incrementRateLimitCounter($userIp, $route);

            throw new Exception('User is not the owner of the segment');
        }

        // 5. check segment status
        if ($segmentTranslation->status !== TranslationStatus::STATUS_NEW) {
            $this->incrementRateLimitCounter($userEmail, $route);
            $this->incrementRateLimitCounter($userIp, $route);

            throw new Exception('Segment is not in "new" status and cannot be disabled');
        }

        $this->incrementRateLimitCounter($userEmail, $route);
        $this->incrementRateLimitCounter($userIp, $route);
    }
}