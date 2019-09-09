<?php

namespace LQA;

use Exceptions\NotFoundException;

class EntryValidator extends \DataAccess_AbstractValidator {

    public $segment;
    public $project;
    public $chunk ;
    public $translation ;
    public $qa_model ;
    public $category ;
    public $issue ;

    /**
     * @throws \Exceptions\NotFoundException
     */

    public function validate() {
        $dao = new \Segments_SegmentDao( \Database::obtain() );
        $this->segment = $dao->getById( $this->struct->id_segment );

        if (!$this->segment) throw new NotFoundException('segment not found');

        $this->job = \Jobs_JobDao::getById( $this->struct->id_job)[0];
        $this->project = \Projects_ProjectDao::findById($this->job->id_project);

        $this->validateCategoryId();
        $this->validateInSegmentScope();
    }

    private function validateInSegmentScope() {
        if ( $this->struct->id ) {
            $this->issue = \LQA\EntryDao::findById( $this->struct->id );

            if ( !$this->issue ) {
                $this->errors[] = array(null, 'issue not found');
            }

            if ( $this->issue->id_segment != $this->segment->id ) {
                $this->errors[] = array(null, 'issue not found');
            }
        }
    }

    private function validateCategoryId() {
        $this->qa_model = \LQA\ModelDao::findById( $this->project->id_qa_model );
        $this->category = \LQA\CategoryDao::findById( $this->struct->id_category );

        if ( $this->category->id_model != $this->qa_model->id ) {
            $this->errors[] = array(null, 'QA model id mismatch');
        }
    }
}
