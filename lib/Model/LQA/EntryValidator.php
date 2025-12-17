<?php

namespace Model\LQA;

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

    public ?SegmentStruct $segment;
    public ?ProjectStruct $project;
    public ?ModelStruct $qa_model = null;
    public ?CategoryStruct $category = null;

    protected array $errors = [];

    protected EntryStruct $struct;

    protected bool $validated = false;

    public function __construct(EntryStruct $struct)
    {
        $this->struct = $struct;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function flushErrors(): void
    {
        $this->errors = [];
    }

    public function getErrorMessages(): array
    {
        return array_map(function ($item) {
            return implode(' ', $item);
        }, $this->errors);
    }

    public function getErrorsAsString(): string
    {
        return implode(', ', $this->getErrorMessages());
    }

    /**
     * @throws NotFoundException
     * @throws ReflectionException
     * @throws ValidationError
     */
    public function ensureValid(): void
    {
        if (!$this->validated && !$this->isValid()) {
            throw new ValidationError ($this->getErrorsAsString());
        }
    }

    /**
     * @throws ReflectionException
     * @throws NotFoundException
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
     */

    public function validate(): void
    {
        $dao = new SegmentDao(Database::obtain());
        $this->segment = $dao->getById($this->struct->id_segment);

        if (!$this->segment) {
            throw new NotFoundException('segment not found');
        }

        $job = JobDao::getById($this->struct->id_job)[0];
        $this->project = ProjectDao::findById($job->id_project);

        $this->validateCategoryId();
        $this->validateInSegmentScope();
    }

    private function validateInSegmentScope(): void
    {
        if ($this->struct->id) {
            if ($this->struct->id_segment != $this->segment->id) {
                $this->errors[] = [null, 'issue not found'];
            }
        }
    }

    /**
     * @throws ReflectionException
     */
    private function validateCategoryId(): void
    {
        if ($this->project->id_qa_model === null) {
            $this->errors[] = [null, 'QA model id not found'];

            return;
        }

        $this->qa_model = ModelDao::findById($this->project->id_qa_model);
        $this->category = CategoryDao::findById($this->struct->id_category);

        if ($this->category->id_model != $this->qa_model->id) {
            $this->errors[] = [null, 'QA model id mismatch'];
        }
    }
}
