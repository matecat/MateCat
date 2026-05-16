<?php

use Model\DataAccess\Database;
use Model\DataAccess\IDaoStruct;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use Model\Jobs\JobStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;


/**
 * @group  regression
 * @covers Model\DataAccess\AbstractDao::_sanitizeInputArray
 * User: dinies
 * Date: 19/04/16
 * Time: 16.06
 */
#[Group('PersistenceNeeded')]
class SanitizeInputArrayTest extends AbstractTest
{
    protected ReflectionClass $reflector;
    protected ReflectionMethod $method;
    /**
     * @var IDaoStruct[]
     */
    protected array $array_of_structs_input;
    protected EngineDAO $dao;

    public function setUp(): void
    {
        parent::setUp();
        $this->dao = new EngineDAO(Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE));
        $this->reflector = new ReflectionClass($this->dao);
        $this->method = $this->reflector->getMethod("_sanitizeInputArray");
    }

    /**
     * @throws ReflectionException
     * @group  regression
     * @covers Model\DataAccess\AbstractDao::_sanitizeInputArray
     */
    #[Test]
    public function test__sanitizeInputArray_with_correct_structs_that_match_with_the_given_type()
    {
        $first_struct = new EngineStruct();
        $second_struct = new EngineStruct();
        $third_struct = new EngineStruct();

        $first_struct->name = "bar";
        $second_struct->name = "foo";
        $third_struct->id = "22";

        $this->array_of_structs_input = [$first_struct, $second_struct, $third_struct];
        $type = EngineStruct::class;

        $invoke = $this->method->invoke($this->dao, $this->array_of_structs_input, $type);
        $this->assertEquals($this->array_of_structs_input, $invoke);
    }


    /**
     * @throws ReflectionException
     * @group  regression
     * @covers Model\DataAccess\AbstractDao::_sanitizeInputArray
     */
    #[Test]
    public function test__sanitizeInputArray_with_wrong_struct_that_dont_match_with_the_given_type()
    {
        $first_struct = new EngineStruct();
        $second_struct = new JobStruct();
        $third_struct = new EngineStruct();

        $first_struct->name = "bar";
        $second_struct->owner = "foo";
        $third_struct->id = "22";

        $this->array_of_structs_input = [$first_struct, $second_struct, $third_struct];
        $type = EngineStruct::class;

        $this->expectException("Exception");
        $this->method->invoke($this->dao, $this->array_of_structs_input, $type);
    }
}