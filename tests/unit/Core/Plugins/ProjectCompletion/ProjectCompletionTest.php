<?php

namespace Matecat\Core\Plugins\ProjectCompletion;

use Matecat\TestHelpers\AbstractTest;
use Model\ChunksCompletion\ChunkCompletionEventDao;
use Model\ChunksCompletion\ChunkCompletionUpdateDao;
use Model\ChunksCompletion\ChunkCompletionUpdateStruct;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FeaturesBase\Hook\Event\Run\JobPasswordChangedEvent;
use Model\FeaturesBase\Hook\Event\Run\PostAddSegmentTranslationEvent;
use Model\Jobs\JobStruct;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\ProjectCompletion;
use ReflectionClass;
use ReflectionMethod;

class ProjectCompletionTest extends AbstractTest
{
    private function makeFeature(
        ?ChunkCompletionEventDao $eventDao = null,
        ?ChunkCompletionUpdateDao $updateDao = null,
    ): ProjectCompletion {
        return new ProjectCompletion(
            new BasicFeatureStruct(['feature_code' => ProjectCompletion::FEATURE_CODE]),
            $eventDao ?? $this->createStub(ChunkCompletionEventDao::class),
            $updateDao ?? $this->createStub(ChunkCompletionUpdateDao::class),
        );
    }

    private function makeChunk(?int $id = 1, ?string $password = 'pwd'): JobStruct
    {
        return new JobStruct([
            'id' => $id,
            'id_project' => 100,
            'password' => $password,
            'job_first_segment' => 1,
            'job_last_segment' => 10,
            'source' => 'en-US',
            'target' => 'it-IT',
            'create_date' => '2026-01-01 00:00:00',
            'last_update' => '2026-01-01 00:00:00',
        ]);
    }

    private function makeTranslationEvent(JobStruct $chunk, bool $isReview, ?object $loggedUser = null): PostAddSegmentTranslationEvent
    {
        $context = [
            'is_review' => $isReview,
            'chunk' => $chunk,
        ];

        if ($loggedUser !== null) {
            $context['logged_user'] = $loggedUser;
        }

        return new PostAddSegmentTranslationEvent($context);
    }

    #[Test]
    public function postAddSegmentTranslationSavesWhenPhaseMatchesTranslate(): void
    {
        $chunk = $this->makeChunk();
        $eventDao = $this->createStub(ChunkCompletionEventDao::class);
        $eventDao->method('currentPhase')->willReturn(ChunkCompletionEventDao::TRANSLATE);

        $updateDao = $this->createMock(ChunkCompletionUpdateDao::class);
        $updateDao->expects($this->once())
            ->method('createOrUpdateFromStruct')
            ->with($this->isInstanceOf(ChunkCompletionUpdateStruct::class))
            ->willReturn(true);

        $feature = $this->makeFeature($eventDao, $updateDao);
        $feature->postAddSegmentTranslation($this->makeTranslationEvent($chunk, false));
    }

    #[Test]
    public function postAddSegmentTranslationSavesWhenPhaseMatchesReview(): void
    {
        $chunk = $this->makeChunk();
        $eventDao = $this->createStub(ChunkCompletionEventDao::class);
        $eventDao->method('currentPhase')->willReturn(ChunkCompletionEventDao::REVISE);

        $updateDao = $this->createMock(ChunkCompletionUpdateDao::class);
        $updateDao->expects($this->once())
            ->method('createOrUpdateFromStruct')
            ->with($this->isInstanceOf(ChunkCompletionUpdateStruct::class))
            ->willReturn(true);

        $feature = $this->makeFeature($eventDao, $updateDao);
        $feature->postAddSegmentTranslation($this->makeTranslationEvent($chunk, true));
    }

    #[Test]
    public function postAddSegmentTranslationSkipsSaveWhenPhaseMismatches(): void
    {
        $chunk = $this->makeChunk();
        $eventDao = $this->createStub(ChunkCompletionEventDao::class);
        $eventDao->method('currentPhase')->willReturn(ChunkCompletionEventDao::TRANSLATE);

        $updateDao = $this->createMock(ChunkCompletionUpdateDao::class);
        $updateDao->expects($this->never())->method('createOrUpdateFromStruct');

        $feature = $this->makeFeature($eventDao, $updateDao);
        $feature->postAddSegmentTranslation($this->makeTranslationEvent($chunk, true));
    }

