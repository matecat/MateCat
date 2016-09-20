<?php

namespace Features\ProjectCompletion\Decorator ;
use AbstractDecorator ;
use Features ;

class CatDecorator extends AbstractDecorator {

    /** @var  \catController  */
    protected $controller;

    private $stats;
    
    public function decorate() {
        $job = $this->controller->getJob();
        $this->stats = $this->controller->getJobStats();

        $this->template->project_completion_feature_enabled = true ;

        $this->template->header_main_button_id  = 'markAsCompleteButton' ;

        $completed = $job->isMarkedComplete( array('is_review' => $this->controller->isRevision() ) ) ;

        $dao = new \Chunks_ChunkCompletionEventDao();
        $this->template->job_completion_current_phase = $dao->currentPhase( $this->controller->getJob() );

        if ( $completed ) {
            $this->varsForComplete();
        }
        else {
            $this->varsForUncomplete();
        }
    }

    private function varsForUncomplete() {
        $this->template->job_marked_complete = false;
        $this->template->header_main_button_label = 'Mark as complete';
        $this->template->header_main_button_class = 'notMarkedComplete' ;

        if ( $this->completable()  ) {
            $this->template->header_main_button_enabled = true ;
            $this->template->header_main_button_class = " isMarkableAsComplete" ;
        } else {
            $this->template->header_main_button_enabled = false ;
        }
    }

    private function varsForComplete() {
        $this->template->job_marked_complete = true ;
        $this->template->header_main_button_label = 'Marked as complete';
        $this->template->header_main_button_class = 'isMarkedComplete' ;
        $this->template->header_main_button_enabled =  false ;
    }

    private function completable() {
        if ($this->controller->isRevision()) {
            return $this->stats['DRAFT'] == 0 &&
                ($this->stats['APPROVED'] + $this->stats['REJECTED']) > 0;
        }
        else {
            return $this->stats['DRAFT'] == 0 && $this->stats['REJECTED'] == 0 ;
        }
    }

}
