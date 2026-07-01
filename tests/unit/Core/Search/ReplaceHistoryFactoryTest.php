<?php

namespace Matecat\Core\Search;

use InvalidArgumentException;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use PHPUnit\Framework\Attributes\Test;
use Utils\Search\ReplaceHistoryFactory;

class ReplaceHistoryFactoryTest extends AbstractTest
{
    #[Test]
    public function createWithRedisDriverReturnsReplaceHistory(): void
    {
        $history = ReplaceHistoryFactory::create(1, 'redis', 300, $this->createStub(IDatabase::class));
        $this->assertInstanceOf(\Utils\Search\ReplaceHistory::class, $history);
    }

    #[Test]
    public function createWithMysqlDriverReturnsReplaceHistory(): void
    {
        $history = ReplaceHistoryFactory::create(1, 'mysql', 300, $this->createStub(IDatabase::class));
        $this->assertInstanceOf(\Utils\Search\ReplaceHistory::class, $history);
    }

    #[Test]
    public function createWithInvalidDriverThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('postgres is not an allowed driver');

        ReplaceHistoryFactory::create(1, 'postgres', 300, $this->createStub(IDatabase::class));
    }

    #[Test]
    public function createWithZeroTtl(): void
    {
        $history = ReplaceHistoryFactory::create(1, 'redis', 0, $this->createStub(IDatabase::class));
        $this->assertInstanceOf(\Utils\Search\ReplaceHistory::class, $history);
    }
}
