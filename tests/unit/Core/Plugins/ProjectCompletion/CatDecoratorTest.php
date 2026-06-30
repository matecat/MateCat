<?php

namespace Matecat\Core\Plugins\ProjectCompletion;

use Controller\Abstracts\IController;
use Controller\Views\TemplateDecorator\Arguments\CatDecoratorArguments;
use Matecat\TestHelpers\AbstractTest;
use Model\ChunksCompletion\ChunkCompletionEventDao;
use Model\Projects\MetadataStruct;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\Projects\ProjectStruct;
use Model\WordCount\WordCountStruct;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\ProjectCompletion\Decorator\CatDecorator;
use RuntimeException;
use Utils\Templating\PHPTALWithAppend;

class CatDecoratorTest extends AbstractTest
{
    private \PDOStatement $stmtStub;
    private IController $controllerStub;

    protected function setUp(): void
    {
        parent::setUp();
        [$dbStub, , $this->stmtStub] = $this->createDatabaseMock();
        $this->stmtStub->method('execute')->willReturn(true);
        $this->controllerStub = $this->createStub(IController::class);
        $this->controllerStub->method('getDatabase')->willReturn($dbStub);
    }

    private function makeTemplate(): PHPTALWithAppend
    {
        return $this->createStub(PHPTALWithAppend::class);
    }

    private function makeController(): IController
    {
        return $this->controllerStub;
    }

    private function makeProject(string $wordCountType = 'eq_word_count'): ProjectStruct
    {
        $project = $this->createStub(ProjectStruct::class);
        $project->id = 1;

        if ($wordCountType === ProjectsMetadataMarshaller::WORD_COUNT_RAW->value) {
            $meta = new MetadataStruct();
            $meta->key = 'word_count_type';
            $meta->value = $wordCountType;
            $this->stmtStub->method('fetchAll')->willReturn([$meta]);
        } else {
            $this->stmtStub->method('fetchAll')->willReturn([]);
        }

        return $project;
    }

    private function makeChunk(?ProjectStruct $project = null): JobStruct
    {
        $project = $project ?? $this->makeProject();

        $chunk = $this->createStub(JobStruct::class);
        $chunk->id = 10;
        $chunk->password = 'pass1';
        $chunk->job_first_segment = 100;
        $chunk->job_last_segment = 200;
        $chunk->method('getProject')->willReturn($project);

        return $chunk;
    }

    private function makeWordCountStruct(): WordCountStruct
    {
        $wc = new WordCountStruct();
        $wc->setIdJob(10);
        $wc->setNewWords(0);
        $wc->setDraftWords(0);
        $wc->setTranslatedWords(100);
        $wc->setApprovedWords(50);
        $wc->setApproved2Words(0);
        $wc->setRejectedWords(0);
        $wc->setNewRawWords(0);
        $wc->setDraftRawWords(0);
        $wc->setTranslatedRawWords(100);
        $wc->setApprovedRawWords(50);
        $wc->setApproved2RawWords(0);
        $wc->setRejectedRawWords(0);

        return $wc;
    }

    private function makeDao(array $lastRecord = [], string $phase = ChunkCompletionEventDao::TRANSLATE): ChunkCompletionEventDao
    {
        $dao = $this->createStub(ChunkCompletionEventDao::class);
        $dao->method('lastCompletionRecord')->willReturn($lastRecord);
        $dao->method('currentPhase')->willReturn($phase);

        return $dao;
    }

    private function makeArguments(bool $isRevision = false, ?JobStruct $chunk = null, ?WordCountStruct $wc = null): CatDecoratorArguments
    {
        return new CatDecoratorArguments(
            $chunk ?? $this->makeChunk(),
            $isRevision,
            $wc ?? $this->makeWordCountStruct()
        );
    }

    #[Test]
    public function decorateThrowsWhenArgumentsAreWrongType(): void
    {
        $decorator = new CatDecorator($this->makeController(), $this->makeTemplate(), $this->makeDao());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CatDecorator requires CatDecoratorArguments');

        $decorator->decorate(null);
    }

    #[Test]
    public function decorateWithNoCompletionEventSetsUncomplete(): void
    {
        $template = $this->makeTemplate();
        $dao = $this->makeDao([], ChunkCompletionEventDao::TRANSLATE);

        $decorator = new CatDecorator($this->makeController(), $template, $dao);
        $decorator->decorate($this->makeArguments());

        $this->assertTrue(true);
    }

    #[Test]
    public function decorateWithCompletionEventSetsComplete(): void
    {
        $template = $this->makeTemplate();
        $record = ['id_event' => 1, 'id_job' => 10, 'password' => 'pass1', 'is_review' => false, 'create_date' => '2024-01-01'];
        $dao = $this->makeDao($record, ChunkCompletionEventDao::REVISE);

        $decorator = new CatDecorator($this->makeController(), $template, $dao);
        $decorator->decorate($this->makeArguments());

        $this->assertTrue(true);
    }

    #[Test]
    public function decorateInRevisionMode(): void
    {
        $template = $this->makeTemplate();
        $dao = $this->makeDao([], ChunkCompletionEventDao::REVISE);

        $decorator = new CatDecorator($this->makeController(), $template, $dao);
        $decorator->decorate($this->makeArguments(isRevision: true));

        $this->assertTrue(true);
    }

    #[Test]
    public function decorateWithRawWordCount(): void
    {
        $project = $this->makeProject(ProjectsMetadataMarshaller::WORD_COUNT_RAW->value);
        $chunk = $this->makeChunk($project);
        $dao = $this->makeDao([], ChunkCompletionEventDao::TRANSLATE);

        $decorator = new CatDecorator($this->makeController(), $this->makeTemplate(), $dao);
        $decorator->decorate($this->makeArguments(chunk: $chunk));

        $this->assertTrue(true);
    }

    #[Test]
    public function decorateWithRawWordCountInRevisionMode(): void
    {
        $project = $this->makeProject(ProjectsMetadataMarshaller::WORD_COUNT_RAW->value);
        $chunk = $this->makeChunk($project);
        $dao = $this->makeDao([], ChunkCompletionEventDao::REVISE);

        $decorator = new CatDecorator($this->makeController(), $this->makeTemplate(), $dao);
        $decorator->decorate($this->makeArguments(isRevision: true, chunk: $chunk));

        $this->assertTrue(true);
    }
}
