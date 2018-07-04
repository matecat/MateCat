<?php

/**
 * @group regression
 * @covers Projects_ProjectDao::findById
 * User: dinies
 * Date: 01/07/16
 * Time: 12.38
 */
class FindByIdProjectTest extends AbstractTest
{
    /**
     * @var Projects_ProjectDao
     */
    protected $projectDao;

    /**
     * @var Projects_ProjectStruct
     */
    protected $project;
    /**
     * @var Database
     */
    protected $database_instance;


    public function setUp()
    {
        parent::setUp();

        $this->database_instance = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $this->projectDao = new Projects_ProjectDao($this->database_instance);

        $test_initializer = new UnitTestInitializer($this->database_instance);
        $this->project = $test_initializer->getProject();


    }

    /**
     * @group regression
     * @covers Projects_ProjectDao::findById
     */
    function test_findById()
    {

        $result = $this->projectDao->findById($this->project['id']);
        $this->assertTrue($result instanceof Projects_ProjectStruct);

        $this->assertEquals($this->project['id'], $result->id);
        $this->assertEquals($this->project['password'], $result->password);
        $this->assertEquals($this->project['name'], $result->name);
        $this->assertEquals($this->project['id_customer'], $result->id_customer);
        $this->assertEquals($this->project['create_date'], $result->create_date);
        $this->assertEquals($this->project['id_engine_tm'], $result->id_engine_tm);
        $this->assertEquals($this->project['id_engine_mt'], $result->id_engine_mt);
        $this->assertEquals($this->project['status_analysis'], $result->status_analysis);
        $this->assertEquals($this->project['fast_analysis_wc'], $result->fast_analysis_wc);
        $this->assertEquals($this->project['standard_analysis_wc'], $result->standard_analysis_wc);
        $this->assertEquals($this->project['remote_ip_address'], $result->remote_ip_address);
        $this->assertEquals($this->project['instance_number'], $result->instance_number);
        $this->assertEquals($this->project['pretranslate_100'], $result->pretranslate_100);
        $this->assertEquals($this->project['id_qa_model'], $result->id_qa_model);
    }
}