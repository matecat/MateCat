<?php

namespace Matecat\Core\Search;

use Matecat\TestHelpers\AbstractTest;
use Model\Search\ReplaceEventDAOInterface;
use Model\Search\ReplaceEventIndexDaoInterface;
use Model\Search\ReplaceEventStruct;
use PHPUnit\Framework\Attributes\Test;
use Utils\Search\ReplaceHistory;

class ReplaceHistoryTest extends AbstractTest
{
    private ReplaceEventDAOInterface $eventDao;
    private ReplaceEventIndexDaoInterface $indexDao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventDao = $this->createStub(ReplaceEventDAOInterface::class);
        $this->indexDao = $this->createStub(ReplaceEventIndexDaoInterface::class);
    }

    private function makeHistory(int $idJob = 1, int $ttl = 0): ReplaceHistory
    {
        return new ReplaceHistory($idJob, $this->eventDao, $this->indexDao, $ttl);
    }

    #[Test]
    public function constructorSetsTtlOnDaos(): void
    {
        $eventDao = $this->createMock(ReplaceEventDAOInterface::class);
        $eventDao->expects($this->once())->method('setTtl')->with(600);

        $indexDao = $this->createMock(ReplaceEventIndexDaoInterface::class);
        $indexDao->expects($this->once())->method('setTtl')->with(600);

        new ReplaceHistory(1, $eventDao, $indexDao, 600);
    }

    #[Test]
    public function constructorSkipsTtlWhenZero(): void
    {
        $eventDao = $this->createMock(ReplaceEventDAOInterface::class);
        $eventDao->expects($this->never())->method('setTtl');

        $indexDao = $this->createMock(ReplaceEventIndexDaoInterface::class);
        $indexDao->expects($this->never())->method('setTtl');

        new ReplaceHistory(1, $eventDao, $indexDao, 0);
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

        $history = new ReplaceHistory(42, $this->eventDao, $this->indexDao);
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
    public function undoReturnsZeroWhenNoEvents(): void
    {
        $this->indexDao->method('getActualIndex')->willReturn(0);
        $this->eventDao->method('getEvents')->willReturn([]);

        $this->assertSame(0, $this->makeHistory()->undo());
    }

    #[Test]
    public function updateIndexCallsIndexDaoSave(): void
    {
        $indexDao = $this->createMock(ReplaceEventIndexDaoInterface::class);
        $indexDao->expects($this->once())->method('save')->with(1, 7);

        $history = new ReplaceHistory(1, $this->eventDao, $indexDao);
        $history->updateIndex(7);
    }
}
