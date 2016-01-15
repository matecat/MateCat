<?php

namespace Features\ProjectCompletion ;
use AbstractDecorator ;
use Features ;

class CatDecorator extends AbstractDecorator {

    private $stats;

    public function decorate() {
        $job = $this->controller->getJob();
        $this->stats = $this->controller->getJobStats();

        $this->template->project_completion_feature_enabled = true ;

        $this->template->header_main_button_id  = 'markAsCompleteButton' ;

        $completed = $job->isMarkedComplete( array('is_review' => $this->controller->isRevision() ) ) ;

        if ( $completed ) {
            $this->varsForComplete();
        }
        else {
            $this->varsForUncomplete();
        }
    }

    private function varsForUncomplete() {
        $this->template->header_main_button_label = 'SEND';
        $this->template->header_main_button_class = 'notMarkedComplete' ;

        if ( $this->completable()  ) {
            $this->template->header_main_button_enabled = true ;
            $this->template->header_main_button_class = " isMarkableAsComplete" ;
        } else {
            $this->template->header_main_button_enabled = false ;
        }
    }

    private function varsForComplete() {
        $this->template->header_main_button_label = 'SENT';
        $this->template->header_main_button_class = 'isMarkedComplete' ;
        $this->template->header_main_button_enabled =  false ;
    }

    private function completable() {
        if ($this->controller->isRevision()) {
            \Log::doLog( $this->stats );

            return $this->stats['DRAFT'] <= 0 &&
                $this->stats['TRANSLATED'] <= 0 &&
                ($this->stats['APPROVED'] + $this->stats['REJECTED']) > 0;
        }
        else {
            return $this->stats['DRAFT'] == 0 && $this->stats['REJECTED'] == 0 ;
        }
    }

}
