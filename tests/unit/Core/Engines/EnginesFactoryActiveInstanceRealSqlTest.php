<?php

declare(strict_types=1);

namespace Matecat\Core\Engines;

use Controller\API\Commons\Exceptions\AuthorizationError;
use DomainException;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Constants\EngineConstants;
use Utils\Engines\AbstractEngine;
use Utils\Engines\DeepL;
use Utils\Engines\EnginesFactory;
use Utils\Engines\Lara;

#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class EnginesFactoryActiveInstanceRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private int $uid;

    protected function realSqlTableDeps(): array
    {
        return ['engines'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->realSqlSetUp();
        $this->uid = self::ASSIGNABLE_ID_FLOOR + 7017;
    }

    protected function tearDown(): void
    {
        $this->realSqlTearDown(function (): void {
            $this->realSqlDb()->getConnection()->exec(
                'DELETE FROM engines WHERE uid IN (' . $this->uid . ', ' . ($this->uid + 1) . ')'
            );
        });
        parent::tearDown();
    }

    #[Test]
    public function getActiveInstanceByUserAndClassReturnsTheActiveEngineOfTheUserAndClass(): void
    {
        $id = $this->createEngine($this->uid, Lara::class);

        $engine = EnginesFactory::getActiveInstanceByUserAndClass($this->uid, $this->realSqlDb(), Lara::class);

        self::assertInstanceOf(Lara::class, $engine);
        self::assertSame($id, $engine->getEngineRecord()->id);
    }

    #[Test]
    public function getActiveInstanceByUserAndClassReturnsNullWhenTheEngineIsDisabled(): void
    {
        $this->createEngine($this->uid, Lara::class, active: false);

        self::assertNull(EnginesFactory::getActiveInstanceByUserAndClass($this->uid, $this->realSqlDb(), Lara::class));
    }

    #[Test]
    public function getActiveInstanceByUserAndClassReturnsNullForAnotherClass(): void
    {
        $this->createEngine($this->uid, DeepL::class);

        self::assertNull(EnginesFactory::getActiveInstanceByUserAndClass($this->uid, $this->realSqlDb(), Lara::class));
    }

    #[Test]
    public function getActiveInstanceByUserAndClassReturnsNullForAnotherUser(): void
    {
        $this->createEngine($this->uid + 1, Lara::class);

        self::assertNull(EnginesFactory::getActiveInstanceByUserAndClass($this->uid, $this->realSqlDb(), Lara::class));
    }

    #[Test]
    public function getActiveInstanceByUserAndClassReturnsNullForUidZero(): void
    {
        $this->createEngine($this->uid, Lara::class);

        self::assertNull(EnginesFactory::getActiveInstanceByUserAndClass(0, $this->realSqlDb(), Lara::class));
    }

    #[Test]
    public function getActiveInstanceByUserAndClassReturnsNullForANullUid(): void
    {
        $this->createEngine($this->uid, Lara::class);

        self::assertNull(EnginesFactory::getActiveInstanceByUserAndClass(null, $this->realSqlDb(), Lara::class));
    }

    #[Test]
    public function getInstanceByIdAndUserRefusesAnEngineWhoseClassWasRemoved(): void
    {
        // ModernMT Full: the row is still there, but its class is gone, so EngineDAO builds it as NONE.
        $id = $this->createEngine($this->uid, 'MMT');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Engine is no longer available');

        EnginesFactory::getInstanceByIdAndUser($id, $this->uid, $this->realSqlDb(), AbstractEngine::class);
    }

    #[Test]
    public function getInstanceByIdAndUserGivesTheOwnershipErrorForARemovedEngineOfAnotherUser(): void
    {
        // The reply must not tell another user's retired engine apart from any other engine they own.
        $id = $this->createEngine($this->uid + 1, 'MMT');

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionMessage("Engine doesn't belong to the user");

        EnginesFactory::getInstanceByIdAndUser($id, $this->uid, $this->realSqlDb(), AbstractEngine::class);
    }

    #[Test]
    public function getStoredOwnerUidReadsTheUidOfARowBuiltAsNone(): void
    {
        $id = $this->createEngine($this->uid, 'MMT');
        $dao = new EngineDAO($this->realSqlDb());

        self::assertSame($this->uid, $dao->getStoredOwnerUid($id));
        self::assertNull($dao->getStoredOwnerUid(PHP_INT_MAX));
    }

    #[Test]
    public function getInstanceByIdAndUserStillReturnsTheUsersEngine(): void
    {
        $id = $this->createEngine($this->uid, Lara::class);

        $engine = EnginesFactory::getInstanceByIdAndUser($id, $this->uid, $this->realSqlDb(), AbstractEngine::class);

        self::assertSame($id, $engine->getEngineRecord()->id);
    }

    /**
     * Inserts an MT engine row and returns its id.
     */
    private function createEngine(int $uid, string $classLoad, bool $active = true): int
    {
        $s = new EngineStruct();
        $s->name = 'factory real-sql engine';
        $s->type = EngineConstants::MT;
        $s->description = 'real-sql test engine';
        $s->base_url = 'https://example.invalid/api';
        $s->translate_relative_url = 'get';
        $s->others = [];
        $s->extra_parameters = ['Lara-AccessKeyId' => 'kid', 'Lara-AccessKeySecret' => 'ksecret'];
        $s->class_load = $classLoad;
        $s->penalty = 14;
        $s->active = $active;
        $s->uid = $uid;

        return (int)(new EngineDAO($this->realSqlDb()))->create($s)->id;
    }
}
