<?php

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use DomainException;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Translations\SegmentTranslationDao;
use ReflectionException;

class IssueCheckController extends KleinController
{
    use ChunkNotFoundHandlerTrait;

    /**
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws DomainException
     * @throws \Exception
     */
    public function segments(): void
    {
        $result = [
            'modified_segments_count' => 0,
            'issue_count' => 0,
            'modified_segments' => []
        ];

        // params
        $id_job = (int)$this->request->param('id_job');
        $password = (string)$this->request->param('password');
        $source_page = $this->request->param('source_page', 2);

        // find a job
        $job = $this->getJob($id_job, $password);

        if (null === $job) {
            throw new NotFoundException('Job not found.');
        }

        $this->chunk = $job;
        $this->return404IfTheJobWasDeleted();

        $modifiedSegments = (new SegmentTranslationDao($this->getDatabase()))
            ->setCacheTTL(60 * 5)
            ->getSegmentTranslationsModifiedByRevisorWithIssueCount(
                $job->id ?? throw new DomainException('Job ID must not be null'),
                $job->password ?? throw new DomainException('Job password must not be null'),
                (int)$source_page
            );

        $result['modified_segments_count'] = count($modifiedSegments);

        foreach ($modifiedSegments as $modifiedSegment) {
            /** @var ShapelessConcreteStruct $modifiedSegment */
            $result['modified_segments'][] = [
                'id_segment' => (int)$modifiedSegment->id_segment,
                'issue_count' => (int)$modifiedSegment->q_count,
            ];

            $result['issue_count'] = (int)$result['issue_count'] + (int)$modifiedSegment->q_count;
        }

        $this->response->json($result);
    }
}

