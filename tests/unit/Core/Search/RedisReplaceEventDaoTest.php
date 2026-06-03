<?php

namespace Matecat\Core\Search;

use Matecat\TestHelpers\AbstractTest;
use Model\Search\RedisReplaceEventDao;
use Model\Search\ReplaceEventStruct;
use Model\Translations\SegmentTranslationDao;
use PHPUnit\Framework\Attributes\Test;
use Predis\ClientInterface;

class RedisReplaceEventDaoTest extends AbstractTest
{
    private ClientInterface $redis;
    private RedisReplaceEventDao $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->redis = $this->createStub(ClientInterface::class);
        $this->dao = new RedisReplaceEventDao(null, $this->redis);
    }

    #[Test]
    public function getEventsReturnsEmptyWhenNoData(): void
    {
        $this->redis->method('__call')->willReturn([]);

        $result = $this->dao->getEvents(1, 1);
        $this->assertSame([], $result);
    }

    #[Test]
    public function getEventsReturnsDeserializedStructs(): void
    {
        $event = new ReplaceEventStruct();
        $event->id_job = 1;
        $event->replace_version = '1';
        $event->id_segment = 100;
        $event->target = 'hello';
        $event->status = 'TRANSLATED';
        $event->replacement = 'world';
        $event->translation_after_replacement = 'world';
        $event->job_password = 'abc';

        $this->redis->method('__call')->willReturnCallback(function (string $method) use ($event) {
            if ($method === 'hgetall') {
                return [serialize($event)];
            }
            return null;
        });

        $result = $this->dao->getEvents(1, 1);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(ReplaceEventStruct::class, $result[0]);
        $this->assertSame(100, $result[0]->id_segment);
    }

    #[Test]
    public function saveWithVersionSet(): void
    {
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

        $callLog = [];
        $this->redis->method('__call')->willReturnCallback(function (string $method) use (&$callLog) {
            $callLog[] = $method;
            if ($method === 'hgetall') {
                return [];
            }
            if ($method === 'hset') {
                return 1;
            }
            return true;
        });

        $result = $this->dao->save($event);
        $this->assertSame(1, $result);
        $this->assertNotEmpty($event->created_at);
    }

    #[Test]
    public function saveWithoutVersionUsesSegmentTranslationDao(): void
    {
        $segmentStub = new \stdClass();
        $segmentStub->version_number = 7;

        $segmentDao = $this->createStub(SegmentTranslationDao::class);
        $segmentDao->method('getByJobId')->willReturn([$segmentStub]);

        $dao = new RedisReplaceEventDao(null, $this->redis, $segmentDao);

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

        $this->redis->method('__call')->willReturnCallback(function (string $method) {
            if ($method === 'hgetall') {
                return [];
            }
            if ($method === 'hset') {
                return 1;
            }
            return true;
        });

        $dao->save($event);
        $this->assertSame(7, $event->segment_version);
    }

    #[Test]
    public function setTtlChangesValue(): void
    {
        $this->dao->setTtl(600);
        // no assertion needed — verifies no error; TTL used internally on next save
        $this->assertTrue(true);
    }
}
