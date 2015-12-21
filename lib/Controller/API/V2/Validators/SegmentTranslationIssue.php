<?php

namespace API\V2\Validators  ;

use API\V2\ValidationError ;
use API\V2\NotFoundError ;

class SegmentTranslationIssue {

    private $request;

    public $segment;
    public $translation ;
    public $issue ;

    public function __construct( $request ) {
        $this->request = $request;
    }

    public function validate() {
        $this->ensureSegmentExists();
        $this->ensureTranslationExists();

        if ( $this->request->id_issue ) {
            $this->ensureSegmentIsInScope();
        }
    }

    private function ensureTranslationExists() {
        $this->translation = \Translations_SegmentTranslationDao::
            findBySegmentAndJob( $this->request->id_segment, $this->request->id_job  );
        if ( !$this->translation ) {
            throw new NotFoundError('translation not found');
        }
    }
    private function ensureSegmentExists() {
        $dao = new \Segments_SegmentDao( \Database::obtain() );
        $this->segment = $dao->getByChunkIdAndSegmentId(
            $this->request->id_job,
            $this->request->password,
            $this->request->id_segment
        );

        if (!$this->segment) throw new NotFoundError('segment not found');
    }

    private function ensureSegmentIsInScope() {
        $this->issue = \LQA\EntryDao::findById( $this->request->id_issue );

        if ( !$this->issue ) {
            throw new ValidationError('issue not found');
        }

        if ( $this->issue->id_segment != $this->segment->id ) {
            throw new ValidationError('issue not found');
        }
    }
}
