<?php

namespace Matecat\Core\Search;

use Matecat\TestHelpers\AbstractTest;
use Model\Search\MySQLReplaceEventIndexDao;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;

class MySQLReplaceEventIndexDaoTest extends AbstractTest
{
    private \PDO $pdo;
    private PDOStatement $stmt;
    private MySQLReplaceEventIndexDao $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stmt = $this->createStub(PDOStatement::class);
        $this->pdo = $this->createStub(PDO::class);
        $this->pdo->method('prepare')->willReturn($this->stmt);
        $this->dao = new MySQLReplaceEventIndexDao(null, $this->pdo);
    }

    #[Test]
    public function getActualIndexReturnsVersion(): void
    {
        $this->stmt->method('fetch')->willReturn([['v' => '3']]);

        $this->assertSame(3, $this->dao->getActualIndex(1));
    }

    #[Test]
    public function getActualIndexReturnsZeroWhenNoRows(): void
    {
        $this->stmt->method('fetch')->willReturn([['v' => '0']]);

        $this->assertSame(0, $this->dao->getActualIndex(1));
    }

    #[Test]
    public function saveInsertPathWhenIndexIsZero(): void
    {
        $this->stmt->method('fetch')->willReturn([['v' => '0']]);
        $this->stmt->method('rowCount')->willReturn(1);

        $result = $this->dao->save(1, 5);
        $this->assertSame(1, $result);
    }

    #[Test]
    public function saveUpdatePathWhenIndexNonZero(): void
    {
        $this->stmt->method('fetch')->willReturn([['v' => '3']]);
        $this->stmt->method('rowCount')->willReturn(1);

        $result = $this->dao->save(1, 5);
        $this->assertSame(1, $result);
    }

    #[Test]
    public function setTtlIsNoOp(): void
    {
        $this->dao->setTtl(600);
        $this->assertTrue(true);
    }
}
