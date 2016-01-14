<?php

namespace API\V2\Validators;

use API\V2\ValidationError ;

class SegmentTranslationIssue extends Base {

    public $issue ;
    public $translation ;

    private $parent_validator ;

    public function validate() {
        $this->parent_validator = new SegmentTranslation( $this->request );
        $this->parent_validator->validate();

        $this->translation = $this->parent_validator->translation ;

        if ( $this->request->id_issue ) {
            $this->ensureIssueIsInScope();
        }

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
