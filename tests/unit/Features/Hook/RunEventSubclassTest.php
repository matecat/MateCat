<?php

declare(strict_types=1);

namespace Tests\Unit\Features\Hook;

use Controller\Features\ProjectCompletion\CompletionEventStruct;
use Model\ChunksCompletion\ChunkCompletionEventStruct;
use Model\FeaturesBase\Hook\Event\Run\AlterChunkReviewStructEvent;
use Model\FeaturesBase\Hook\Event\Run\BeforeProjectCreationEvent;
use Model\FeaturesBase\Hook\Event\Run\ChunkReviewUpdatedEvent;
use Model\FeaturesBase\Hook\Event\Run\FilterProjectNameModifiedEvent;
use Model\FeaturesBase\Hook\Event\Run\JobPasswordChangedEvent;
use Model\FeaturesBase\Hook\Event\Run\PostAddSegmentTranslationEvent;
use Model\FeaturesBase\Hook\Event\Run\PostJobMergedEvent;
use Model\FeaturesBase\Hook\Event\Run\PostJobSplittedEvent;
use Model\FeaturesBase\Hook\Event\Run\PostProjectCreateEvent;
use Model\FeaturesBase\Hook\Event\Run\ProjectCompletionEventSavedEvent;
use Model\FeaturesBase\Hook\Event\Run\ReviewPasswordChangedEvent;
use Model\FeaturesBase\Hook\Event\Run\SetTranslationCommittedEvent;
use Model\FeaturesBase\Hook\Event\Run\TmAnalysisDisabledEvent;
use Model\FeaturesBase\Hook\Event\Run\ValidateJobCreationEvent;
use Model\FeaturesBase\Hook\Event\Run\ValidateProjectCreationEvent;
use Model\FeaturesBase\Hook\RunEvent;
use Model\Jobs\JobStruct;
use Model\JobSplitMerge\SplitMergeProjectData;
use Model\LQA\ChunkReviewStruct;
use Model\ProjectCreation\ProjectStructure;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use ReflectionClass;

class RunEventSubclassTest extends AbstractTest
{
    #[Test]
    #[DataProvider('hookNameProvider')]
    public function allRunEventsReturnCorrectHookName(string $class, string $expectedHookName): void
    {
        self::assertSame($expectedHookName, $class::hookName());
    }

    #[Test]
    #[DataProvider('hookNameProvider')]
    public function allRunEventsAreFinalAndExtendRunEvent(string $class, string $_hookName): void
    {
        $reflection = new ReflectionClass($class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isSubclassOf(RunEvent::class));
    }

    public static function hookNameProvider(): array
    {
        return [
            'PostProjectCreate' => [PostProjectCreateEvent::class, 'postProjectCreate'],
            'BeforeProjectCreation' => [BeforeProjectCreationEvent::class, 'beforeProjectCreation'],
            'ProjectCompletionEventSaved' => [ProjectCompletionEventSavedEvent::class, 'projectCompletionEventSaved'],
            'PostJobSplitted' => [PostJobSplittedEvent::class, 'postJobSplitted'],
            'PostAddSegmentTranslation' => [PostAddSegmentTranslationEvent::class, 'postAddSegmentTranslation'],
            'AlterChunkReviewStruct' => [AlterChunkReviewStructEvent::class, 'alterChunkReviewStruct'],
            'ReviewPasswordChanged' => [ReviewPasswordChangedEvent::class, 'reviewPasswordChanged'],
            'ChunkReviewUpdated' => [ChunkReviewUpdatedEvent::class, 'chunkReviewUpdated'],
            'JobPasswordChanged' => [JobPasswordChangedEvent::class, 'jobPasswordChanged'],
            'ValidateJobCreation' => [ValidateJobCreationEvent::class, 'validateJobCreation'],
            'FilterProjectNameModified' => [FilterProjectNameModifiedEvent::class, 'filterProjectNameModified'],
            'TmAnalysisDisabled' => [TmAnalysisDisabledEvent::class, 'tmAnalysisDisabled'],
            'ValidateProjectCreation' => [ValidateProjectCreationEvent::class, 'validateProjectCreation'],
            'PostJobMerged' => [PostJobMergedEvent::class, 'postJobMerged'],
            'SetTranslationCommitted' => [SetTranslationCommittedEvent::class, 'setTranslationCommitted'],
        ];
    }

    // ── Individual constructor / property tests ──────────────────────────

    #[Test]
    public function postProjectCreateEventExposesProjectStructure(): void
    {
        $ps = new ProjectStructure([]);
        $event = new PostProjectCreateEvent($ps);

        self::assertSame($ps, $event->projectStructure);
    }

    #[Test]
    public function beforeProjectCreationEventExposesProjectStructureAndContext(): void
    {
        $ps = new ProjectStructure([]);
        $context = ['key' => 'value'];
        $event = new BeforeProjectCreationEvent($ps, $context);

        self::assertSame($ps, $event->projectStructure);
        self::assertSame($context, $event->context);
    }

