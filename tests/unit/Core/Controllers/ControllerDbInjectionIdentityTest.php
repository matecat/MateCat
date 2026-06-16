<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

use Controller\API\App\CommentController;
use Controller\API\App\GetSegmentsController;
use Error;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerDbInjectionTrait;
use Model\Comments\CommentDao;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\IDatabase;
use Model\Segments\SegmentDao;
use PHPUnit\Event\NoPreviousThrowableException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\InvalidArgumentException;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use ReflectionClass;
use ReflectionException;

/**
 * Neutered-ctor subclass of CommentController that exposes the exact
 * `new CommentDao($this->db())` construction path used in production
 * (see CommentController::getRange / resolve / etc.) so a test can capture
 * the DAO instance and reflect its injected connection.
 */
class IdentityTestableCommentController extends CommentController
{
    public function __construct()
    {
    }

    /**
     * Mirrors the production seam `new CommentDao($this->db())`.
     */
    public function makeDao(): CommentDao
    {
        return new CommentDao($this->db());
    }

    /**
     * Exposes the protected db() accessor for direct assertion.
     */
    public function exposeDb(): IDatabase
    {
        return $this->db();
    }
}

/**
 * Neutered-ctor subclass of GetSegmentsController that exposes the exact
 * `new SegmentDao($this->db())` construction path used in production
 * (see GetSegmentsController::getSegmentDao around line 357).
 */
class IdentityTestableGetSegmentsController extends GetSegmentsController
{
    public function __construct()
    {
    }

    /**
     * Mirrors the production seam `new SegmentDao($this->db())`.
     */
    public function makeDao(): SegmentDao
    {
        return new SegmentDao($this->db());
    }

    /**
     * Exposes the protected db() accessor for direct assertion.
     */
    public function exposeDb(): IDatabase
    {
        return $this->db();
    }
}

/**
 * Proves the IDatabase instance injected into a controller is the *exact same*
 * instance that flows into DAOs constructed via `new XxxDao($this->db())`.
 *
 * Strategy:
 *   - Inject a stub IDatabase through the neutered/reflection path (no real DB).
 *   - Call the exposed `makeDao()` seam, then reflect the DAO's protected
 *     `$database` connection property and assertSame() it against the stub.
 *
 * The connection property reflected on the DAO is AbstractDao::$database
 * (declared `protected IDatabase $database;` in lib/Model/DataAccess/AbstractDao.php).
 */
class ControllerDbInjectionIdentityTest extends AbstractTest
{
    use ControllerDbInjectionTrait;

    /**
     * Reflect the connection property AbstractDao stores the injected IDatabase
     * into. It is declared as `protected IDatabase $database` on AbstractDao.
     *
     * @throws ReflectionException
     */
    private function readDaoConnection(AbstractDao $dao): IDatabase
    {
        $prop = (new ReflectionClass(AbstractDao::class))->getProperty('database');
        $prop->setAccessible(true);

        /** @var IDatabase $connection */
        $connection = $prop->getValue($dao);

        return $connection;
    }

    /**
     * Identity: the stub injected into CommentController is the exact instance
     * inside the CommentDao built via `new CommentDao($this->db())`.
     *
     * @throws ReflectionException
     * @throws NoPreviousThrowableException
     * @throws InvalidArgumentException
     * @throws MockObjectException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function injectedConnectionReachesCommentDao(): void
    {
        $stub = $this->createStub(IDatabase::class);

        /** @var IdentityTestableCommentController $controller */
        $controller = $this->buildNeuteredControllerWithDb(IdentityTestableCommentController::class, $stub);

        $dao = $controller->makeDao();

        self::assertSame(
            $stub,
            $this->readDaoConnection($dao),
            'The IDatabase injected into CommentController must be the exact instance inside CommentDao.'
        );
    }

    /**
     * Identity: the stub injected into GetSegmentsController is the exact
     * instance inside the SegmentDao built via `new SegmentDao($this->db())`.
     *
     * @throws ReflectionException
     * @throws NoPreviousThrowableException
     * @throws InvalidArgumentException
     * @throws MockObjectException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function injectedConnectionReachesSegmentDao(): void
    {
        $stub = $this->createStub(IDatabase::class);

        /** @var IdentityTestableGetSegmentsController $controller */
        $controller = $this->buildNeuteredControllerWithDb(IdentityTestableGetSegmentsController::class, $stub);

        $dao = $controller->makeDao();

        self::assertSame(
            $stub,
            $this->readDaoConnection($dao),
            'The IDatabase injected into GetSegmentsController must be the exact instance inside SegmentDao.'
        );
    }

    /**
     * Safety: a controller built with newInstanceWithoutConstructor() and a
     * reflection-set $database returns the stub from db() and never triggers an
     * Error ("Typed property ... must not be accessed before initialization").
     *
     * @throws ReflectionException
     * @throws NoPreviousThrowableException
     * @throws InvalidArgumentException
     * @throws MockObjectException
     * @throws AssertionFailedError
     * @throws ExpectationFailedException
     */
    #[Test]
    public function neuteredCtorDbAccessorReturnsStubWithoutInitializationError(): void
    {
        $stub = $this->createStub(IDatabase::class);

        /** @var IdentityTestableCommentController $controller */
        $controller = $this->buildNeuteredControllerWithDb(IdentityTestableCommentController::class, $stub);

        try {
            $resolved = $controller->exposeDb();
        } catch (Error $e) {
            self::fail('db() raised an Error on a neutered-ctor controller: ' . $e->getMessage());
        }

        self::assertSame($stub, $resolved, 'db() must return the reflection-injected stub without re-resolving.');
    }
}
