<?php

namespace API\V2\Validators;

use API\V2\ValidationError ;

class SegmentTranslationIssue extends Base {

    public $issue ;

    public function validate() {
        $segment_translation = new SegmentTranslation( $this->request );
        $segment_translation->validate();

        if ( $this->request->id_issue ) {
            $this->ensureIssueIsInScope();
        }
    }

    private function ensureIssueIsInScope() {
        $this->issue = \LQA\EntryDao::findById( $this->request->id_issue );

        if ( !$this->issue ) {
            throw new ValidationError('issue not found');
        }

        if ( $this->issue->id_segment != $this->segment->id ) {
            throw new ValidationError('issue not found');
        }
    }
}
