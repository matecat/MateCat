<?php

namespace Model\LQA;

use Exception;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\Jobs\JobDao;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentStruct;
use ReflectionException;

class EntryValidator
{

    public ?SegmentStruct $segment = null;
    public ?ProjectStruct $project = null;
    public ?ModelStruct $qa_model = null;
    public ?CategoryStruct $category = null;

    /** @var array<array{0: null|string, 1: string}> */
    protected array $errors = [];

    protected EntryStruct $struct;

    protected bool $validated = false;

    private SegmentDao $segmentDao;
    private JobDao $jobDao;
    private ProjectDao $projectDao;
    private ModelDao $modelDao;
    private CategoryDao $categoryDao;

    public function __construct(
        EntryStruct $struct,
        ?SegmentDao $segmentDao = null,
        ?JobDao $jobDao = null,
        ?ProjectDao $projectDao = null,
        ?ModelDao $modelDao = null,
        ?CategoryDao $categoryDao = null
    ) {
        $this->struct      = $struct;
        $this->segmentDao  = $segmentDao  ?? new SegmentDao(Database::obtain());
        $this->jobDao      = $jobDao      ?? new JobDao();
        $this->projectDao  = $projectDao  ?? new ProjectDao();
        $this->modelDao    = $modelDao    ?? new ModelDao();
        $this->categoryDao = $categoryDao ?? new CategoryDao();
    }

    /** @return array<array{0: null|string, 1: string}> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function flushErrors(): void
    {
        $this->errors = [];
    }

    /** @return list<string> */
    public function getErrorMessages(): array
    {
        return array_values(array_map(function ($item) {
            return implode(' ', array_filter($item, fn($v) => $v !== null));
        }, $this->errors));
    }

    public function getErrorsAsString(): string
    {
        return implode(', ', $this->getErrorMessages());
    }

    /**
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws ValidationError
     * @throws Exception
     */
    public function ensureValid(): void
    {
        if (!$this->validated && !$this->isValid()) {
            throw new ValidationError($this->getErrorsAsString());
        }
    }

    /**
     * @throws ReflectionException
     * @throws NotFoundException
     * @throws Exception
     */
    public function isValid(): bool
    {
        $this->flushErrors();
        $this->validate();
        $errors = $this->getErrors();
        $this->validated = true;

        return empty($errors);
    }

    /**
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws Exception
     */
    public function validate(): void
    {
        $this->segment = $this->segmentDao->fetchById($this->struct->id_segment, SegmentStruct::class);

        if (!$this->segment) {
            throw new NotFoundException('segment not found');
        }

        $jobs = $this->jobDao->getNotDeletedById($this->struct->id_job);
        $job  = $jobs[0] ?? throw new NotFoundException('job not found');

        $this->project = $this->projectDao->findById($job->id_project)
            ?? throw new NotFoundException('project not found');

        $this->validateCategoryId();
        $this->validateInSegmentScope();
    }

    private function validateInSegmentScope(): void
    {
        if ($this->struct->id) {
            if ($this->segment === null || $this->struct->id_segment != $this->segment->id) {
                $this->errors[] = [null, 'issue not found'];
            }
        }
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    private function validateCategoryId(): void
    {
        if ($this->project === null || $this->project->id_qa_model === null) {
            $this->errors[] = [null, 'QA model id not found'];
            return;
        }

        $this->qa_model  = $this->modelDao->fetchById($this->project->id_qa_model, ModelStruct::class)
            ?? throw new NotFoundException('QA model not found');
        $this->category  = $this->categoryDao->fetchById($this->struct->id_category, CategoryStruct::class)
            ?? throw new NotFoundException('category not found');

        if ($this->category->id_model != $this->qa_model->id) {
            $this->errors[] = [null, 'QA model id mismatch'];
        }
    }
}
