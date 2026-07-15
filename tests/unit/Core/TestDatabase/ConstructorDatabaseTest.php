<?php


namespace Matecat\Core\TestDatabase;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;


/**
 * @group  regression
 * @covers \Model\DataAccess\Database::__construct
 * User: dinies
 * Date: 11/04/16
 * Time: 17.51
 */
#[Group('PersistenceNeeded')]
class ConstructorDatabaseTest extends AbstractTest
{

    public function setUp(): void
    {
        parent::setUp();
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * This test checks that an Exception will be raised if the constructor is called without parameters.
     * @group  regression
     * @covers Database::__construct
     */
    #[Test]
    public function test___construct_without_parameters()
    {
        $instance = new Database('', '', '', '');

        $this->assertInstanceOf(Database::class, $instance);

        $this->expectException(\PDOException::class);
        $instance->getConnection();
    }


}
