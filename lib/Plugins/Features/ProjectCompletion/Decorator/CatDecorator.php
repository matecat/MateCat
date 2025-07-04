<?php

namespace Features\ProjectCompletion\Decorator;

use CatUtils;
use Controller\Views\TemplateDecorator\AbstractDecorator;
use Controller\Views\TemplateDecorator\Arguments\ArgumentInterface;
use Controller\Views\TemplateDecorator\Arguments\CatDecoratorArguments;
use Exception;
use Model\ChunksCompletion\ChunkCompletionEventDao;
use Model\Projects\MetadataDao;

class CatDecorator extends AbstractDecorator {

    private array $stats;

    private string $current_phase;
    /**
     * @var CatDecoratorArguments|null
     */
    private ?CatDecoratorArguments $arguments;

    /**
     * @param CatDecoratorArguments|null $arguments
     *
     * @return void
     * @throws Exception
     */
    public function decorate( ?ArgumentInterface $arguments = null ) {

        $this->arguments = $arguments;
        $job             = $this->arguments->getJob();

        $this->stats = CatUtils::getFastStatsForJob( $this->arguments->getWordCountStruct() );
        $completed   = $job->isMarkedComplete( [ 'is_review' => $this->arguments->isRevision() ] );

        $lastCompletionEvent = ChunkCompletionEventDao::lastCompletionRecord(
                $job, [ 'is_review' => $this->arguments->isRevision() ]
        );

        $dao                 = new ChunkCompletionEventDao();
        $this->current_phase = $dao->currentPhase( $this->arguments->getJob() );

        $this->template->{'project_completion_feature_enabled'} = true;
        $this->template->{'header_main_button_id'}              = 'markAsCompleteButton';
        $this->template->{'job_completion_current_phase'}       = $this->current_phase;

        if ( $lastCompletionEvent ) {
            $this->template->{'job_completion_last_event_id'} = $lastCompletionEvent[ 'id_event' ];
        }

        if ( $completed ) {
            $this->varsForComplete();
        } else {
            $this->varsForUncomplete();
        }

    }

    private function varsForUncomplete() {
        $this->template->{'job_marked_complete'}      = false;
        $this->template->{'header_main_button_class'} = 'notMarkedComplete';

        if ( $this->completable() ) {
            $this->template->{'header_main_button_enabled'}      = true;
            $this->template->{'mark_as_complete_button_enabled'} = true;
            $this->template->{'header_main_button_class'}        = " isMarkableAsComplete";
        } else {
            $this->template->{'mark_as_complete_button_enabled'} = false;
            $this->template->{'header_main_button_enabled'}      = false;
        }
    }

    private function varsForComplete() {
        $this->template->{'job_marked_complete'}             = true;
        $this->template->{'header_main_button_class'}        = 'isMarkedComplete';
        $this->template->{'header_main_button_enabled'}      = false;
        $this->template->{'mark_as_complete_button_enabled'} = false;
    }

    private function completable(): bool {

        if ( $this->arguments->getJob()->getProject()->getWordCountType() != MetadataDao::WORD_COUNT_RAW ) {

            if ( $this->arguments->isRevision() ) {
                $completable = $this->current_phase == ChunkCompletionEventDao::REVISE &&
                        $this->stats[ 'DRAFT' ] == 0 &&
                        ( $this->stats[ 'APPROVED' ] + $this->stats[ 'REJECTED' ] ) > 0;
            } else {
                $completable = $this->current_phase == ChunkCompletionEventDao::TRANSLATE &&
                        $this->stats[ 'DRAFT' ] == 0 && $this->stats[ 'REJECTED' ] == 0;
            }

        } else {

            if ( $this->arguments->isRevision() ) {
                $completable = $this->current_phase == ChunkCompletionEventDao::REVISE &&
                        $this->stats[ 'raw' ][ 'draft' ] == 0 && $this->stats[ 'raw' ][ 'new' ] == 0 &&
                        ( $this->stats[ 'raw' ][ 'approved' ] + $this->stats[ 'raw' ][ 'approved2' ] + $this->stats[ 'raw' ][ 'rejected' ] ) > 0;
            } else {
                $completable = $this->current_phase == ChunkCompletionEventDao::TRANSLATE &&
                        $this->stats[ 'raw' ][ 'draft' ] == 0 &&
                        $this->stats[ 'raw' ][ 'new' ] == 0 &&
                        $this->stats[ 'raw' ][ 'rejected' ] == 0;
            }

        }

        return $completable;
    }

}
