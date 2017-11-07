<?php

namespace Features\ProjectCompletion\Decorator ;
use AbstractDecorator ;
use catController;
use Features ;
use Chunks_ChunkCompletionEventDao  ;

class CatDecorator extends AbstractDecorator {

    /** @var  catController  */
    protected $controller;

    private $stats;

    private $current_phase  ;
    
    public function decorate() {
        $job = $this->controller->getChunk();

        $this->stats = $this->controller->getJobStats();
        $completed = $job->isMarkedComplete( array('is_review' => $this->controller->isRevision() ) ) ;

        $lastCompletionEvent = Chunks_ChunkCompletionEventDao::lastCompletionRecord(
                $job, ['is_review' => $this->controller->isRevision() ]
        );

        $dao = new \Chunks_ChunkCompletionEventDao();
        $this->current_phase = $dao->currentPhase( $this->controller->getChunk() );

        $this->template->project_completion_feature_enabled = true ;
        $this->template->header_main_button_id  = 'markAsCompleteButton' ;
        $this->template->job_completion_current_phase = $this->current_phase ;

        if ( $lastCompletionEvent ) {
            $this->template->job_completion_last_event_id = $lastCompletionEvent['id_event'] ;
        }

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
            $completed = $this->current_phase == Chunks_ChunkCompletionEventDao::REVISE &&
                    $this->stats['DRAFT'] == 0 &&
                    ( $this->stats['APPROVED'] + $this->stats['REJECTED'] ) > 0;
        }
        else {
            $completable =  $this->current_phase == Chunks_ChunkCompletionEventDao::TRANSLATE &&
                    $this->stats['DRAFT'] == 0 && $this->stats['REJECTED'] == 0 ;
        }

        $completable = $this->controller->getChunk()->getProject()->getFeatures()->filter('filterJobCompletable', $completable,
                $this->controller->getChunk(),
                $this->controller->getLoggedUser(),
                catController::isRevision()
        );

        return $completable ;
    }

}
