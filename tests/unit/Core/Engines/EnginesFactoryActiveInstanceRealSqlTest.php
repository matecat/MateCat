<?php

declare(strict_types=1);

namespace Matecat\Core\Engines;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Utils\Constants\EngineConstants;
use Utils\Engines\EnginesFactory;
use Utils\Engines\Lara;
use Utils\Engines\MMT;

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
        $this->createEngine($this->uid, MMT::class);

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
