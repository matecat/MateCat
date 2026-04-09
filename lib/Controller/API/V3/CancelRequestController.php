<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 13/09/2018
 * Time: 13:03
 */

namespace Controller\API\V3;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Controller\Traits\RateLimiterTrait;
use Controller\Traits\SegmentDisabledTrait;
use Exception;
use Klein\Response;
use Model\DataAccess\DaoCacheTrait;
use Model\Exceptions\NotFoundException;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\Projects\ProjectStruct;
use Model\Segments\SegmentMetadataDao;
use Model\Segments\SegmentMetadataStruct;
use Model\Translations\SegmentTranslationDao;
use Utils\Constants\TranslationStatus;
use Utils\Tools\Utils;
use View\API\V3\Json\Chunk;

class CancelRequestController extends KleinController
{
    use RateLimiterTrait;
    use SegmentDisabledTrait;

    /**
     * @throws Exception
     */
    public function cancelRequest(int $id_job, int $id_segment): void
    {
        $route = '/api/v3/cancel-request/'.$id_job.'/'.$id_segment;
        $checkRateLimitEmail = $this->checkRateLimitResponse($this->response, $this->user->email ?? "BLANK_EMAIL", $route, 5);
        $checkRateLimitIp = $this->checkRateLimitResponse($this->response, Utils::getRealIpAddr() ?? "127.0.0.1", $route, 5);

        if ($checkRateLimitIp instanceof Response) {
            $this->response = $checkRateLimitIp;

            return;
        }

        if ($checkRateLimitEmail instanceof Response) {
            $this->response = $checkRateLimitEmail;

            return;
        }

        $segmentTranslation = SegmentTranslationDao::findBySegmentAndJob($id_segment, $id_job);

        if (empty($segmentTranslation)) {
            throw new NotFoundException('Segment not found');
        }

        if ($segmentTranslation->status !== TranslationStatus::STATUS_NEW) {
            throw new Exception('Segment is not in "new" status and cannot be disabled');
        }

        // If the cache is empty, it means that the segment is not already disabled, so we can proceed with disabling it and
        // setting the cache to avoid multiple disable requests for the same segment in a short time frame
        if (!$this->isSegmentDisabled($id_job, $id_segment)) {
            SegmentMetadataDao::setTranslationDisabled($id_job, $id_segment);
            SegmentMetadataDao::destroyGetAllCache($id_segment);
        }

        $this->response->json([
            'id_segment' => $id_segment,
        ]);
    }
}