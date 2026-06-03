<?php

namespace Matecat\Core\Search;

use Matecat\TestHelpers\AbstractTest;
use Model\Search\RedisReplaceEventIndexDao;
use PHPUnit\Framework\Attributes\Test;
use Predis\ClientInterface;

class RedisReplaceEventIndexDaoTest extends AbstractTest
{
    private ClientInterface $redis;
    private RedisReplaceEventIndexDao $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->redis = $this->createStub(ClientInterface::class);
        $this->dao = new RedisReplaceEventIndexDao(null, $this->redis);
    }

    #[Test]
    public function getActualIndexReturnsZeroWhenNull(): void
    {
        $this->redis->method('__call')->willReturn(null);

        $this->assertSame(0, $this->dao->getActualIndex(1));
    }

    #[Test]
    public function getActualIndexReturnsZeroWhenZeroOrNegative(): void
    {
        $this->redis->method('__call')->willReturn('0');

        $this->assertSame(0, $this->dao->getActualIndex(1));
    }

    #[Test]
    public function getActualIndexReturnsValueWhenPositive(): void
    {
        $this->redis->method('__call')->willReturn('5');

        $this->assertSame(5, $this->dao->getActualIndex(1));
    }

    #[Test]
    public function saveReturnsOne(): void
    {
        $this->redis->method('__call')->willReturn(true);

        $result = $this->dao->save(1, 3);
        $this->assertSame(1, $result);
    }

    #[Test]
    public function setTtlChangesValue(): void
    {
        $this->dao->setTtl(600);
        $this->assertTrue(true);
    }
}
