<?php

namespace LQA;

use Database;
use Exceptions\NotFoundException;
use Exceptions\ValidationError;
use Jobs_JobDao;
use Projects_ProjectDao;
use Segments_SegmentDao;

class EntryValidator {

    public $segment;
    public $project;
    public $chunk;
    public $translation;
    public $qa_model;
    public $category;
    public $issue;

    protected $errors = [];

    protected $struct;

    protected $validated = false;

    public function __construct( $struct ) {
        $this->struct = $struct;
    }

    public function getErrors() {
        return $this->errors;
    }

    public function flushErrors() {
        $this->errors = [];
    }

    public function getErrorMessages() {
        return array_map( function ( $item ) {
            return implode( ' ', $item );
        }, $this->errors );
    }

    public function getErrorsAsString() {
        return implode( ', ', $this->getErrorMessages() );
    }

    /**
     * @throws ValidationError
     */
    public function ensureValid() {
        if ( !$this->validated && !$this->isValid() ) {
            throw new ValidationError ( $this->getErrorsAsString() );
        }
    }

    public function isValid() {
        $this->flushErrors();
        $this->validate();
        $errors  = $this->getErrors();
        $this->validated = true;
        return empty( $errors );
    }

    /**
     * @throws NotFoundException
     */

    public function validate() {
        $dao           = new Segments_SegmentDao( Database::obtain() );
        $this->segment = $dao->getById( $this->struct->id_segment );

        if ( !$this->segment ) {
            throw new NotFoundException( 'segment not found' );
        }

        $this->job     = Jobs_JobDao::getById( $this->struct->id_job )[ 0 ];
        $this->project = Projects_ProjectDao::findById( $this->job->id_project );

        $this->validateCategoryId();
        $this->validateInSegmentScope();
    }

    private function validateInSegmentScope() {
        if ( $this->struct->id ) {
            if ( $this->struct->id_segment != $this->segment->id ) {
                $this->errors[] = [ null, 'issue not found' ];
            }
        }
    }

    private function validateCategoryId() {
        $this->qa_model = ModelDao::findById( $this->project->id_qa_model );
        $this->category = CategoryDao::findById( $this->struct->id_category );

        if ( $this->category->id_model != $this->qa_model->id ) {
            $this->errors[] = [ null, 'QA model id mismatch' ];
        }
    }
}
