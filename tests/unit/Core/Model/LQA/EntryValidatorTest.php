<?php

namespace Matecat\Core\Model\LQA;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\LQA\CategoryDao;
use Model\LQA\CategoryStruct;
use Model\LQA\EntryStruct;
use Model\LQA\EntryValidator;
use Model\LQA\ModelDao;
use Model\LQA\ModelStruct;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentStruct;
use PHPUnit\Framework\Attributes\Test;

class EntryValidatorTest extends AbstractTest
{
    private function makeStruct(int $idSegment = 1, int $idJob = 2, int $idCategory = 3): EntryStruct
    {
        $s = new EntryStruct();
        $s->id_segment  = $idSegment;
        $s->id_job      = $idJob;
        $s->id_category = $idCategory;
        $s->severity    = 'minor';
        $s->source_page = 1;
        return $s;
    }

    private function makeSegment(int $id = 1): SegmentStruct
    {
        $seg     = new SegmentStruct();
        $seg->id = $id;
        return $seg;
    }

    private function makeJob(int $idProject = 10): JobStruct
    {
        $job             = new JobStruct();
        $job->id_project = $idProject;
        return $job;
    }

    private function makeProject(?int $idQaModel = 5): ProjectStruct
    {
        $p              = new ProjectStruct();
        $p->id          = 10;
        $p->id_qa_model = $idQaModel;
        return $p;
    }

    private function makeQaModel(int $id = 5): ModelStruct
    {
        $m     = new ModelStruct();
        $m->id = $id;
        return $m;
    }

    private function makeCategory(int $id = 3, int $idModel = 5): CategoryStruct
    {
        $c           = new CategoryStruct();
        $c->id       = $id;
        $c->id_model = $idModel;
        return $c;
    }

    private function makeValidator(
        EntryStruct $struct,
        SegmentDao  $segmentDao,
        JobDao      $jobDao,
        ProjectDao  $projectDao,
        ModelDao    $modelDao,
        CategoryDao $categoryDao
    ): EntryValidator {
        return new EntryValidator($struct, obtainTestDatabase(), $segmentDao, $jobDao, $projectDao, $modelDao, $categoryDao);
    }

    // ─── isValid ────────────────────────────────────────────────────────

    #[Test]
    public function is_valid_returns_true_when_all_checks_pass(): void
    {
        $struct = $this->makeStruct(1, 2, 3);
        $struct->id = null; // new entry, not update

        $segmentDao  = $this->createStub(SegmentDao::class);
        $segmentDao->method('fetchById')->willReturn($this->makeSegment(1));

        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getNotDeletedById')->willReturn([$this->makeJob(10)]);

        $projectDao = $this->createStub(ProjectDao::class);
        $projectDao->method('findById')->willReturn($this->makeProject(5));

        $modelDao = $this->createStub(ModelDao::class);
        $modelDao->method('fetchById')->willReturn($this->makeQaModel(5));

        $categoryDao = $this->createStub(CategoryDao::class);
        $categoryDao->method('fetchById')->willReturn($this->makeCategory(3, 5));

        $validator = $this->makeValidator($struct, $segmentDao, $jobDao, $projectDao, $modelDao, $categoryDao);

        $this->assertTrue($validator->isValid());
        $this->assertEmpty($validator->getErrors());
    }

    #[Test]
    public function is_valid_returns_false_when_qa_model_mismatch(): void
    {
        $struct = $this->makeStruct(1, 2, 3);
        $struct->id = null;

        $segmentDao  = $this->createStub(SegmentDao::class);
        $segmentDao->method('fetchById')->willReturn($this->makeSegment(1));

        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getNotDeletedById')->willReturn([$this->makeJob(10)]);

        $projectDao = $this->createStub(ProjectDao::class);
        $projectDao->method('findById')->willReturn($this->makeProject(5));

        $modelDao = $this->createStub(ModelDao::class);
        $modelDao->method('fetchById')->willReturn($this->makeQaModel(5));

        $categoryDao = $this->createStub(CategoryDao::class);
        $categoryDao->method('fetchById')->willReturn($this->makeCategory(3, 99)); // id_model mismatch

        $validator = $this->makeValidator($struct, $segmentDao, $jobDao, $projectDao, $modelDao, $categoryDao);

        $this->assertFalse($validator->isValid());
        $this->assertNotEmpty($validator->getErrors());
    }

    #[Test]
    public function is_valid_returns_false_when_no_qa_model_on_project(): void
    {
        $struct = $this->makeStruct(1, 2, 3);
        $struct->id = null;

        $segmentDao  = $this->createStub(SegmentDao::class);
        $segmentDao->method('fetchById')->willReturn($this->makeSegment(1));

        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getNotDeletedById')->willReturn([$this->makeJob(10)]);

        $projectDao = $this->createStub(ProjectDao::class);
        $projectDao->method('findById')->willReturn($this->makeProject(null)); // no QA model

        $validator = $this->makeValidator(
            $struct, $segmentDao, $jobDao, $projectDao,
            $this->createStub(ModelDao::class),
            $this->createStub(CategoryDao::class)
        );

        $this->assertFalse($validator->isValid());
        $messages = $validator->getErrorMessages();
        $this->assertStringContainsString('QA model id not found', implode(' ', $messages));
    }

    // ─── ensureValid ─────────────────────────────────────────────────────

