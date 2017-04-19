<?php

/**
 * @group regression
 * @covers Projects_ProjectDao::findByIdCustomer
 * User: dinies
 * Date: 01/07/16
 * Time: 12.29
 */
class FindByIdCostumerPojectTest extends AbstractTest
{

    /**
     * @var Projects_ProjectDao
     */
    protected $projectDao;
    /**
     * @var Database
     */
    protected $database_instance;
    /**
     * @var UnitTestInitializer
     */
    protected $test_initializer;
    /**
     * @var Projects_ProjectStruct
     */
    protected $project;

    public function setUp()
    {
        parent::setUp();

        $this->database_instance = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $this->projectDao = new Projects_ProjectDao($this->database_instance);

        $this->test_initializer = new UnitTestInitializer($this->database_instance);
        $this->project= $this->test_initializer->getProject();

    }

    /**
     * @group regression
     * @covers Projects_ProjectDao::findByIdCustomer
     */
    function test_findByIdCustomer()
    {
        
        $result_array = $this->projectDao->findByIdCustomer($this->project['id_customer']);
        $first_elem_result= $result_array['0'];
        $this->assertTrue($first_elem_result instanceof Projects_ProjectStruct);

        $this->assertEquals($this->project['id'], $first_elem_result->id);
        $this->assertEquals($this->project['password'], $first_elem_result->password);
        $this->assertEquals($this->project['name'], $first_elem_result->name);
        $this->assertEquals($this->project['id_customer'], $first_elem_result->id_customer);
        $this->assertEquals($this->project['create_date'], $first_elem_result->create_date);
        $this->assertEquals($this->project['id_engine_tm'], $first_elem_result->id_engine_tm);
        $this->assertEquals($this->project['id_engine_mt'], $first_elem_result->id_engine_mt);
        $this->assertEquals($this->project['status_analysis'], $first_elem_result->status_analysis);
        $this->assertEquals($this->project['fast_analysis_wc'], $first_elem_result->fast_analysis_wc);
        $this->assertEquals($this->project['standard_analysis_wc'], $first_elem_result->standard_analysis_wc);
        $this->assertEquals($this->project['remote_ip_address'], $first_elem_result->remote_ip_address);
        $this->assertEquals($this->project['instance_number'], $first_elem_result->instance_number);
        $this->assertEquals($this->project['pretranslate_100'], $first_elem_result->pretranslate_100);
        $this->assertEquals($this->project['id_qa_model'], $first_elem_result->id_qa_model);
    }
}