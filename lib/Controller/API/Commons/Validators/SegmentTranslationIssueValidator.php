<?php

namespace Controller\API\Commons\Validators;


use Controller\API\Commons\Exceptions\ValidationError;
use Exception;
use Model\Exceptions\NotFoundException;
use Model\LQA\ChunkReviewStruct;
use Model\LQA\EntryDao;
use Model\LQA\EntryStruct;
use Model\LQA\EntryValidator;
use Model\Translations\SegmentTranslationStruct;
use PDOException;
use Plugins\Features\ReviewExtended\ReviewUtils;
use Plugins\Features\TranslationEvents\Model\TranslationEventDao;
use ReflectionException;
use Throwable;

class SegmentTranslationIssueValidator extends Base
{

    /**
     * @var ?EntryStruct
     */
    public ?EntryStruct $issue = null;
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

        $this->translation = $validator->translation ?? throw new ValidationError('Segment translation not found');

        if ($this->request->param('id_issue')) {
            $this->__ensureIssueIsInScope();
        }

        // The segment-state check is not run here: only the endpoints that write an issue need it,
        // and they invoke ensureSegmentRevisionMatchesCredentialPhase() themselves. Inferring it
        // from the HTTP verb would also apply it to comment creation, which does not write a phase.
        if ($this->request->httpMethod('delete')) {
            $this->__ensureRevisionPasswordAllowsDeleteForIssue();
        }
    }

    /**
     * @throws ValidationError
     */
    protected function __ensureRevisionPasswordAllowsDeleteForIssue(): void
    {
        if ($this->issue === null) {
            throw new ValidationError('Issue not found');
        }

        if ($this->issue->source_page > $this->chunkReview->source_page) {
            throw new ValidationError('Not enough privileges to delete this issue');
        }
    }

    /**
     * A segment may only receive an issue for the phase it currently sits in, and that phase is the
     * one the presented password resolved to.
     *
     * @throws Exception
     * @throws ValidationError
     */
    public function ensureSegmentRevisionMatchesCredentialPhase(): void
    {
        $latestSegmentEvent = (new TranslationEventDao($this->controller->getDatabase()))->getLatestEventForSegment($this->chunkReview->id_job, $this->translation->id_segment);

        if (!$latestSegmentEvent) {
            if ($this->translation->isICE() || $this->translation->isPreTranslated()) {
                throw new ValidationError('Cannot set issues on unmodified ICE.', -2000);
            }

            // Can latest event be missing here? Actually yes, for example in case we are setting an issue on
            // a locked ice match, which never received a submit from the UI. How do we handle that case?
            // No reviewed words yet an issue. That's not possible, we need to ensure the reviewed words
            // are set, and reviewed words are set during setTranslation triggered callbacks.
            throw new Exception('Unable to find the current state of this segment. Please report this issue to support.');
        }

        // The phase to compare against is the one the presented password resolved to, not one the
        // client declared: a caller holding one phase's review password must not be able to act on
        // another phase's segments (GHSA-7q94-2fmr-3p42).
        if ($latestSegmentEvent->source_page != $this->chunkReview->source_page) {
            throw new ValidationError(
                "Trying access segment issue for revision number " .
                ReviewUtils::sourcePageToRevisionNumber($this->chunkReview->source_page) .
                " but segment is not in same revision state."
            );
        }
    }

    /**
     * @throws ValidationError
     * @throws NotFoundException
     * @throws \Model\Exceptions\ValidationError
     * @throws ReflectionException
     * @throws PDOException
     * @throws Exception
     */
    protected function __ensureIssueIsInScope(): void
    {
        $this->issue = (new EntryDao($this->controller->getDatabase()))->findById($this->request->param('id_issue'));

        if (!$this->issue) {
            throw new ValidationError('Issue not found');
        }

        if ($this->issue->id_segment != $this->translation->id_segment) {
            throw new ValidationError('Issue not found');
        }

        $this->issue->ensureValid(new EntryValidator($this->issue, database: $this->controller->getDatabase()));
    }
}
