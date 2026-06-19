<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 19/06/2017
 * Time: 18:13
 */

namespace Plugins\Features\ProjectCompletion\Model;


use Controller\API\Commons\Exceptions\AuthenticationError;
use Exception;
use Model\ChunksCompletion\ChunkCompletionEventDao;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobDao;
use Model\FeaturesBase\Hook\Event\Filter\FilterJobPasswordToReviewPasswordEvent;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;
use Utils\Tools\Utils;

class ProjectCompletionStatusModel
{

    /**
     * @var ProjectStruct
     */
    protected ProjectStruct $project;

    /** @var array<string, mixed> */
    protected array $cachedStatus = [];

    private ChunkCompletionEventDao $chunkCompletionEventDao;
    private FeatureSet $featureSet;
    private JobDao $jobDao;

    /**
     * @throws Exception
     */
    public function __construct(
        ProjectStruct $project,
        FeatureSet $featureSet,
        ?ChunkCompletionEventDao $chunkCompletionEventDao = null,
        ?JobDao $jobDao = null,
    ) {
        $this->project = $project;
        $this->chunkCompletionEventDao = $chunkCompletionEventDao ?? new ChunkCompletionEventDao();
        $this->featureSet = $featureSet;
        $this->jobDao = $jobDao ?? new JobDao();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws NotFoundException
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws ValidationError
     * @throws AuthenticationError
     * @throws Exception
     */
    public function getStatus(): array
    {
        if (empty($this->cachedStatus)) {
            $this->cachedStatus = $this->populateStatus();
        }

        return $this->cachedStatus;
    }

    /**
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws EndQueueException
     * @throws ValidationError
     * @throws AuthenticationError
     * @throws Exception
     *
     * @return array<string, mixed>
     */
    private function populateStatus(): array
    {
        $response = [];
        $response['revise'] = [];
        $response['translate'] = [];

        $response['id'] = $this->project->id;

        $any_uncomplete = false;

        foreach ($this->jobDao->getNotDeletedByProjectId((int) $this->project->id) as $chunk) {
            $translate = $this->dataForChunkStatus($chunk, false);
            $revise = $this->dataForChunkStatus($chunk, true);

            $this->featureSet->loadForProject($this->project);
            $filterJobPasswordToReviewPasswordEvent = new FilterJobPasswordToReviewPasswordEvent(
                $chunk->password ?? throw new \RuntimeException('Chunk password is required'),
                $chunk->id ?? throw new \RuntimeException('Chunk id is required')
            );
            $this->featureSet->dispatch($filterJobPasswordToReviewPasswordEvent);
            $revise['password'] = $filterJobPasswordToReviewPasswordEvent->getPassword();

            $response['translate'][] = $translate;
            $response['revise'][] = $revise;

            if (!($revise['completed'] && $translate['completed'])) {
                $any_uncomplete = true;
            }
        }

        $response['completed'] = !$any_uncomplete;

        return $response;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    private function dataForChunkStatus(JobStruct $chunk, bool $is_review): array
    {
        $record = $this->chunkCompletionEventDao->lastCompletionRecord($chunk, [
            'is_review' => $is_review
        ]);

        if ($record) {
            $is_completed = true;
            $completed_at = Utils::api_timestamp($record['create_date']);
            $event_id = $record['id_event'];
        } else {
            $is_completed = false;
            $completed_at = null;
            $event_id = null;
        }

        return [
            'id' => $chunk->id,
            'password' => $chunk->password,
            'completed' => $is_completed,
            'completed_at' => $completed_at,
            'event_id' => $event_id
        ];
    }


}
