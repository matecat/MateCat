<?php

use Model\Database;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use Model\Jobs\JobStruct;
use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers Model\DataAccess\AbstractDao::_sanitizeInput
 * User: dinies
 * Date: 19/04/16
 * Time: 16.07
 */
class SanitizeInputTest extends AbstractTest {
    protected $reflector;
    protected $method;
    /**
     * @var EngineStruct
     */
    protected $struct_input;

    public function setUp(): void {
        parent::setUp();
        $this->databaseInstance = new EngineDAO( Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ) );
        $this->reflector        = new ReflectionClass( $this->databaseInstance );
        $this->method           = $this->reflector->getMethod( "_sanitizeInput" );
        $this->method->setAccessible( true );


    }

    /**
     * @param EngineStruct
     * It sanitizes a struct with correct type and particular name with critical characters ( " , ' ).
     *
     * @group  regression
     * @covers Model\DataAccess\AbstractDao::_sanitizeInput
     */
    public function test__sanitizeInput_with_correct_type_and_param() {

        $this->struct_input       = new EngineStruct();
        $this->struct_input->name = <<<LABEL
ba""r/foo'
LABEL;
        $type                     = EngineStruct::class;
        $this->assertEquals( $this->struct_input, $this->method->invoke( $this->databaseInstance, $this->struct_input, $type ) );
        $this->assertTrue( $this->method->invoke( $this->databaseInstance, $this->struct_input, $type ) instanceof EngineStruct );
    }


    /**
     * @param JobStruct
     * It trows an exception because the struct isn't an instnce of  'EngineStruct' .
     *
     * @group  regression
     * @covers Model\DataAccess\AbstractDao::_sanitizeInput
     */
    public function test__sanitizeInput_with_wrong_param_not_instance_of_type() {


        $this->struct_input = new JobStruct();

        $this->struct_input->owner = <<<LABEL
ba""r/foo'
LABEL;
        $type                      = EngineStruct::class;
        $this->expectException( "Exception" );
        $invoke = $this->method->invoke( $this->databaseInstance, $this->struct_input, $type );
        $this->assertFalse( $invoke instanceof EngineStruct );
    }

}