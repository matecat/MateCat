<?php

namespace Matecat\Core\Model\WordCount;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\WordCount\WordCounterDao;
use Model\WordCount\WordCountStruct;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

class WordCounterDaoTest extends AbstractTest
{
    private \PDO $pdoStub;
    private PDOStatement $stmtStub;

    protected function setUp(): void
    {
        parent::setUp();
        AppConfig::$SKIP_SQL_CACHE = true;

        [, $this->pdoStub, $this->stmtStub] = $this->createDatabaseMock();
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    private function makeStruct(): WordCountStruct
    {
        $struct = new WordCountStruct();
        $struct->setIdJob(1)->setJobPassword('abc');
        $struct->setNewWords(10)->setDraftWords(20)->setTranslatedWords(30);
        $struct->setApprovedWords(40)->setRejectedWords(5)->setApproved2Words(15);
        $struct->setNewRawWords(100)->setDraftRawWords(200)->setTranslatedRawWords(300);
        $struct->setApprovedRawWords(400)->setRejectedRawWords(50)->setApproved2RawWords(150);

        return $struct;
    }

    #[Test]
    public function updateWordCountReturnsRowCount(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);

        $dao = new WordCounterDao(obtainTestDatabase());
        $result = $dao->updateWordCount($this->makeStruct());

        $this->assertSame(1, $result);
    }

    #[Test]
    public function updateWordCountReturnsNegativeCodeOnPdoException(): void
    {
        $this->stmtStub->method('execute')->willThrowException(new PDOException('test', 42));

        $dao = new WordCounterDao(obtainTestDatabase());
        $result = $dao->updateWordCount($this->makeStruct());

        $this->assertSame(-42, $result);
    }

    // ─── initializeWordCount() ───

    #[Test]
    public function initializeWordCountReturnsRowCount(): void
    {
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('getConnection')->willReturn($this->pdoStub);
        $dbMock->method('rowCount')->willReturn(1);

        $this->setDatabaseInstance($dbMock);

        $dao = new WordCounterDao(obtainTestDatabase());
        $result = $dao->initializeWordCount($this->makeStruct());

        $this->assertSame(1, $result);
    }

    #[Test]
    public function initializeWordCountReturnsNegativeCodeOnPdoException(): void
    {
        $dbMock = $this->createStub(Database::class);
        $dbMock->method('getConnection')->willReturn($this->pdoStub);
        $dbMock->method('update')->willThrowException(new PDOException('test', 99));

        $this->setDatabaseInstance($dbMock);

        $dao = new WordCounterDao(obtainTestDatabase());
        $result = $dao->initializeWordCount($this->makeStruct());

        $this->assertSame(-99, $result);
    }

    // ─── getStatsForJob() ───

    #[Test]
    public function getStatsForJobReturnsResults(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([['id' => 1, 'NEW' => 10]]);

        $dao = new WordCounterDao(obtainTestDatabase());
        $results = $dao->getStatsForJob(1);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
    }

    #[Test]
    public function getStatsForJobWithPasswordAndFileId(): void
    {
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new WordCounterDao(obtainTestDatabase());
        $results = $dao->getStatsForJob(1, 5, 'abc');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }
}
