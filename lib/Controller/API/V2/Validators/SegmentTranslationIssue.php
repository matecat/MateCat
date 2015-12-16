<?php

namespace API\V2\Validators  ;

use API\V2\ValidationError ;
use API\V2\NotFoundError ;

class SegmentTranslationIssue {

    private $request;

    public $segment;
    public $project;
    public $chunk ;
    public $translation ;
    public $qa_model ;
    public $category ;
    public $issue ;

    public function __construct( $request ) {
        $this->request = $request;
    }

    public function validate() {
        $this->validateResourcePresence();
        $this->validateRequestParams();
    }

    private function validateResourcePresence() {
        $dao = new \Segments_SegmentDao( \Database::obtain() );
        $this->segment = $dao->getByChunkIdAndSegmentId(
            $this->request->id_job,
            $this->request->password,
            $this->request->id_segment
        );

        if (!$this->segment) throw new NotFoundError('segment not found');

        $this->chunk = \Chunks_ChunkDao::getByIdAndPassword(
            $this->request->id_job,
            $this->request->password
        );

        $this->project = \Projects_ProjectDao::findById(
            $this->chunk->id_project
        );
    }

    private function validateRequestParams() {
        $this->validateCategoryId();

        $this->translation = $this->segment->findTranslation( $this->request->id_job ) ;

        // IF an issue_id is provided check it's in the segment scope
        if ( $this->request->id_issue ) {
            $this->issue = \LQA\EntryDao::findById( $this->request->id_issue );

            if ( !$this->issue ) {
                throw new ValidationError('issue not found');
            }

            if ( $this->issue->id_segment != $this->segment->id ) {
                throw new ValidationError('issue not found');
            }
        }
    }

    private function validateCategoryId() {
        if ( $this->request->id_category ) {
            $this->qa_model = \LQA\ModelDao::findById( $this->project->id_qa_model );
            $this->category = \LQA\CategoryDao::findById( $this->request->id_category );

            if ( $this->category->id_model != $this->qa_model->id ) {
                throw new ValidationError('QA model id mismatch');
            }
        }
    }
}
