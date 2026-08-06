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

    #[Test]
    public function createdReplaceHistoryDoesNotDependOnSegmentTranslationDao(): void
    {
        // The dead redo path was removed, so ReplaceHistory (and thus the factory) no longer needs a
        // SegmentTranslationDao. Guard the constructor shape against the dependency creeping back in.
        $constructor = (new \ReflectionClass(\Utils\Search\ReplaceHistory::class))->getConstructor();
        $this->assertNotNull($constructor);

        $paramNames = array_map(
            static fn(\ReflectionParameter $p): string => $p->getName(),
            $constructor->getParameters()
        );

        $this->assertSame(['idJob', 'replaceEventDAO', 'replaceEventIndexDAO', 'ttl'], $paramNames);
    }
}
