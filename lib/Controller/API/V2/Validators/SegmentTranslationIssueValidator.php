<?php

namespace API\V2\Validators;


use API\V2\Exceptions\ValidationError;
use Exception;
use Features\ReviewExtended\ReviewUtils;
use Features\TranslationVersions\Model\TranslationEventDao;
use LQA\ChunkReviewStruct;
use LQA\EntryDao;
use LQA\EntryStruct;
use Translations_SegmentTranslationStruct;

class SegmentTranslationIssueValidator extends Base {

    /**
     * @var EntryStruct
     */
    public $issue;
    /**
     * @var Translations_SegmentTranslationStruct
     */
    public $translation;

    /**
     * @var ChunkReviewStruct
     */
    protected $chunk_review;

    /**
     * @param ChunkReviewStruct $chunkReviewStruct
     *
     * @return $this
     */
    public function setChunkReview( ChunkReviewStruct $chunkReviewStruct ) {
        $this->chunk_review = $chunkReviewStruct;

        return $this;
    }

    /**
     * @return mixed|void
     * @throws ValidationError
     * @throws Exception
     */
    public function _validate() {

        //load validator for the segment translation
        $validator = ( new SegmentTranslation( $this->request ) );
        $validator->validate();

        $this->translation = $validator->translation;

        if ( $this->request->id_issue ) {
            $this->__ensureIssueIsInScope();
        }

        if ( $this->request->method( 'post' ) && $this->request->revision_number ) {
            $this->__ensureSegmentRevisionIsCompatibleWithIssueRevisionNumber();
        } elseif ( $this->request->method( 'delete' ) ) {
            $this->__ensureRevisionPasswordAllowsDeleteForIssue();
        }

    }

    public function getChunkReview() {
        return $this->chunk_review;
    }

    /**
     * @throws ValidationError
     */
    protected function __ensureRevisionPasswordAllowsDeleteForIssue() {
        if ( $this->issue->source_page > $this->chunk_review->source_page ) {
            throw new ValidationError( 'Not enough privileges to delete this issue' );
        }
    }

    /**
     *
     * @throws Exception
     * @throws ValidationError
     */
    protected function __ensureSegmentRevisionIsCompatibleWithIssueRevisionNumber() {

        $latestSegmentEvent = ( new TranslationEventDao() )->getLatestEventForSegment( $this->chunk_review->id_job, $this->translation->id_segment );

        if ( !$latestSegmentEvent && ( $this->translation->isICE() || $this->translation->isPreTranslated()) ) {
            throw new ValidationError( 'Cannot set issues on unmodified ICE.', -2000 );
        } elseif ( $latestSegmentEvent->source_page != ReviewUtils::revisionNumberToSourcePage( $this->request->revision_number ) ) {
            // Can latest event be missing here? Actually yes, for example in case we are setting an issue on
            // a locked ice match, which never received a submit from the UI. How do we handle that case?
            // No reviewed words yet an issue. That's not possible, we need to ensure the reviewed words
            // are set, and reviewed words are set during setTranslation triggered callbacks.
            throw new ValidationError( "Trying access segment issue for revision number " .
                    $this->request->revision_number . " but segment is not in same revision state." );
        } elseif ( !$latestSegmentEvent ) {
            throw new Exception( 'Unable to find the current state of this segment. Please report this issue to support.' );
        }
    }

    /**
     * @throws ValidationError
     */
    protected function __ensureIssueIsInScope() {
        $this->issue = EntryDao::findById( $this->request->id_issue );

        if ( !$this->issue ) {
            throw new ValidationError( 'issue not found' );
        }

        if ( $this->issue->id_segment != $this->translation->id_segment ) {
            throw new ValidationError( 'issue not found' );
        }

        $this->issue->ensureValid();

    }
}
