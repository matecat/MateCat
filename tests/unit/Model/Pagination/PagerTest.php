<?php

namespace Tests\unit\Model\Pagination;

use Model\Pagination\Pager;
use Model\Pagination\PaginationParameters;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

class PagerTest extends AbstractTest
{
    #[Test]
    public function count_returns_integer(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn([42]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $pager = new Pager($pdo);
        $this->assertSame(42, $pager->count('SELECT count(*) FROM foo'));
    }

    #[Test]
    public function count_returns_zero_when_no_result(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $pager = new Pager($pdo);
        $this->assertSame(0, $pager->count('SELECT count(*) FROM foo'));
    }

    #[Test]
    public function count_passes_parameters(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn([5]);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(['uid' => 1]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $pager = new Pager($pdo);
        $this->assertSame(5, $pager->count('SELECT count(*) FROM foo WHERE uid = :uid', ['uid' => 1]));
    }

    #[Test]
    public function getPagination_returns_formatted_result(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn(['item1', 'item2']);
        $stmt->queryString = 'SELECT * FROM foo LIMIT %d OFFSET %d';

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $params = new PaginationParameters(
            'SELECT * FROM foo LIMIT %d OFFSET %d',
            [],
            \stdClass::class,
            '/api/test?page=',
            1,
            10
        );

        $pager = new Pager($pdo);
        $result = $pager->getPagination(19, $params);

        $this->assertSame(1, $result['current_page']);
        $this->assertSame(10, $result['per_page']);
        $this->assertSame(2, $result['last_page']);
        $this->assertSame(20, $result['total']);
        $this->assertNull($result['prev']);
        $this->assertSame('/api/test?page=2', $result['next']);
        $this->assertSame(['item1', 'item2'], $result['items']);
    }

    #[Test]
    public function getPagination_page2_has_prev_and_next(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn(['item1']);
        $stmt->queryString = 'SELECT * FROM foo LIMIT %d OFFSET %d';

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $params = new PaginationParameters(
            'SELECT * FROM foo LIMIT %d OFFSET %d',
            [],
            \stdClass::class,
            '/api/test?page=',
            2,
            5
        );

        $pager = new Pager($pdo);
        $result = $pager->getPagination(14, $params);

        $this->assertSame(2, $result['current_page']);
        $this->assertSame('/api/test?page=1', $result['prev']);
        $this->assertSame('/api/test?page=3', $result['next']);
    }

    #[Test]
    public function getPagination_last_page_has_no_next(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchAll')->willReturn(['item1']);
        $stmt->queryString = 'SELECT * FROM foo LIMIT %d OFFSET %d';

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $params = new PaginationParameters(
            'SELECT * FROM foo LIMIT %d OFFSET %d',
            [],
            \stdClass::class,
            '/api/test?page=',
            2,
            10
        );

        $pager = new Pager($pdo);
        $result = $pager->getPagination(14, $params);

        $this->assertSame(2, $result['current_page']);
        $this->assertSame('/api/test?page=1', $result['prev']);
        $this->assertNull($result['next']);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function getPagination_cache_miss_then_set(): void
    {
        $pdo = \Model\DataAccess\Database::obtain(
            AppConfig::$DB_SERVER,
            AppConfig::$DB_USER,
            AppConfig::$DB_PASS,
            AppConfig::$DB_DATABASE
        )->getConnection();

        $pager = new Pager($pdo);

        $params = new PaginationParameters(
            'SELECT id, segment FROM segments LIMIT %d OFFSET %d',
            [],
            \Model\DataAccess\ShapelessConcreteStruct::class,
            '/api/test?page=',
            1,
            5
        );
        $cacheKey = 'PagerTest::cache_miss_' . uniqid();
        $params->setCache($cacheKey, 10);

        $totals = $pager->count('SELECT count(id) FROM segments');
        $result = $pager->getPagination($totals, $params);

        $this->assertSame(1, $result['current_page']);
        $this->assertSame(5, $result['per_page']);
        $this->assertIsArray($result['items']);
    }

    #[Test]
    #[Group('PersistenceNeeded')]
    public function getPagination_cache_hit_returns_cached_result(): void
    {
        $pdo = \Model\DataAccess\Database::obtain(
            AppConfig::$DB_SERVER,
            AppConfig::$DB_USER,
            AppConfig::$DB_PASS,
            AppConfig::$DB_DATABASE
        )->getConnection();

        $pager = new Pager($pdo);

        $params = new PaginationParameters(
            'SELECT id, segment FROM segments LIMIT %d OFFSET %d',
            [],
            \Model\DataAccess\ShapelessConcreteStruct::class,
            '/api/test?page=',
            1,
            5
        );
        $cacheKey = 'PagerTest::cache_hit_' . uniqid();
        $params->setCache($cacheKey, 10);

        $totals = $pager->count('SELECT count(id) FROM segments');

        $first = $pager->getPagination($totals, $params);
        $second = $pager->getPagination($totals, $params);

        $this->assertEquals($first['items'], $second['items']);
        $this->assertSame($first['current_page'], $second['current_page']);
    }
}
