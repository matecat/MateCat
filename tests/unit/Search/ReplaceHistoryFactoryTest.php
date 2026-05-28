<?php

namespace unit\Search;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Search\ReplaceHistoryFactory;

class ReplaceHistoryFactoryTest extends AbstractTest
{
    #[Test]
    public function createWithRedisDriverReturnsReplaceHistory(): void
    {
        $history = ReplaceHistoryFactory::create(1, 'redis', 300);
        $this->assertInstanceOf(\Utils\Search\ReplaceHistory::class, $history);
    }

    #[Test]
    public function createWithMysqlDriverReturnsReplaceHistory(): void
    {
        $history = ReplaceHistoryFactory::create(1, 'mysql', 300);
        $this->assertInstanceOf(\Utils\Search\ReplaceHistory::class, $history);
    }

    #[Test]
    public function createWithInvalidDriverThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('postgres is not an allowed driver');

        ReplaceHistoryFactory::create(1, 'postgres', 300);
    }

    #[Test]
    public function createWithZeroTtl(): void
    {
        $history = ReplaceHistoryFactory::create(1, 'redis', 0);
        $this->assertInstanceOf(\Utils\Search\ReplaceHistory::class, $history);
    }
}
