<?php

use Model\Database;
use Model\Engines\EngineDAO;
use Model\Engines\EngineStruct;
use Model\Jobs\JobStruct;
use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers Model\DataAccess\AbstractDao::_sanitizeInputArray
 * User: dinies
 * Date: 19/04/16
 * Time: 16.06
 */
class SanitizeInputArrayTest extends AbstractTest {
    protected $reflector;
    protected $method;
    protected $array_of_structs_input;

    public function setUp(): void {
        parent::setUp();
        $this->databaseInstance = new EngineDAO( Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ) );
        $this->reflector        = new ReflectionClass( $this->databaseInstance );
        $this->method           = $this->reflector->getMethod( "_sanitizeInputArray" );
        $this->method->setAccessible( true );


    }

    /**
     * @param array(EngineStruct,EngineStruct,EngineStruct).
     * It sanitizes an array of EngineStruct with structs of the correct type of instance.
     *
     * @group  regression
     * @covers Model\DataAccess\AbstractDao::_sanitizeInputArray
     */
    public function test__sanitizeInputArray_with_correct_structs_that_match_with_the_given_type() {
        $first_struct  = new EngineStruct();
        $second_struct = new EngineStruct();
        $third_struct  = new EngineStruct();

        $first_struct->name  = "bar";
        $second_struct->name = "foo";
        $third_struct->id    = "22";

        $this->array_of_structs_input = [ $first_struct, $second_struct, $third_struct ];
        $type                         = EngineStruct::class;

        $invoke = $this->method->invoke( $this->databaseInstance, $this->array_of_structs_input, $type );
        $this->assertEquals( $this->array_of_structs_input, $invoke );
    }


    /**
     * @param array(EngineStruct,JobStruct,EngineStruct).
     * It throws an exception because the second element is of the wrong instance type.
     *
     * @group  regression
     * @covers Model\DataAccess\AbstractDao::_sanitizeInputArray
     */
    public function test__sanitizeInputArray_with_wrong_struct_that_dont_match_with_the_given_type() {
        $first_struct  = new EngineStruct();
        $second_struct = new JobStruct();
        $third_struct  = new EngineStruct();

        $first_struct->name   = "bar";
        $second_struct->owner = "foo";
        $third_struct->id     = "22";

        $this->array_of_structs_input = [ $first_struct, $second_struct, $third_struct ];
        $type                         = EngineStruct::class;

        $this->expectException( "Exception" );
        $this->method->invoke( $this->databaseInstance, $this->array_of_structs_input, $type );

    }
}