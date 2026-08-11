<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 26/03/2018
 * Time: 12:35
 */

namespace Controller\API\V2;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Exception;
use Model\Translations\SegmentTranslationDao;
use Plugins\Features\ReviewExtended\ReviewUtils;
use Utils\ActiveMQ\WorkerClient;
use Utils\AsyncTasks\Workers\BulkSegmentStatusChangeWorker;
use Utils\Constants\SourcePages;
use Utils\Constants\TranslationStatus;


class MarkAllSegmentStatusController extends KleinController
{
    use ChunkNotFoundHandlerTrait;

    /**
     * The only bulk status each phase may set.
     */
    private const STATUS_BY_SOURCE_PAGE = [
        SourcePages::SOURCE_PAGE_TRANSLATE => TranslationStatus::STATUS_TRANSLATED,
        SourcePages::SOURCE_PAGE_REVISION => TranslationStatus::STATUS_APPROVED,
        SourcePages::SOURCE_PAGE_REVISION_2 => TranslationStatus::STATUS_APPROVED2,
    ];


    protected function registerValidators(): void
    {
        $this->appendValidator(new LoginValidator($this));
        $chunkValidator = new ChunkPasswordValidator($this);
        $chunkValidator->onSuccess(function () use ($chunkValidator) {
            $this->chunk = $chunkValidator->getChunk();
        });

        $this->appendValidator($chunkValidator);
    }

    /**
     * Change the status of segments based on the provided parameters.
     * @return void
     * @throws Exception
     * @see api_v2_routes.php
     */
    public function changeSegmentsStatus(): void
    {
        $this->return404IfTheJobWasDeleted();

        $segments_id = $this->sanitizeSegmentIDs($this->request->param('segments_id'));
        $status = strtoupper($this->request->param('status'));

        /*
         * The revision phase comes from the password this request authenticated with, which
         * ChunkPasswordValidator has already stamped onto the chunk. The client no longer declares
         * a revision_number at all: on its own it proved nothing about which phase the caller may
         * act in, so a value sent by an older client is ignored.
         */
        $source_page = $this->chunk->getSourcePage() ?: SourcePages::SOURCE_PAGE_TRANSLATE;
        $revision_number = ReviewUtils::sourcePageToRevisionNumber($source_page);

        /*
         * Each of the three bulk statuses belongs to exactly one phase, so a status disagreeing
         * with the phase the credential resolves to would let a reviewer of one phase stamp the
         * other one's status onto the segments.
         */
        $allowed_status = self::STATUS_BY_SOURCE_PAGE[$source_page] ?? null;

        if ($status !== $allowed_status && in_array($status, self::STATUS_BY_SOURCE_PAGE, true)) {
            throw new AuthorizationError(
                'The presented password does not allow to set the segments status to ' . $status,
                401
            );
        }

        if (in_array($status, [
            TranslationStatus::STATUS_TRANSLATED,
            TranslationStatus::STATUS_APPROVED,
            TranslationStatus::STATUS_APPROVED2
        ])) {
            $unchangeable_segments = (new SegmentTranslationDao($this->getDatabase()))->getUnchangeableStatus(
                $this->chunk,
                $segments_id,
                $status,
                $source_page
            );
            $segments_id = array_diff($segments_id, $unchangeable_segments);

            if (!empty($segments_id)) {
                try {
                    WorkerClient::enqueue(
                        'JOBS',
                        BulkSegmentStatusChangeWorker::class,
                        [
                            'segment_ids' => $segments_id,
                            'client_id' => $this->request->param('client_id'),
                            'chunk' => $this->chunk,
                            'destination_status' => $status,
                            'id_user' => ($this->isLoggedIn() ? $this->getUser()->uid : null),
                            'is_review' => ($status == TranslationStatus::STATUS_APPROVED),
                            'revision_number' => $revision_number
                        ], ['persistent' => true]
                    );
                } catch (Exception $e) {
                    $this->response->json(['error_message' => $e->getMessage(), 'data' => true, 'unchangeble_segments' => $segments_id]);

                    return;
                }
            }

            $this->response->json(['data' => true, 'unchangeble_segments' => $unchangeable_segments]);
        }
    }

    /**
     * @param array<mixed> $segment_list
     * @return array<int, int>
     */
    protected function sanitizeSegmentIDs(array $segment_list): array
    {
        /** @var array<int, int> $filtered */
        $filtered = [];
        foreach ($segment_list as $integer) {
            $result = (int)$integer;
            if (!empty($result)) {
                $filtered[] = $result;
            }
        }

        return array_values(array_unique($filtered));
    }

}