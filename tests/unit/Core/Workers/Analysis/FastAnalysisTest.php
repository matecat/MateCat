<?php

namespace Matecat\Core\Workers\Analysis;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Utils\AsyncTasks\Workers\Analysis\FastAnalysis;
use Utils\Constants\ProjectStatus;
use Utils\Logger\MatecatLogger;

/**
 * Unit-covers the injected-database plumbing of the FastAnalysis daemon.
 *
 * The daemon constructor opens an ActiveMQ/Redis connection (and installs signal
 * handlers), so it cannot be exercised in a unit test. We instead build the
 * instance with newInstanceWithoutConstructor() and seed the protected $db with a
 * stub, then drive the methods that thread that handle into the DAOs. This proves
 * the daemon resolves its DB once (db()) and passes it down instead of calling the
 * Database::obtain() singleton.
 */
class FastAnalysisTest extends AbstractTest
{
    /**
     * Build a FastAnalysis with no constructor and the given IDatabase seeded
     * into its private $db composition-root property.
     */
    private function daemonWithDb(IDatabase $db): FastAnalysis
    {
        $ref    = new ReflectionClass(FastAnalysis::class);
        $daemon = $ref->newInstanceWithoutConstructor();

        $ref->getProperty('db')->setValue($daemon, $db);
        // The constructor normally wires the logger; seed a no-op stub so the
        // methods under test can log without a NullPointer on the typed property.
        $ref->getProperty('logger')->setValue($daemon, $this->createStub(MatecatLogger::class));

        return $daemon;
    }

    /**
     * Invoke a non-public method by name, with optional arguments.
     */
    private function invoke(FastAnalysis $daemon, string $method, mixed ...$args): mixed
    {
        $m = (new ReflectionClass(FastAnalysis::class))->getMethod($method);

        return $m->invoke($daemon, ...$args);
    }

    /**
     * Set a private/protected property on the daemon (e.g. a seeded collaborator).
     */
    private function setProp(FastAnalysis $daemon, string $name, mixed $value): void
    {
        (new ReflectionClass(FastAnalysis::class))->getProperty($name)->setValue($daemon, $value);
    }

    #[Test]
    public function dbReturnsTheSeededHandleWithoutHittingTheSingleton(): void
    {
        $injected = $this->createStub(IDatabase::class);
        $daemon   = $this->daemonWithDb($injected);

        // db() must return the already-resolved instance (the ??= short-circuit),
        // never fall back to Database::obtain().
        $this->assertSame($injected, $this->invoke($daemon, 'db'));
    }

    #[Test]
    public function getProjectDaoBuildsDaoWithTheInjectedDatabase(): void
    {
        $injected = $this->createStub(IDatabase::class);
        $daemon   = $this->daemonWithDb($injected);

        $dao = $this->invoke($daemon, 'getProjectDao');

        $this->assertInstanceOf(ProjectDao::class, $dao);
        // The DAO carries the injected handle, not the singleton.
        $this->assertSame($injected, $dao->getDatabaseHandler());
    }

    #[Test]
    public function checkDatabaseConnectionResolvesThroughInjectedDatabase(): void
    {
        // A non-Database IDatabase stub makes _checkDatabaseConnection() take the
        // early return after `$db = $this->db()` — covering the injected-handle
        // resolution without needing a live connection. expects(never()) on the
        // poisoned singleton proves it is not consulted.
        $injected = $this->createStub(IDatabase::class);

        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        $daemon = $this->daemonWithDb($injected);

        $this->assertNotInstanceOf(Database::class, $injected);
        $this->invoke($daemon, '_checkDatabaseConnection');
    }

    #[Test]
    public function executeInsertPreparesOnTheInjectedDatabaseConnection(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->willReturn(true);
        $stmt->expects($this->once())->method('closeCursor')->willReturn(true);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $injected = $this->createMock(IDatabase::class);
        $injected->expects($this->atLeastOnce())->method('getConnection')->willReturn($pdo);

        // The singleton must never be consulted for the insert.
        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('getConnection');
        $this->setDatabaseInstance($poison);

        $daemon = $this->daemonWithDb($injected);

        $this->invoke($daemon, '_executeInsert', ['(:a,:b,:c,:d,:e,:f)'], ['x']);
    }

    #[Test]
    public function updateProjectChangesStatusThroughTheInjectedDatabaseTransaction(): void
    {
        $project = new ProjectStruct();
        $project->status_analysis = ProjectStatus::STATUS_NEW; // != DONE → status change runs

        $projectDao = $this->createMock(ProjectDao::class);
        $projectDao->method('findById')->willReturn($project);
        $projectDao->expects($this->once())
            ->method('changeProjectStatus')
            ->with(7, ProjectStatus::STATUS_BUSY)
            ->willReturn(1);

        // transaction() must run on the injected db and execute the closure.
        $injected = $this->createMock(IDatabase::class);
        $injected->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(fn (callable $cb) => $cb());

        $poison = $this->createMock(IDatabase::class);
        $poison->expects($this->never())->method('transaction');
        $this->setDatabaseInstance($poison);

        $daemon = $this->daemonWithDb($injected);
        $this->setProp($daemon, 'projectDao', $projectDao); // getProjectDao() short-circuits to this

        $this->invoke($daemon, '_updateProject', 7, ProjectStatus::STATUS_BUSY);
    }
}