    #[Test]
    public function projectCompletionEventSavedEventExposesAllProperties(): void
    {
        $chunk = new JobStruct();
        $chunk->id = 5;

        $completionEvent = new CompletionEventStruct();
        $completionEvent->uid = 1;
        $completionEvent->source = 'user';
        $completionEvent->is_review = false;

        $event = new ProjectCompletionEventSavedEvent($chunk, $completionEvent, 42);

        self::assertSame($chunk, $event->chunk);
        self::assertSame($completionEvent, $event->event);
        self::assertSame(42, $event->completionEventId);
    }

    #[Test]
    public function postJobSplittedEventExposesData(): void
    {
        $data = new SplitMergeProjectData(1, 'customer_1');
        $event = new PostJobSplittedEvent($data);

        self::assertSame($data, $event->data);
    }

    #[Test]
    public function postAddSegmentTranslationEventExposesContext(): void
    {
        $context = ['id_segment' => 123, 'translation' => 'Ciao'];
        $event = new PostAddSegmentTranslationEvent($context);

        self::assertSame($context, $event->context);
    }

    #[Test]
    public function alterChunkReviewStructEventExposesEvent(): void
    {
        $completionEvent = new ChunkCompletionEventStruct();
        $completionEvent->id_project = 1;
        $completionEvent->id_job = 2;
        $completionEvent->password = 'abc';
        $completionEvent->source = ChunkCompletionEventStruct::SOURCE_USER;
        $completionEvent->job_first_segment = 1;
        $completionEvent->job_last_segment = 10;
        $completionEvent->create_date = '2024-01-01';
        $completionEvent->is_review = false;

        $event = new AlterChunkReviewStructEvent($completionEvent);

        self::assertSame($completionEvent, $event->event);
    }

    #[Test]
    public function reviewPasswordChangedEventExposesAllProperties(): void
    {
        $event = new ReviewPasswordChangedEvent(99, 'old_pass', 'new_pass', 2);

        self::assertSame(99, $event->jobId);
        self::assertSame('old_pass', $event->oldPassword);
        self::assertSame('new_pass', $event->newPassword);
        self::assertSame(2, $event->revisionNumber);
    }

    #[Test]
    public function chunkReviewUpdatedEventExposesAllProperties(): void
    {
        $chunkReview = new ChunkReviewStruct();
        $chunkReview->id_project = 1;
        $chunkReview->id_job = 2;
        $chunkReview->password = 'pass';
        $chunkReview->source_page = 1;

        $project = new ProjectStruct();
        $updateResult = ['affected_rows' => 1];
        $model = new \stdClass();

        $event = new ChunkReviewUpdatedEvent($chunkReview, $updateResult, $model, $project);

        self::assertSame($chunkReview, $event->chunkReview);
        self::assertSame($updateResult, $event->updateResult);
        self::assertSame($model, $event->model);
        self::assertSame($project, $event->project);
    }

    #[Test]
    public function jobPasswordChangedEventExposesJobAndOldPassword(): void
    {
        $job = new JobStruct();
        $job->id = 10;
        $event = new JobPasswordChangedEvent($job, 'old_pwd');

        self::assertSame($job, $event->job);
        self::assertSame('old_pwd', $event->oldPassword);
    }

    #[Test]
    public function validateJobCreationEventExposesJobAndProjectStructure(): void
    {
        $job = new JobStruct();
        $job->id = 7;
        $ps = new ProjectStructure([]);
        $event = new ValidateJobCreationEvent($job, $ps);

        self::assertSame($job, $event->job);
        self::assertSame($ps, $event->projectStructure);
    }

    #[Test]
    public function filterProjectNameModifiedEventExposesAllProperties(): void
    {
        $event = new FilterProjectNameModifiedEvent(42, 'New Name', 'pass123', 'owner@test.com');

        self::assertSame(42, $event->idProject);
        self::assertSame('New Name', $event->name);
        self::assertSame('pass123', $event->password);
        self::assertSame('owner@test.com', $event->ownerEmail);
    }

    #[Test]
    public function tmAnalysisDisabledEventExposesProjectId(): void
    {
        $event = new TmAnalysisDisabledEvent(55);

        self::assertSame(55, $event->projectId);
    }

    #[Test]
    public function validateProjectCreationEventExposesProjectStructure(): void
    {
        $ps = new ProjectStructure([]);
        $event = new ValidateProjectCreationEvent($ps);

        self::assertSame($ps, $event->projectStructure);
    }

    #[Test]
    public function postJobMergedEventExposesDataAndChunk(): void
    {
        $data = new SplitMergeProjectData(3, 'cust');
        $chunk = new JobStruct();
        $chunk->id = 8;
        $event = new PostJobMergedEvent($data, $chunk);

        self::assertSame($data, $event->data);
        self::assertSame($chunk, $event->chunk);
    }

    #[Test]
    public function setTranslationCommittedEventExposesContext(): void
    {
        $context = ['id_job' => 1, 'password' => 'pwd'];
        $event = new SetTranslationCommittedEvent($context);

        self::assertSame($context, $event->context);
    }
}
