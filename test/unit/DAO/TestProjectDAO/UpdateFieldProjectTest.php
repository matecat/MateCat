<?php

/**
 * @group  regression
 * @covers Projects_ProjectDao::updateField
 * User: dinies
 * Date: 01/07/16
 * Time: 12.45
 */
class UpdateFieldProjectTest extends AbstractTest {

    /**
     * @var Projects_ProjectDao
     */
    protected $projectDao;

    /**
     * @var Database
     */
    protected $database_instance;

    /** @var Projects_ProjectStruct */
    protected $project;

    public function setUp() {
        parent::setUp();

        $this->database_instance = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->projectDao        = new Projects_ProjectDao( $this->database_instance );

        $this->database_instance->getConnection()->query( "DELETE FROM projects WHERE 1" );
        $this->database_instance->getConnection()->query( "DELETE FROM jobs WHERE 1" );

        $this->database_instance->getConnection()->query(
                "INSERT INTO projects
                    ( password, id_customer, id_team, name, create_date, id_engine_tm, id_engine_mt, status_analysis, fast_analysis_wc, 
                    tm_analysis_wc, standard_analysis_wc, remote_ip_address, instance_id, pretranslate_100, id_qa_model, id_assignee, due_date )
                VALUES ( 
                    '3359e5740208', 'domenico@translated.net', '1', 'MATECAT_PROJ-201906190336', 
                    '2019-06-19 15:36:08', NULL, NULL, 'DONE', '148.00', '120.60', '150.60', 
                    '127.0.0.1', '0', '0', '123', '3', NULL 
                    )"
        );
        $this->project = new Projects_ProjectStruct( $this->database_instance->getConnection()->query( "SELECT * FROM projects LIMIT 1" )->fetch() );

    }


    public function test_updateField() {

        /**
         * @params
         */
        $field_to_update   = 'name';
        $value_to_update   = 'bar_and_foo_updated_name';
        $project_to_update = new Projects_ProjectStruct( $this->project->toArray() );


        $project_before_update = $this->project;
        $this->projectDao->updateField( $project_to_update, $field_to_update, $value_to_update );

        $project_after_update = new Projects_ProjectStruct( $this->database_instance->getConnection()->query( "SELECT * FROM projects LIMIT 1" )->fetch() );

        $this->assertNotEquals( $project_before_update[ $field_to_update ], $project_after_update[ $field_to_update ] );
        $this->assertEquals( $value_to_update, $project_after_update[ $field_to_update ] );


    }
}