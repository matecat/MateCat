<?php

use Model\DataAccess\Database;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Engines\MyMemory;
use Utils\Registry\AppConfig;


/**
 * @group  regression
 * @covers MyMemory::__construct
 * * User: dinies
 * Date: 28/04/16
 * Time: 15.45
 */
#[Group('PersistenceNeeded')]
class ConstructorMyMemoryTest extends AbstractTest
{

    /**
     * @var EngineStruct
     */
    protected EngineStruct $engine_struct_param;

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        $engineDAO = new EngineDAO(Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE));
        $engine_struct = EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng = $engineDAO->read($engine_struct);

        /**
         * @var $engineRecord EngineStruct
         */
        $this->engine_struct_param = $eng[0];
        parent::setUp();
    }

    /**
     * It constructs an engine, and it initializes some globals from the abstract constructor
     * @group   regression
     * @covers  Engines_Moses::__construct
     * @throws Exception
     */
    #[Test]
    public function test___construct_of_sub_engine_of_moses()
    {
        $myMemory = new MyMemory($this->engine_struct_param);
        $reflector = new ReflectionClass($myMemory);
        $property = $reflector->getProperty("engineRecord");


        $this->assertEquals($this->engine_struct_param, $property->getValue($myMemory));

        $property = $reflector->getProperty("className");


        $this->assertEquals(MyMemory::class, $property->getValue($myMemory));

        $property = $reflector->getProperty("curl_additional_params");


        $this->assertCount(6, $property->getValue($myMemory));
    }


    /**
     * It will raise an exception constructing an engine because of he wrong property of the struct.
     * @group   regression
     * @covers  Engines_Moses::__construct
     */
    #[Test]
    public function test___construct_failure()
    {
        $this->engine_struct_param->type = "fooo";
        $this->expectException("Exception");
        new MyMemory($this->engine_struct_param);
    }
}