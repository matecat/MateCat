<?php

namespace Tests\unit\Model\Pagination;

use Model\Pagination\PaginationParameters;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class PaginationParametersTest extends AbstractTest
{
    #[Test]
    public function constructor_sets_all_properties(): void
    {
        $params = new PaginationParameters(
            'SELECT * FROM foo',
            ['uid' => 1],
            \stdClass::class,
            '/api/test?page=',
            3,
            15
        );

        $this->assertSame('SELECT * FROM foo', $params->getQuery());
        $this->assertSame(['uid' => 1], $params->getBindParams());
        $this->assertSame(\stdClass::class, $params->getFetchClass());
        $this->assertSame('/api/test?page=', $params->getBaseRoute());
        $this->assertSame(3, $params->getCurrent());
        $this->assertSame(15, $params->getPagination());
    }

    #[Test]
    public function constructor_defaults_current_and_pagination(): void
    {
        $params = new PaginationParameters(
            'SELECT * FROM foo',
            [],
            \stdClass::class,
            '/api/test?page='
        );

        $this->assertSame(1, $params->getCurrent());
        $this->assertSame(20, $params->getPagination());
    }

    #[Test]
    public function constructor_null_current_defaults_to_1(): void
    {
        $params = new PaginationParameters(
            'SELECT * FROM foo',
            [],
            \stdClass::class,
            '/api/test?page=',
            null,
            null
        );

        $this->assertSame(1, $params->getCurrent());
        $this->assertSame(20, $params->getPagination());
    }

    #[Test]
    public function setCache_sets_key_and_ttl(): void
    {
        $params = new PaginationParameters(
            'SELECT * FROM foo',
            [],
            \stdClass::class,
            '/api/test?page='
        );

        $this->assertNull($params->getCacheKeyMap());
        $this->assertNull($params->getTtl());

        $params->setCache('my-cache-key', 3600);

        $this->assertSame('my-cache-key', $params->getCacheKeyMap());
        $this->assertSame(3600, $params->getTtl());
    }

    #[Test]
    public function setCache_default_ttl(): void
    {
        $params = new PaginationParameters(
            'SELECT * FROM foo',
            [],
            \stdClass::class,
            '/api/test?page='
        );

        $params->setCache('my-key');

        $this->assertSame(86400, $params->getTtl());
    }
}
