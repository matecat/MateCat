<?php

namespace Plugins\Features\ProjectCompletion\Decorator;

use Controller\Views\TemplateDecorator\AbstractDecorator;
use Controller\Views\TemplateDecorator\Arguments\ArgumentInterface;
use Controller\Views\TemplateDecorator\Arguments\CatDecoratorArguments;
use DivisionByZeroError;
use DomainException;
use Exception;
use Model\ChunksCompletion\ChunkCompletionEventDao;
use Model\Projects\ProjectsMetadataMarshaller;
use RuntimeException;
use Utils\Templating\PHPTalBoolean;
use Utils\Tools\CatUtils;

class CatDecorator extends AbstractDecorator
{

    /** @var array<string, mixed> */
    private array $stats;

    private string $current_phase;

    private CatDecoratorArguments $arguments;

    /**
     * @throws Exception
     * @throws DivisionByZeroError
     */
    public function decorate(?ArgumentInterface $arguments = null): void
    {
        if (!$arguments instanceof CatDecoratorArguments) {
            throw new RuntimeException('CatDecorator requires CatDecoratorArguments, got ' . get_debug_type($arguments));
        }

        $this->arguments = $arguments;
        $job = $this->arguments->getJob();

        $this->stats = CatUtils::getFastStatsForJob($this->arguments->getWordCountStruct());

        $lastCompletionEvent = ChunkCompletionEventDao::lastCompletionRecord($job, ['is_review' => $this->arguments->isRevision()]);

        $dao = new ChunkCompletionEventDao();
        $this->current_phase = $dao->currentPhase($this->arguments->getJob());

        $this->template->project_completion_feature_enabled = new PHPTalBoolean(true);
        $this->template->job_completion_current_phase = $this->current_phase;

        if ($lastCompletionEvent) {
            $this->template->job_completion_last_event_id = $lastCompletionEvent['id_event'];
            $this->varsForComplete();
        } else {
            $this->varsForUncomplete();
        }
    }

    /**
     * @throws DomainException
     */
    private function varsForUncomplete(): void
    {
        $this->template->job_marked_complete = new PHPTalBoolean(false);

        if ($this->completable()) {
            $this->template->mark_as_complete_button_enabled = new PHPTalBoolean(true);
        } else {
            $this->template->mark_as_complete_button_enabled = new PHPTalBoolean(false);
        }
    }

    private function varsForComplete(): void
    {
        $this->template->job_marked_complete = new PHPTalBoolean(true);
        $this->template->mark_as_complete_button_enabled = new PHPTalBoolean(false);
    }

    /**
     * @throws DomainException
     */
    private function completable(): bool
    {
        if ($this->arguments->getJob()->getProject()->getWordCountType() != ProjectsMetadataMarshaller::WORD_COUNT_RAW->value) {
            if ($this->arguments->isRevision()) {
                $completable = $this->current_phase == ChunkCompletionEventDao::REVISE &&
                    $this->stats['DRAFT'] == 0 &&
                    ($this->stats['APPROVED'] + $this->stats['REJECTED']) > 0;
            } else {
                $completable = $this->current_phase == ChunkCompletionEventDao::TRANSLATE &&
                    $this->stats['DRAFT'] == 0 && $this->stats['REJECTED'] == 0;
            }
        } elseif ($this->arguments->isRevision()) {
            $completable = $this->current_phase == ChunkCompletionEventDao::REVISE &&
                $this->stats['raw']['draft'] == 0 && $this->stats['raw']['new'] == 0 &&
                ($this->stats['raw']['approved'] + $this->stats['raw']['approved2'] + $this->stats['raw']['rejected']) > 0;
        } else {
            $completable = $this->current_phase == ChunkCompletionEventDao::TRANSLATE &&
                $this->stats['raw']['draft'] == 0 &&
                $this->stats['raw']['new'] == 0 &&
                $this->stats['raw']['rejected'] == 0;
        }

        return $completable;
    }

}
