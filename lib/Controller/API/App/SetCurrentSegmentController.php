<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\ChunkPasswordValidator;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\Traits\ChunkNotFoundHandlerTrait;
use Exception;
use InvalidArgumentException;
use Model\Exceptions\NotFoundException;
use Model\Segments\SegmentDao;
use Model\TranslationsSplit\SegmentSplitStruct;
use Model\TranslationsSplit\SplitDAO;
use ReflectionException;
use RuntimeException;
use TypeError;
use Utils\Constants\SourcePages;
use Utils\Constants\TranslationStatus;
use Utils\Tools\CatUtils;

class SetCurrentSegmentController extends KleinController
{
    use ChunkNotFoundHandlerTrait;

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
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws RuntimeException
     * @throws Exception
     * @throws TypeError
     */
    public function set(): void
    {
        $request = $this->validateTheRequest();
        $id_segment = $request['id_segment'];
        $split_num = $request['split_num'];

        /*
         * Which phase the caller is navigating in comes from the password this request
         * authenticated with, not from a client-declared revision_number.
         */
        $isRevision = ($this->chunk->getSourcePage() ?: SourcePages::SOURCE_PAGE_TRANSLATE) !== SourcePages::SOURCE_PAGE_TRANSLATE;

        // The next-segment lookup joins on the job password, which is never the review one.
        $id_job = $this->chunk->id ?? throw new RuntimeException('Missing job id');
        $password = $this->chunk->password ?? throw new RuntimeException('Missing job password');

        if (empty($id_segment)) {
            throw new InvalidArgumentException("missing segment id", -1);
        }

        $segmentStruct = new SegmentSplitStruct();
        $segmentStruct->id_segment = (int)$id_segment;
        $segmentStruct->id_job = $id_job;

        $translationDao = new SplitDAO($this->getDatabase());
        $currSegmentInfo = $translationDao->read($segmentStruct);

        /**
         * Split check control
         */
        $isASplittedSegment = false;
        $isLastSegmentChunk = true;

        if (count($currSegmentInfo) > 0) {
            $isASplittedSegment = true;
            $currSegmentInfo = array_shift($currSegmentInfo);

            //get the chunk number and check whether it is the last one or not
            $sourceChunkLengths = ($currSegmentInfo !== null && is_array($currSegmentInfo->source_chunk_lengths)) ? $currSegmentInfo->source_chunk_lengths : [];
            $isLastSegmentChunk = ($split_num == count($sourceChunkLengths) - 1);

            if (!$isLastSegmentChunk) {
                $nextSegmentId = $id_segment . "-" . ((int)$split_num + 1);
            }
        }

        $id_segment_int = (int)$id_segment;

        /**
         * End Split check control
         */
        if (!$isASplittedSegment or $isLastSegmentChunk) {
            $segmentList = (new SegmentDao($this->getDatabase()))->getNextSegment($id_segment_int, $id_job, $password, $isRevision);

            if (!$isRevision) {
                $nextSegmentId = CatUtils::fetchStatus($id_segment_int, $segmentList);
            } else {
                $nextSegmentId = CatUtils::fetchStatus($id_segment_int, $segmentList, TranslationStatus::STATUS_TRANSLATED);
                if (!$nextSegmentId) {
                    $nextSegmentId = CatUtils::fetchStatus($id_segment_int, $segmentList, TranslationStatus::STATUS_APPROVED);
                }
            }
        }

        $this->response->json([
            'code' => 1,
            'errors' => [],
            'data' => [],
            'nextSegmentId' => $nextSegmentId ?? null,
        ]);
    }

    /**
     * @return array{id_segment: string, split_num: string|null}
     * @throws InvalidArgumentException
     */
    private function validateTheRequest(): array
    {
        $id_segment = filter_var($this->request->param('id_segment'), FILTER_SANITIZE_NUMBER_INT);

        if (empty($id_segment)) {
            throw new InvalidArgumentException("No id segment provided", -3);
        }

        $segment = explode("-", $id_segment);
        $id_segment = $segment[0];
        $split_num = $segment[1] ?? null;

        return [
            'id_segment' => $id_segment,
            'split_num' => $split_num,
        ];
    }
}
