<?php

namespace Plugins\Features\ProjectCompletion\Decorator;

use Controller\Abstracts\IController;
use Controller\Views\TemplateDecorator\AbstractDecorator;
use Controller\Views\TemplateDecorator\Arguments\ArgumentInterface;
use Controller\Views\TemplateDecorator\Arguments\CatDecoratorArguments;
use DivisionByZeroError;
use DomainException;
use Exception;
use Model\ChunksCompletion\ChunkCompletionEventDao;
use Model\Projects\MetadataDao;
use Model\Projects\ProjectsMetadataMarshaller;
use RuntimeException;
use Utils\Templating\PHPTALWithAppend;
use Utils\Templating\PHPTalBoolean;
use Utils\Tools\CatUtils;

class CatDecorator extends AbstractDecorator
{

    /** @var array<string, mixed> */
    private array $stats;

    private string $current_phase;

    private CatDecoratorArguments $arguments;

    private ChunkCompletionEventDao $chunkCompletionEventDao;

    public function __construct(
        IController $controller,
        PHPTALWithAppend $template,
        ?ChunkCompletionEventDao $chunkCompletionEventDao = null
    ) {
        parent::__construct($controller, $template);
        $this->chunkCompletionEventDao = $chunkCompletionEventDao ?? new ChunkCompletionEventDao();
    }

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

        $this->stats = (new CatUtils())->getFastStatsForJob($this->arguments->getWordCountStruct());

        $lastCompletionEvent = $this->chunkCompletionEventDao->lastCompletionRecord($job, ['is_review' => $this->arguments->isRevision()]);

        $this->current_phase = $this->chunkCompletionEventDao->currentPhase($this->arguments->getJob());

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
     * @throws Exception
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
     * @throws Exception
     */
    private function completable(): bool
    {
        $project = $this->arguments->getJob()->getProject();
        $wordCountMeta = (new MetadataDao($this->controller->getDatabase()))
            ->setCacheTTL(3600)
            ->get((int) $project->id, ProjectsMetadataMarshaller::WORD_COUNT_TYPE_KEY->value);
        $wordCountType = $wordCountMeta !== null ? $wordCountMeta->value : ProjectsMetadataMarshaller::WORD_COUNT_EQUIVALENT->value;
        if ($wordCountType != ProjectsMetadataMarshaller::WORD_COUNT_RAW->value) {
            if ($this->arguments->isRevision()) {
                $completable = $this->current_phase == ChunkCompletionEventDao::REVISE &&
                    ($this->stats['DRAFT'] ?? 0) == 0 &&
                    (($this->stats['APPROVED'] ?? 0) + ($this->stats['REJECTED'] ?? 0)) > 0;
            } else {
                $completable = $this->current_phase == ChunkCompletionEventDao::TRANSLATE &&
                    ($this->stats['DRAFT'] ?? 0) == 0 && ($this->stats['REJECTED'] ?? 0) == 0;
            }
        } elseif ($this->arguments->isRevision()) {
            $completable = $this->current_phase == ChunkCompletionEventDao::REVISE &&
                ($this->stats['raw']['draft'] ?? 0) == 0 && ($this->stats['raw']['new'] ?? 0) == 0 &&
                (($this->stats['raw']['approved'] ?? 0) + ($this->stats['raw']['approved2'] ?? 0) + ($this->stats['raw']['rejected'] ?? 0)) > 0;
        } else {
            $completable = $this->current_phase == ChunkCompletionEventDao::TRANSLATE &&
                ($this->stats['raw']['draft'] ?? 0) == 0 &&
                ($this->stats['raw']['new'] ?? 0) == 0 &&
                ($this->stats['raw']['rejected'] ?? 0) == 0;
        }

        return $completable;
    }

}
