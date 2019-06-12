<?php

namespace API\V2\Validators;


use API\V2\Exceptions\ValidationError;
use Constants;
use Exception;
use Features\SecondPassReview\Utils;
use Features\TranslationVersions\Model\SegmentTranslationEventDao;
use LQA\ChunkReviewDao;
use LQA\ChunkReviewStruct;
use LQA\EntryStruct;

class SegmentTranslationIssue extends Base {

    /**
     * @var EntryStruct
     */
    public $issue ;
    /**
     * @var \Translations_SegmentTranslationStruct
     */
    public $translation ;

    /**
     * @var \Segments_SegmentStruct
     */
    public $segment ;

    /**
     * @var SegmentTranslation
     */
    private $parent_validator ;

    /**
     * @var ChunkReviewStruct
     */
    protected $chunk_review ;

    /**
     * @return mixed|void
     * @throws ValidationError
     * @throws Exception
     */
    public function _validate() {
        // if method is delete we expect the password to be revise password.
        // TODO: this should be changed because revision password should always be used

        if ( $this->request->method('delete') ) {
            $this->chunk_review = ChunkReviewDao::findByReviewPasswordAndJobId( $this->request->password, $this->request->id_job );
            if ( ! $this->chunk_review ) {
                throw new ValidationError('Revision record not found') ;
            }
            $password = $this->chunk_review->password ;
        }
        else {
            $this->chunk_review = ChunkReviewDao::findOneChunkReviewByIdJobAndPassword( $this->request->id_job, $this->request->password );
            $password = $this->request->password ;
        }

        if ( is_null( $this->chunk_review ) ) {
            throw new ValidationError('Revision record not found.') ;
        }

        $this->parent_validator = new SegmentTranslation( $this->request );
        $this->parent_validator->setPassword( $password ) ;
        $this->parent_validator->validate();

        $this->translation = $this->parent_validator->translation ;

        $this->segment = $this->parent_validator->segment ;

        if ( $this->request->id_issue ) {
            $this->__ensureIssueIsInScope();
        }

        if ( $this->request->method('post') && $this->request->revision_number ) {
            $this->__ensureSegmentRevisionIsCompatibileWithIssueRevisionNumber();
        }
        elseif ( $this->request->method('delete') ) {
            $this->__ensureRevisionPasswordAllowsDeleteForIssue();
        }

    }

    public function getChunkReview() {
        return $this->chunk_review ;
    }

    private function __ensureRevisionPasswordAllowsDeleteForIssue() {
        if ( $this->issue->source_page != $this->chunk_review->source_page ) {
            throw new ValidationError('Not enough privileges to delete this issue') ;
        }
    }

    /**
     *
     * @throws Exception
     * @throws ValidationError
     */
    private function __ensureSegmentRevisionIsCompatibileWithIssueRevisionNumber() {
        $latestSegmentEvent = ( new SegmentTranslationEventDao() )
                ->getLatestEventForSegment( $this->chunk_review->id_job, $this->segment->id );

        if ( !$latestSegmentEvent && $this->translation->isICE() ) {
            throw new ValidationError('Cannot set issues on unmodified ICE.') ;
        }
        elseif ( $latestSegmentEvent->source_page != Utils::revisionNumberToSourcePage( $this->request->revision_number ) ) {
            // Can latest event be missing here? Actually yes, for example in case we are setting an issue on
            // a locked ice match, which never received a submit from the UI. How do we handle that case?
            // No reviewed words yet an issue. That's not possible, we need to ensure the reviewed words
            // are set, and reviewed words are set during setTranslation triggered callbacks.
            throw new ValidationError("Trying access segment issue for revision number " .
                    $this->request->revision_number . " but segment is not in same revision state.");
        }
        elseif ( !$latestSegmentEvent ) {
            throw new Exception('Unable to find the current state of this segment. Please report this issue to support.') ;
        }
    }

    private function __ensureIssueIsInScope() {
        $this->issue = \LQA\EntryDao::findById( $this->request->id_issue );

        if ( !$this->issue ) {
            throw new ValidationError('issue not found');
        }

        if ( $this->issue->id_segment != $this->translation->id_segment ) {
            throw new ValidationError('issue not found');
        }
    }
}
