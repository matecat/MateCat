<?php

namespace Controller\API\Commons\Validators;


use Controller\API\Commons\Exceptions\ValidationError;
use Exception;
use Model\Exceptions\NotFoundException;
use Model\LQA\ChunkReviewStruct;
use Model\LQA\EntryDao;
use Model\LQA\EntryStruct;
use Model\Translations\SegmentTranslationStruct;
use Plugins\Features\ReviewExtended\ReviewUtils;
use Plugins\Features\TranslationEvents\Model\TranslationEventDao;
use ReflectionException;
use Throwable;

class SegmentTranslationIssueValidator extends Base
{

    /**
     * @var ?EntryStruct
     */
    public ?EntryStruct $issue;
    /**
     * @var SegmentTranslationStruct
     */
    public SegmentTranslationStruct $translation;

    /**
     * @var ChunkReviewStruct
     */
    protected ChunkReviewStruct $chunkReview;

    /**
     * @param ChunkReviewStruct $chunkReviewStruct
     *
     * @return $this
     */
    public function setChunkReview(ChunkReviewStruct $chunkReviewStruct): SegmentTranslationIssueValidator
    {
        $this->chunkReview = $chunkReviewStruct;

        return $this;
    }

    /**
     * @return void
     * @throws Throwable
     * @throws ValidationError
     */
    public function _validate(): void
    {
        //load validator for the segment translation
        $validator = (new SegmentTranslation($this->controller));
        $validator->validate();

        $this->translation = $validator->translation;

        if ($this->request->param('id_issue')) {
            $this->__ensureIssueIsInScope();
        }

        if ($this->request->httpMethod('post') && $this->request->param('revision_number')) {
            $this->__ensureSegmentRevisionIsCompatibleWithIssueRevisionNumber();
        } elseif ($this->request->httpMethod('delete')) {
            $this->__ensureRevisionPasswordAllowsDeleteForIssue();
        }
    }

    /**
     * @throws ValidationError
     */
    protected function __ensureRevisionPasswordAllowsDeleteForIssue(): void
    {
        if ($this->issue->source_page > $this->chunkReview->source_page) {
            throw new ValidationError('Not enough privileges to delete this issue');
        }
    }

    /**
     *
     * @throws Exception
     * @throws ValidationError
     */
    protected function __ensureSegmentRevisionIsCompatibleWithIssueRevisionNumber(): void
    {
        $latestSegmentEvent = (new TranslationEventDao())->getLatestEventForSegment($this->chunkReview->id_job, $this->translation->id_segment);

        if (!$latestSegmentEvent && ($this->translation->isICE() || $this->translation->isPreTranslated())) {
            throw new ValidationError('Cannot set issues on unmodified ICE.', -2000);
        } elseif ($latestSegmentEvent->source_page != ReviewUtils::revisionNumberToSourcePage($this->request->param('revision_number'))) {
            // Can latest event be missing here? Actually yes, for example in case we are setting an issue on
            // a locked ice match, which never received a submit from the UI. How do we handle that case?
            // No reviewed words yet an issue. That's not possible, we need to ensure the reviewed words
            // are set, and reviewed words are set during setTranslation triggered callbacks.
            throw new ValidationError(
                    "Trying access segment issue for revision number " .
                    $this->request->param('revision_number') . " but segment is not in same revision state."
            );
        } elseif (!$latestSegmentEvent) {
            throw new Exception('Unable to find the current state of this segment. Please report this issue to support.');
        }
    }

    /**
     * @throws ValidationError
     * @throws NotFoundException
     * @throws \Model\Exceptions\ValidationError
     * @throws ReflectionException
     */
    protected function __ensureIssueIsInScope(): void
    {
        $this->issue = EntryDao::findById($this->request->param('id_issue'));

        if (!$this->issue) {
            throw new ValidationError('issue not found');
        }

        if ($this->issue->id_segment != $this->translation->id_segment) {
            throw new ValidationError('issue not found');
        }

        $this->issue->ensureValid();
    }
}
