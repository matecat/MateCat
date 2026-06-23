<?php

namespace Matecat\Core\Model\Projects;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Projects\MetadataDao;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

class MetadataDaoInjectedDbGuardTest extends AbstractTest
{
    private static bool $originalSkipCache;

    protected function setUp(): void
    {
        parent::setUp();

        self::$originalSkipCache = AppConfig::$SKIP_SQL_CACHE;
        AppConfig::$SKIP_SQL_CACHE = true;

        // Clear the singleton so Database::obtain()->getConnection() throws if called
        $this->setDatabaseInstance(null);
    }

    protected function tearDown(): void
    {
        AppConfig::$SKIP_SQL_CACHE = self::$originalSkipCache;

        parent::tearDown();
    }

    #[Test]
    public function queriesUseInjectedDatabaseNotSingleton(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->queryString = '';
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(0);
        $stmt->method('fetch')->willReturn(false);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = $this->createMock(IDatabase::class);
        $db->expects($this->atLeastOnce())
            ->method('getConnection')
            ->willReturn($pdo);

        $dao = new MetadataDao($db);
        $dao->set(1, 'k', 'v');
    }
}
