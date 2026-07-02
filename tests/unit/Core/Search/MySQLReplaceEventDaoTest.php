<?php

namespace Matecat\Core\Search;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Search\MySQLReplaceEventDao;
use Model\Search\ReplaceEventStruct;
use Model\Translations\SegmentTranslationDao;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;

class MySQLReplaceEventDaoTest extends AbstractTest
{
    private \PDO $pdo;
    private PDOStatement $stmt;
    private MySQLReplaceEventDao $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stmt = $this->createStub(PDOStatement::class);
        $this->pdo = $this->createStub(PDO::class);
        $this->pdo->method('prepare')->willReturn($this->stmt);
        $this->dao = new MySQLReplaceEventDao($this->createStub(IDatabase::class), $this->pdo);
    }

    #[Test]
    public function getEventsReturnsEmptyArray(): void
    {
        $this->stmt->method('fetchAll')->willReturn([]);

        $result = $this->dao->getEvents(1, 1);
        $this->assertSame([], $result);
    }

    #[Test]
    public function getEventsReturnsStructs(): void
    {
        $event = new ReplaceEventStruct();
        $event->id_job = 1;
        $event->replace_version = '1';
        $this->stmt->method('fetchAll')->willReturn([$event]);

        $result = $this->dao->getEvents(1, 1);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(ReplaceEventStruct::class, $result[0]);
    }

    #[Test]
    public function saveWithVersionReturnsRowCount(): void
    {
        $this->stmt->method('rowCount')->willReturn(1);

        $event = new ReplaceEventStruct();
        $event->id_job = 1;
        $event->replace_version = '2';
        $event->id_segment = 50;
        $event->segment_version = 3;
        $event->target = 'target';
        $event->status = 'TRANSLATED';
        $event->replacement = 'repl';
        $event->translation_after_replacement = 'repl';
        $event->job_password = 'pass';
        $event->source = 'source';
        $event->translation_before_replacement = 'before';

        $result = $this->dao->save($event);
        $this->assertSame(1, $result);
    }

    #[Test]
    public function saveWithoutVersionUsesSegmentTranslationDao(): void
    {
        $segmentStub = new \stdClass();
        $segmentStub->version_number = 5;

        $segmentDao = $this->createStub(SegmentTranslationDao::class);
        $segmentDao->method('getByJobId')->willReturn([$segmentStub]);

        $dao = new MySQLReplaceEventDao($this->createStub(IDatabase::class), $this->pdo, $segmentDao);

        $this->stmt->method('rowCount')->willReturn(1);

        $event = new ReplaceEventStruct();
        $event->id_job = 1;
        $event->replace_version = '1';
        $event->id_segment = 50;
        $event->segment_version = null;
        $event->target = 'target';
        $event->status = 'TRANSLATED';
        $event->replacement = 'repl';
        $event->translation_after_replacement = 'repl';
        $event->job_password = 'pass';
        $event->source = 'src';
        $event->translation_before_replacement = 'before';

        $dao->save($event);
        $this->assertSame(5, $event->segment_version);
    }

    #[Test]
    public function setTtlIsNoOp(): void
    {
        $this->dao->setTtl(600);
        $this->assertTrue(true);
    }
}
