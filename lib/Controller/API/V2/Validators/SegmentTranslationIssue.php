<?php

namespace API\V2\Validators;


use API\V2\Exceptions\ValidationError;
use LQA\ChunkReviewDao;
use LQA\ChunkReviewStruct;

class SegmentTranslationIssue extends Base {

    public $issue ;
    /**
     * @var \Translations_SegmentTranslationStruct
     */
    public $translation ;

    /**
     * @var \Segments_SegmentStruct
     */
    public $segment ;

    private $parent_validator ;

    /**
     * @var ChunkReviewStruct
     */
    protected $chunk_review ;

    /**
     * @return mixed|void
     * @throws ValidationError
     * @throws \Exception
     */
    public function _validate() {
        // if method is delete we expect the password to be revise password.

        if ( $this->request->method('delete') ) {
            $this->chunk_review = ChunkReviewDao::findByReviewPasswordAndJobId( $this->request->password, $this->request->id_job );
            if ( ! $this->chunk_review ) {
                throw new ValidationError('Record not found') ;
            }
            $password = $this->chunk_review->password ;
        }
        else {
            $password = $this->request->password ;
        }

        $this->parent_validator = new SegmentTranslation( $this->request );
        $this->parent_validator->setPassword( $password ) ;
        $this->parent_validator->validate();

        $this->translation = $this->parent_validator->translation ;

        $this->segment = $this->parent_validator->segment ;

        if ( $this->request->id_issue ) {
            $this->ensureIssueIsInScope();
        }

    }

    public function getChunkReview() {
        return $this->chunk_review ;
    }

    private function ensureIssueIsInScope() {
        $this->issue = \LQA\EntryDao::findById( $this->request->id_issue );

        if ( !$this->issue ) {
            throw new ValidationError('issue not found');
        }

        if ( $this->issue->id_segment != $this->translation->id_segment ) {
            throw new ValidationError('issue not found');
        }
    }
}
