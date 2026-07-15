<?php

namespace Matecat\Core\Search;

use Matecat\TestHelpers\AbstractTest;
use Model\Search\ReplaceEventDAOInterface;
use Model\Search\ReplaceEventIndexDaoInterface;
use Model\Search\ReplaceEventStruct;
use Model\Translations\SegmentTranslationDao;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use ReflectionParameter;
use Utils\Search\ReplaceHistory;

class ReplaceHistoryTest extends AbstractTest
{
    private ReplaceEventDAOInterface $eventDao;
    private ReplaceEventIndexDaoInterface $indexDao;
    private SegmentTranslationDao $segmentTranslationDao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventDao = $this->createStub(ReplaceEventDAOInterface::class);
        $this->indexDao = $this->createStub(ReplaceEventIndexDaoInterface::class);
        $this->segmentTranslationDao = $this->createStub(SegmentTranslationDao::class);
    }

    private function makeHistory(int $idJob = 1, int $ttl = 0): ReplaceHistory
    {
        return new ReplaceHistory($idJob, $this->eventDao, $this->indexDao, $this->segmentTranslationDao, $ttl);
    }

    #[Test]
    public function constructorRequiresInjectedSegmentTranslationDao(): void
    {
        $params = (new ReflectionMethod(ReplaceHistory::class, '__construct'))->getParameters();
        $daoParam = array_values(array_filter(
            $params,
            static fn(ReflectionParameter $p): bool => $p->getName() === 'segmentTranslationDao'
        ))[0] ?? null;

        $this->assertNotNull($daoParam, 'ReplaceHistory must accept an injected $segmentTranslationDao');
        $this->assertSame(SegmentTranslationDao::class, (string)$daoParam->getType(), '$segmentTranslationDao must be typed SegmentTranslationDao');
        $this->assertFalse($daoParam->isOptional(), '$segmentTranslationDao must be mandatory');
        $this->assertFalse($daoParam->allowsNull(), '$segmentTranslationDao must be non-nullable');
    }

    #[Test]
    public function constructorSetsTtlOnDaos(): void
    {
        $eventDao = $this->createMock(ReplaceEventDAOInterface::class);
        $eventDao->expects($this->once())->method('setTtl')->with(600);

        $indexDao = $this->createMock(ReplaceEventIndexDaoInterface::class);
        $indexDao->expects($this->once())->method('setTtl')->with(600);

        new ReplaceHistory(1, $eventDao, $indexDao, $this->segmentTranslationDao, 600);
    }

    #[Test]
    public function constructorSkipsTtlWhenZero(): void
    {
        $eventDao = $this->createMock(ReplaceEventDAOInterface::class);
        $eventDao->expects($this->never())->method('setTtl');

        $indexDao = $this->createMock(ReplaceEventIndexDaoInterface::class);
        $indexDao->expects($this->never())->method('setTtl');

        new ReplaceHistory(1, $eventDao, $indexDao, $this->segmentTranslationDao, 0);
    }

    #[Test]
    public function getReturnsEventsFromDao(): void
    {
        $event = new ReplaceEventStruct();
        $event->id_job = 1;
        $event->replace_version = '3';

        $this->eventDao->method('getEvents')->willReturn([$event]);

        $result = $this->makeHistory()->get(3);

        $this->assertCount(1, $result);
        $this->assertSame($event, $result[0]);
    }

    #[Test]
    public function getCursorReturnsIndexFromDao(): void
    {
        $this->indexDao->method('getActualIndex')->willReturn(5);

        $history = new ReplaceHistory(42, $this->eventDao, $this->indexDao, $this->segmentTranslationDao);
        $this->assertSame(5, $history->getCursor());
    }

    #[Test]
    public function saveCallsDaoSave(): void
    {
        $event = new ReplaceEventStruct();
        $event->id_job = 1;
        $this->eventDao->method('save')->willReturn(1);

        $result = $this->makeHistory()->save($event);
        $this->assertSame(1, $result);
    }

    #[Test]
    public function redoReturnsZeroWhenNoEvents(): void
    {
        $this->indexDao->method('getActualIndex')->willReturn(2);
        $this->eventDao->method('getEvents')->willReturn([]);

        $this->assertSame(0, $this->makeHistory()->redo());
    }

    #[Test]
    public function undoReturnsZeroWhenNoEvents(): void
    {
        $this->indexDao->method('getActualIndex')->willReturn(0);
        $this->eventDao->method('getEvents')->willReturn([]);

        $this->assertSame(0, $this->makeHistory()->undo());
    }

    #[Test]
    public function redoRebuildsEventsAndAdvancesCursorWhenEventsExist(): void
    {
        $event = new ReplaceEventStruct();
        $event->id_job = 7;
        $event->replace_version = '3';

        $this->eventDao->method('getEvents')->willReturn([$event]);

        $indexDao = $this->createMock(ReplaceEventIndexDaoInterface::class);
        $indexDao->method('getActualIndex')->willReturn(2);
        $indexDao->expects($this->once())->method('save')->with(7, 3);

        $segmentTranslationDao = $this->createMock(SegmentTranslationDao::class);
        $segmentTranslationDao->expects($this->once())
            ->method('rebuildFromReplaceEvents')
            ->with([$event])
            ->willReturn(5);

        $history = new ReplaceHistory(7, $this->eventDao, $indexDao, $segmentTranslationDao);

        $this->assertSame(5, $history->redo());
    }

    #[Test]
    public function updateIndexCallsIndexDaoSave(): void
    {
        $indexDao = $this->createMock(ReplaceEventIndexDaoInterface::class);
        $indexDao->expects($this->once())->method('save')->with(1, 7);

        $history = new ReplaceHistory(1, $this->eventDao, $indexDao, $this->segmentTranslationDao);
        $history->updateIndex(7);
    }
}
