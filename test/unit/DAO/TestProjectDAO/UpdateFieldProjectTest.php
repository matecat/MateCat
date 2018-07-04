<?php

/**
 * @group regression
 * @covers Projects_ProjectDao::updateField
 * User: dinies
 * Date: 01/07/16
 * Time: 12.45
 */
class UpdateFieldProjectTest extends AbstractTest
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

    public function setUp()
    {
        parent::setUp();

        $this->database_instance = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $this->projectDao = new Projects_ProjectDao($this->database_instance);

        $this->test_initializer = new UnitTestInitializer($this->database_instance);

    }


    public function test_updateField(){

        /**
         * @params
         */
        $field_to_update = 'name';
        $value_to_update = 'bar_and_foo_updated_name';
        $project_to_update = new Projects_ProjectStruct($this->test_initializer->getProject());


        $project_before_update = $this->test_initializer->getProject();
        $this->projectDao->updateField($project_to_update, $field_to_update, $value_to_update);
        $project_after_update = $this->test_initializer->getProject();

        $this->assertNotEquals( $project_before_update[$field_to_update], $project_after_update[$field_to_update]);
        $this->assertEquals( $value_to_update, $project_after_update[$field_to_update]);


    }
}