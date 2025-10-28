<?php

use Model\DataAccess\Database;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
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
class ConstructorMyMemoryTest extends AbstractTest {


    /**
     * @var EngineStruct
     */
    protected $engine_struct_param;

    /**
     * @var array
     */
    protected $others_param;
    protected $reflector;
    protected $property;

    public function setUp(): void {
        $engineDAO         = new EngineDAO( Database::obtain( AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE ) );
        $engine_struct     = EngineStruct::getStruct();
        $engine_struct->id = 1;
        $eng               = $engineDAO->read( $engine_struct );

        /**
         * @var $engineRecord EngineStruct
         */
        $this->engine_struct_param = $eng[ 0 ];
    }

    /**
     * It construct an engine and it initialises some globals from the abstract constructor
     * @group   regression
     * @covers  Engines_Moses::__construct
     */
    public function test___construct_of_sub_engine_of_moses() {
        $this->databaseInstance = new MyMemory( $this->engine_struct_param );
        $this->reflector        = new ReflectionClass( $this->databaseInstance );
        $this->property         = $this->reflector->getProperty( "engineRecord" );
        

        $this->assertEquals( $this->engine_struct_param, $this->property->getValue( $this->databaseInstance ) );

        $this->property = $this->reflector->getProperty( "className" );
        

        $this->assertEquals( MyMemory::class, $this->property->getValue( $this->databaseInstance ) );

        $this->property = $this->reflector->getProperty( "curl_additional_params" );
        

        $this->assertEquals( 6, count( $this->property->getValue( $this->databaseInstance ) ) );

    }


    /**
     * It will raise an exception constructing an engine because of he wrong property of the struct.
     * @group   regression
     * @covers  Engines_Moses::__construct
     */
    public function test___construct_failure() {
        $this->engine_struct_param->type = "fooo";
        $this->expectException( "Exception" );
        new MyMemory( $this->engine_struct_param );
    }
}