    #[Test]
    public function postAddSegmentTranslationSetsUidWhenLoggedUserPresent(): void
    {
        $chunk = $this->makeChunk();
        $loggedUser = (object) ['uid' => 42];

        $eventDao = $this->createStub(ChunkCompletionEventDao::class);
        $eventDao->method('currentPhase')->willReturn(ChunkCompletionEventDao::TRANSLATE);

        $updateDao = $this->createMock(ChunkCompletionUpdateDao::class);
        $updateDao->expects($this->once())
            ->method('createOrUpdateFromStruct')
            ->with($this->callback(function (ChunkCompletionUpdateStruct $struct) {
                return $struct->uid === 42;
            }))
            ->willReturn(true);

        $feature = $this->makeFeature($eventDao, $updateDao);
        $feature->postAddSegmentTranslation($this->makeTranslationEvent($chunk, false, $loggedUser));
    }

    #[Test]
    public function postAddSegmentTranslationThrowsOnMissingChunkId(): void
    {
        $chunk = $this->makeChunk(id: null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Job id is required');

        $feature = $this->makeFeature();
        $feature->postAddSegmentTranslation($this->makeTranslationEvent($chunk, false));
    }

    #[Test]
    public function jobPasswordChangedUpdatesBothDaos(): void
    {
        $job = $this->makeChunk(id: 10, password: 'new_pw');

        $updateDao = $this->createMock(ChunkCompletionUpdateDao::class);
        $updateDao->expects($this->once())
            ->method('updatePassword')
            ->with(10, 'new_pw', 'old_pw');

        $eventDao = $this->createMock(ChunkCompletionEventDao::class);
        $eventDao->expects($this->once())
            ->method('updatePassword')
            ->with(10, 'new_pw', 'old_pw');

        $feature = $this->makeFeature($eventDao, $updateDao);
        $feature->jobPasswordChanged(new JobPasswordChangedEvent($job, 'old_pw'));
    }

    #[Test]
    public function jobPasswordChangedThrowsOnMissingJobId(): void
    {
        $job = $this->makeChunk(id: null, password: 'pw');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Job id is required');

        $feature = $this->makeFeature();
        $feature->jobPasswordChanged(new JobPasswordChangedEvent($job, 'old_pw'));
    }

    #[Test]
    public function jobPasswordChangedThrowsOnMissingPassword(): void
    {
        $job = $this->makeChunk(id: 1, password: null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Job password is required');

        $feature = $this->makeFeature();
        $feature->jobPasswordChanged(new JobPasswordChangedEvent($job, 'old_pw'));
    }

    #[Test]
    public function lazilyBuildsDaosFromInjectedDatabaseNotSingleton(): void
    {
        $injected  = $this->createStub(IDatabase::class);
        $singleton = $this->createStub(IDatabase::class);

        // Poison the singleton with a *different* instance so a stray Database::obtain()
        // would be observably distinct from the injected handler.
        $prop = (new ReflectionClass(Database::class))->getProperty('instance');
        $prop->setValue(null, $singleton);
        $this->databaseMockApplied = true;

        $feature = new ProjectCompletion(
            new BasicFeatureStruct(['feature_code' => ProjectCompletion::FEATURE_CODE])
        );
        $feature->setDatabase($injected);

        $eventDao  = (new ReflectionMethod(ProjectCompletion::class, 'chunkCompletionEventDao'))->invoke($feature);
        $updateDao = (new ReflectionMethod(ProjectCompletion::class, 'chunkCompletionUpdateDao'))->invoke($feature);

        $this->assertInstanceOf(ChunkCompletionEventDao::class, $eventDao);
        $this->assertInstanceOf(ChunkCompletionUpdateDao::class, $updateDao);
        $this->assertSame($injected, $eventDao->getDatabaseHandler());
        $this->assertSame($injected, $updateDao->getDatabaseHandler());
        $this->assertNotSame($singleton, $eventDao->getDatabaseHandler());
    }
}
