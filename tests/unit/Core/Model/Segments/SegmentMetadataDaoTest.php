<?php

declare(strict_types=1);

namespace Matecat\Core\Model\Segments;

use Matecat\TestHelpers\AbstractTest;
use Model\Segments\SegmentMetadataDao;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

class SegmentMetadataDaoTest extends AbstractTest
{
    protected function setUp(): void
    {
        parent::setUp();
        AppConfig::$SKIP_SQL_CACHE = true;
        $this->createDatabaseMock();
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    #[Test]
    public function getBySegmentIds_returns_empty_array_for_empty_ids(): void
    {
        $dao = new SegmentMetadataDao(\Model\DataAccess\Database::obtain());

        $result = $dao->getBySegmentIds([], 'context-url', 0);

        $this->assertSame([], $result);
    }
}
