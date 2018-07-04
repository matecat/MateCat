<?php

/**
 * @group regression
 * @covers DataAccess_AbstractDao::_sanitizeInputArray
 * User: dinies
 * Date: 19/04/16
 * Time: 16.06
 */
class SanitizeInputArrayTest extends AbstractTest
{
    protected $reflector;
    protected $method;
    protected $array_of_structs_input;

    public function setUp()
    {
        parent::setUp();
        $this->reflectedClass = new EnginesModel_EngineDAO(Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ));
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->method = $this->reflector->getMethod("_sanitizeInputArray");
        $this->method->setAccessible(true);


    }

    /**
     * @param array(EnginesModel_EngineStruct,EnginesModel_EngineStruct,EnginesModel_EngineStruct).
     * It sanitizes an array of EnginesModel_EngineStruct with structs of the correct type of instance.
     * @group regression
     * @covers DataAccess_AbstractDao::_sanitizeInputArray
     */
    public function test__sanitizeInputArray_with_correct_structs_that_match_with_the_given_type(){
        $first_struct= new EnginesModel_EngineStruct();
        $second_struct= new EnginesModel_EngineStruct();
        $third_struct= new EnginesModel_EngineStruct();

        $first_struct->name="bar";
        $second_struct->name="foo";
        $third_struct->id="22";

        $this->array_of_structs_input= array($first_struct,$second_struct,$third_struct );
        $type = "EnginesModel_EngineStruct";

        $invoke = $this->method->invoke($this->reflectedClass, $this->array_of_structs_input, $type);
        $this->assertEquals($this->array_of_structs_input, $invoke);
    }


    /**
     * @param array(EnginesModel_EngineStruct,Chunks_ChunkStruct,EnginesModel_EngineStruct).
     * It throws an exception because the second element is of the wrong instance type.
     * @group regression
     * @covers DataAccess_AbstractDao::_sanitizeInputArray
     */
    public function test__sanitizeInputArray_with_wrong_struct_that_dont_match_with_the_given_type(){
        $first_struct= new EnginesModel_EngineStruct();
        $second_struct= new Chunks_ChunkStruct();
        $third_struct= new EnginesModel_EngineStruct();

        $first_struct->name="bar";
        $second_struct->owner="foo";
        $third_struct->id="22";

        $this->array_of_structs_input= array($first_struct,$second_struct,$third_struct );
        $type = "EnginesModel_EngineStruct";

        $this->setExpectedException("Exception");
        $this->method->invoke($this->reflectedClass, $this->array_of_structs_input, $type);

    }
}