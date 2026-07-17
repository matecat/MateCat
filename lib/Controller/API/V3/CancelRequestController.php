<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 13/09/2018
 * Time: 13:03
 */

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\ConflictError;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Controller\Traits\RateLimiterTrait;
use Exception;
use Klein\Response;
use Model\Exceptions\NotFoundException;
use Model\Jobs\JobStruct;
use Model\Segments\SegmentDisabledService;
use Model\Segments\SegmentMetadataDao;
use Model\Projects\ProjectDao;
use Model\Teams\TeamDao;
use Model\Translations\SegmentTranslationDao;
use Stomp\Exception\ConnectionException;
use Stomp\Transport\Message;
use Utils\ActiveMQ\AMQHandler;
use Utils\Constants\TranslationStatus;
use Utils\Registry\AppConfig;
use Utils\Tools\Utils;

class CancelRequestController extends KleinController
{
    use RateLimiterTrait;
    use ChunkNotFoundHandlerTrait;

    private const string SEGMENT_DISABLED_TYPE = 'segment_disabled';
    private const string SEGMENT_ENABLED_TYPE = 'segment_enabled';

    protected SegmentDisabledService $segmentDisabledService;
    protected SegmentTranslationDao $segmentTranslationDao;
    protected TeamDao $teamDao;

    protected function initDependencies(): void
    {
        $this->segmentDisabledService = new SegmentDisabledService(new SegmentMetadataDao($this->getDatabase()));
        $this->segmentTranslationDao = new SegmentTranslationDao($this->getDatabase());
        $this->teamDao = new TeamDao($this->getDatabase());
    }

    protected function registerValidators(): void
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

        if ($this->isRateLimited($route)) {
            return;
        }

        $job = $this->performChecks($id_job, $password, $id_segment);

        if ($this->segmentDisabledService->isDisabled($id_segment)) {
            $this->segmentDisabledService->enable($id_segment);
            $this->publishSegmentStateChange($job, $id_job, $id_segment, false);
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

        if ($this->isRateLimited($route)) {
            return;
        }

        $job = $this->performChecks($id_job, $password, $id_segment);

        if (!$this->segmentDisabledService->isDisabled($id_segment)) {
            $this->segmentDisabledService->disable($id_segment);
            $this->publishSegmentStateChange($job, $id_job, $id_segment, true);
        }

        $this->response->json([
            'id_segment' => $id_segment,
        ]);
    }

    /**
     * Atomically checks and increments the rate limit for the current user's IP and email
     * against the given route. Sets $this->response to the 429 response on the first identifier
     * that exceeds the limit.
     *
     * @throws Exception
     */
    private function isRateLimited(string $route): bool
    {
        $userEmail = $this->user->email ?? "BLANK_EMAIL";
        $userIp = Utils::getRealIpAddr() ?? "127.0.0.1";

        foreach ([$userIp, $userEmail] as $identifier) {
            $rateLimitResponse = $this->checkAndIncrementRateLimit($this->response, $identifier, $route, 5);
            if ($rateLimitResponse instanceof Response) {
                $this->response = $rateLimitResponse;
                return true;
            }
        }

        return false;
    }

    /**
     * Performs several validation checks for disabling/enabling a segment in a job.
     *
     * @param int $id_job The unique identifier of the job.
     * @param string $password The password associated with the job for authentication.
     * @param int $id_segment The unique identifier of the segment to be validated.
     *
     * @throws NotFoundException If the job or segment is not found.
     * @throws Exception If the user is not the owner or part of the team, or if the segment status is not "new".
     */
    private function performChecks(int $id_job, string $password, int $id_segment): JobStruct
    {
        // 1. check job id and password
        $job = $this->getJob($id_job, $password);
        if (null === $job) {
            throw new NotFoundException('Job not found.');
        }

        // 2. check segment translation
        $segmentTranslation = $this->segmentTranslationDao->findBySegmentAndJob($id_segment, $id_job);
        if (null === $segmentTranslation) {
            throw new NotFoundException('Segment not found');
        }

        // 3. check if user is part of the team
        $project = $job->getProject(new ProjectDao($this->getDatabase()));
        $team = $project->id_team !== null ? $this->teamDao->findById($project->id_team) : null;
        if (empty($team)) {
            throw new NotFoundException('Team not found');
        }

        $uid = $this->getUser()->uid;
        if (!empty($uid) && $team->created_by !== $uid && !$team->hasUser($uid)) {
            throw new Exception('User is not part of the team');
        }

        // 4. check segment status
        // return 409 http code if the segment is not in "new" status
        if ($segmentTranslation->status !== TranslationStatus::STATUS_NEW) {
            throw new ConflictError('Segment is not in "new" status and cannot be disabled');
        }

        return $job;
    }

    /**
     * Publishes a segment enable/disable state change to the project-scoped Socket.IO room so
     * every open browser tab for the project can live-patch its segment state without a
     * page refresh.
     *
     * @throws ConnectionException
     */
    private function publishSegmentStateChange(JobStruct $job, int $id_job, int $id_segment, bool $disabled): void
    {
        $message = (string)json_encode([
            '_type' => $disabled ? self::SEGMENT_DISABLED_TYPE : self::SEGMENT_ENABLED_TYPE,
            'data'  => [
                'id_project' => $job->id_project,
                'payload'    => [
                    'id_segment' => $id_segment,
                    'id_job'     => $id_job,
                    'id_project' => $job->id_project,
                    'disabled'   => $disabled,
                ],
            ],
        ]);

        $queueHandler = $this->getQueueHandler();
        $queueHandler->publishToNodeJsClients(AppConfig::$SOCKET_NOTIFICATIONS_QUEUE_NAME, new Message($message));
    }

    /**
     * Test seam: allows a Testable subclass to inject a queue handler backed by a
     * stubbed transport, avoiding a real broker connection in unit tests.
     *
     * @throws ConnectionException
     */
    protected function getQueueHandler(): AMQHandler
    {
        return new AMQHandler();
    }

}