    #[Test]
    public function ensure_valid_throws_when_invalid(): void
    {
        $struct = $this->makeStruct(1, 2, 3);
        $struct->id = null;

        $segmentDao  = $this->createStub(SegmentDao::class);
        $segmentDao->method('fetchById')->willReturn($this->makeSegment(1));

        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getNotDeletedById')->willReturn([$this->makeJob(10)]);

        $projectDao = $this->createStub(ProjectDao::class);
        $projectDao->method('findById')->willReturn($this->makeProject(null));

        $validator = $this->makeValidator(
            $struct, $segmentDao, $jobDao, $projectDao,
            $this->createStub(ModelDao::class),
            $this->createStub(CategoryDao::class)
        );

        $this->expectException(ValidationError::class);
        $validator->ensureValid();
    }

    #[Test]
    public function ensure_valid_does_not_throw_when_valid(): void
    {
        $struct = $this->makeStruct(1, 2, 3);
        $struct->id = null;

        $segmentDao  = $this->createStub(SegmentDao::class);
        $segmentDao->method('fetchById')->willReturn($this->makeSegment(1));

        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getNotDeletedById')->willReturn([$this->makeJob(10)]);

        $projectDao = $this->createStub(ProjectDao::class);
        $projectDao->method('findById')->willReturn($this->makeProject(5));

        $modelDao = $this->createStub(ModelDao::class);
        $modelDao->method('fetchById')->willReturn($this->makeQaModel(5));

        $categoryDao = $this->createStub(CategoryDao::class);
        $categoryDao->method('fetchById')->willReturn($this->makeCategory(3, 5));

        $validator = $this->makeValidator($struct, $segmentDao, $jobDao, $projectDao, $modelDao, $categoryDao);

        $validator->ensureValid(); // must not throw
        $this->assertTrue(true);
    }

    // ─── validate() throws ───────────────────────────────────────────────

    #[Test]
    public function validate_throws_not_found_when_segment_missing(): void
    {
        $struct = $this->makeStruct(99, 2, 3);

        $segmentDao = $this->createStub(SegmentDao::class);
        $segmentDao->method('fetchById')->willReturn(null);

        $validator = $this->makeValidator(
            $struct, $segmentDao,
            $this->createStub(JobDao::class),
            $this->createStub(ProjectDao::class),
            $this->createStub(ModelDao::class),
            $this->createStub(CategoryDao::class)
        );

        $this->expectException(NotFoundException::class);
        $validator->validate();
    }

    #[Test]
    public function validate_throws_not_found_when_job_missing(): void
    {
        $struct = $this->makeStruct(1, 99, 3);

        $segmentDao = $this->createStub(SegmentDao::class);
        $segmentDao->method('fetchById')->willReturn($this->makeSegment(1));

        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getNotDeletedById')->willReturn([]); // empty

        $validator = $this->makeValidator(
            $struct, $segmentDao, $jobDao,
            $this->createStub(ProjectDao::class),
            $this->createStub(ModelDao::class),
            $this->createStub(CategoryDao::class)
        );

        $this->expectException(NotFoundException::class);
        $validator->validate();
    }

    #[Test]
    public function validate_throws_not_found_when_project_missing(): void
    {
        $struct = $this->makeStruct(1, 2, 3);

        $segmentDao = $this->createStub(SegmentDao::class);
        $segmentDao->method('fetchById')->willReturn($this->makeSegment(1));

        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getNotDeletedById')->willReturn([$this->makeJob(10)]);

        $projectDao = $this->createStub(ProjectDao::class);
        $projectDao->method('findById')->willReturn(null);

        $validator = $this->makeValidator(
            $struct, $segmentDao, $jobDao, $projectDao,
            $this->createStub(ModelDao::class),
            $this->createStub(CategoryDao::class)
        );

        $this->expectException(NotFoundException::class);
        $validator->validate();
    }

    // ─── error helpers ───────────────────────────────────────────────────

    #[Test]
    public function get_error_messages_returns_concatenated_strings(): void
    {
        $struct = $this->makeStruct(1, 2, 3);
        $struct->id = null;

        $segmentDao  = $this->createStub(SegmentDao::class);
        $segmentDao->method('fetchById')->willReturn($this->makeSegment(1));

        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getNotDeletedById')->willReturn([$this->makeJob(10)]);

        $projectDao = $this->createStub(ProjectDao::class);
        $projectDao->method('findById')->willReturn($this->makeProject(null));

        $validator = $this->makeValidator(
            $struct, $segmentDao, $jobDao, $projectDao,
            $this->createStub(ModelDao::class),
            $this->createStub(CategoryDao::class)
        );

        $validator->isValid();
        $this->assertNotEmpty($validator->getErrorMessages());
        $this->assertIsString($validator->getErrorsAsString());
    }

    #[Test]
    public function flush_errors_clears_error_list(): void
    {
        $struct = $this->makeStruct(1, 2, 3);
        $struct->id = null;

        $segmentDao  = $this->createStub(SegmentDao::class);
        $segmentDao->method('fetchById')->willReturn($this->makeSegment(1));

        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getNotDeletedById')->willReturn([$this->makeJob(10)]);

        $projectDao = $this->createStub(ProjectDao::class);
        $projectDao->method('findById')->willReturn($this->makeProject(null));

        $validator = $this->makeValidator(
            $struct, $segmentDao, $jobDao, $projectDao,
            $this->createStub(ModelDao::class),
            $this->createStub(CategoryDao::class)
        );

        $validator->isValid();
        $this->assertNotEmpty($validator->getErrors());
        $validator->flushErrors();
        $this->assertEmpty($validator->getErrors());
    }
}
