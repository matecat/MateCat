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
use Exception;
use Klein\Response;
use Model\Exceptions\NotFoundException;
use Model\Segments\SegmentDisabledService;
use Model\Translations\SegmentTranslationDao;
use Model\Translations\SegmentTranslationStruct;
use ReflectionException;
use Utils\Constants\TranslationStatus;
use Utils\Tools\Utils;

class CancelRequestController extends KleinController
{
    use RateLimiterTrait;
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
            throw new NotFoundException('Invalid id_job or id_segment');
        }

        $route = '/api/v3/jobs/' . $id_job . '/segment/enable/' . $id_segment;

        $this->performChecks($id_job, $password, $id_segment, $route);

        if ($this->response->code() === 429) {
            return;
        }

        $service = new SegmentDisabledService();

        if ($service->isDisabled($id_segment)) {
            $service->enable($id_segment);
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
        $rawIdJob = $this->request->param('id_job');
        $password = $this->request->param('password');
        $rawIdSegment = $this->request->param('id_segment');
        $id_job = filter_var($rawIdJob, FILTER_VALIDATE_INT);
        $id_segment = filter_var($rawIdSegment, FILTER_VALIDATE_INT);

        if ($id_job === false || $id_segment === false) {
            throw new NotFoundException('Invalid id_job or id_segment');
        }

        $route = '/api/v3/jobs/' . $id_job . '/segment/disable/' . $id_segment;

        $this->performChecks($id_job, $password, $id_segment, $route);

        if ($this->response->code() === 429) {
            return;
        }

        $service = new SegmentDisabledService();

        if (!$service->isDisabled($id_segment)) {
            $service->disable($id_segment);
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

        // 1. atomic check + increment rate limit
        foreach ([$userIp, $userEmail] as $identifier) {
            $rateLimitResponse = $this->checkAndIncrementRateLimit($this->response, $identifier, $route, 5);
            if ($rateLimitResponse instanceof Response) {
                $this->response = $rateLimitResponse;
                return;
            }
        }

        // 2. check job id and password
        $job = $this->getJob($id_job, $password);
        if (null === $job) {
            throw new NotFoundException('Job not found.');
        }

        // 3. check segment translation
        $segmentTranslation = $this->findSegmentTranslation($id_segment, $id_job);
        if (null === $segmentTranslation) {
            throw new NotFoundException('Segment not found');
        }

        // 4. check if user is part of the team
        $team = $job->getProject()->getTeam();
        if (empty($team)) {
            throw new NotFoundException('Team not found');
        }

        $uid = $this->getUser()->uid;
        if (!empty($uid) && $team->created_by !== $uid && !$team->hasUser($uid)) {
            throw new Exception('User is not part of the team');
        }

        // 5. check segment status
        if ($segmentTranslation->status !== TranslationStatus::STATUS_NEW) {
            throw new Exception('Segment is not in "new" status and cannot be disabled');
        }
    }

    /**
     * @param int $id_segment
     * @param int $id_job
     *
     * @return ?SegmentTranslationStruct
     * @throws ReflectionException
     */
    protected function findSegmentTranslation(int $id_segment, int $id_job): ?SegmentTranslationStruct
    {
        return SegmentTranslationDao::findBySegmentAndJob($id_segment, $id_job);
    }
}
