<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers Projects_ProjectDao::findByJobId
 * User: dinies
 * Date: 01/07/16
 * Time: 12.02
 */
class FindProjectTest extends AbstractTest {

    /**
     * @var Projects_ProjectDao
     */
    protected $projectDao;

    /**
     * @var Jobs_JobStruct
     */
    protected $job;

    /**
     * @var Projects_ProjectStruct
     */
    protected $project;
    /**
     * @var Database
     */
    protected $database_instance;


    public function setUp() {
        parent::setUp();

        $this->database_instance = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->projectDao        = new Projects_ProjectDao( $this->database_instance );

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
        $pId = $this->database_instance->getConnection()->lastInsertId();
        $this->project = new Projects_ProjectStruct( $this->database_instance->getConnection()->query( "SELECT * FROM projects WHERE id = $pId LIMIT 1" )->fetch() );

        $this->database_instance->getConnection()->query(
                "INSERT INTO jobs
                    ( password, id_project, job_first_segment, job_last_segment, id_translator, tm_keys, 
                    job_type, source, target, total_time_to_edit, only_private_tm, last_opened_segment, id_tms, id_mt_engine, 
                    create_date, last_update, disabled, owner, status_owner, status_translator, status, completed, new_words, 
                    draft_words, translated_words, approved_words, rejected_words, subject, payable_rates, avg_post_editing_effort, total_raw_wc,
                     `approved2_words`, `new_raw_words`, `draft_raw_words`, `translated_raw_words`, `approved_raw_words`, `approved2_raw_words`, `rejected_raw_words`
                     ) VALUES (
                              '92c5e0ce9316', " . $this->project[ 'id' ] . ", '4564373', '4564383', '', 
                              '[{\"tm\":true,\"glos\":true,\"owner\":true,\"uid_transl\":null,\"uid_rev\":null,\"name\":\"2nd pass\",\"key\":\"XXXXXXXXXXXX\",\"r\":true,\"w\":true,\"r_transl\":null,\"w_transl\":null,\"r_rev\":null,\"w_rev\":null,\"source\":null,\"target\":null}]', 
                              NULL, 'en-GB', 'it-IT', '0', '0', NULL, '1', '1', '2019-06-21 15:22:14', '2019-06-21 15:23:30', '0', 
                              'domenico@translated.net', 'active', NULL, 'active', false, '36.00', '9.00', '0.00', '0.00', '0.00', 'general', 
                              '{\"NO_MATCH\":100,\"50 % -74 % \":100,\"75 % -84 % \":60,\"85 % -94 % \":60,\"95 % -99 % \":60,\"100 % \":30,\"100 % _PUBLIC\":30,\"REPETITIONS\":30,\"INTERNAL\":60,\"MT\":80}', 
                              '0', '150', 0,0,0,0,0,0,0
                    )"
        );

        $jobId     = $this->database_instance->getConnection()->lastInsertId();
        $this->job = new Jobs_JobStruct( $this->database_instance->getConnection()->query( "SELECT * FROM jobs WHERE id = $jobId LIMIT 1" )->fetch() );


    }

    /**
     * @group  regression
     * @covers Projects_ProjectDao::findByJobId
     */
    function test_findByJobId() {

        $result = $this->projectDao->findByJobId( $this->job[ 'id' ] );
        $this->assertTrue( $result instanceof Projects_ProjectStruct );

        $this->assertEquals( $this->project[ 'id' ], $result->id );
        $this->assertEquals( $this->project[ 'password' ], $result->password );
        $this->assertEquals( $this->project[ 'name' ], $result->name );
        $this->assertEquals( $this->project[ 'id_customer' ], $result->id_customer );
        $this->assertEquals( $this->project[ 'create_date' ], $result->create_date );
        $this->assertEquals( $this->project[ 'id_engine_tm' ], $result->id_engine_tm );
        $this->assertEquals( $this->project[ 'id_engine_mt' ], $result->id_engine_mt );
        $this->assertEquals( $this->project[ 'status_analysis' ], $result->status_analysis );
        $this->assertEquals( $this->project[ 'fast_analysis_wc' ], $result->fast_analysis_wc );
        $this->assertEquals( $this->project[ 'standard_analysis_wc' ], $result->standard_analysis_wc );
        $this->assertEquals( $this->project[ 'remote_ip_address' ], $result->remote_ip_address );
        $this->assertEquals( $this->project[ 'instance_id' ], $result->instance_id );
        $this->assertEquals( $this->project[ 'pretranslate_100' ], $result->pretranslate_100 );
        $this->assertEquals( $this->project[ 'id_qa_model' ], $result->id_qa_model );
    }

    /**
     * @group  regression
     * @covers Projects_ProjectDao::findById
     */
    function test_findById() {

        $result = $this->projectDao->findById( $this->project[ 'id' ] );
        $this->assertTrue( $result instanceof Projects_ProjectStruct );

        $this->assertEquals( $this->project[ 'id' ], $result->id );
        $this->assertEquals( $this->project[ 'password' ], $result->password );
        $this->assertEquals( $this->project[ 'name' ], $result->name );
        $this->assertEquals( $this->project[ 'id_customer' ], $result->id_customer );
        $this->assertEquals( $this->project[ 'create_date' ], $result->create_date );
        $this->assertEquals( $this->project[ 'id_engine_tm' ], $result->id_engine_tm );
        $this->assertEquals( $this->project[ 'id_engine_mt' ], $result->id_engine_mt );
        $this->assertEquals( $this->project[ 'status_analysis' ], $result->status_analysis );
        $this->assertEquals( $this->project[ 'fast_analysis_wc' ], $result->fast_analysis_wc );
        $this->assertEquals( $this->project[ 'standard_analysis_wc' ], $result->standard_analysis_wc );
        $this->assertEquals( $this->project[ 'remote_ip_address' ], $result->remote_ip_address );
        $this->assertEquals( $this->project[ 'instance_id' ], $result->instance_id );
        $this->assertEquals( $this->project[ 'pretranslate_100' ], $result->pretranslate_100 );
        $this->assertEquals( $this->project[ 'id_qa_model' ], $result->id_qa_model );
    }

    /**
     * @group  regression
     * @covers Projects_ProjectDao::findByIdCustomer
     */
    function test_findByIdCustomer() {

        $result_array      = $this->projectDao->findByIdCustomer( $this->project[ 'id_customer' ] );
        $found = false;
        foreach( $result_array as $first_elem_result ){

            $this->assertTrue( $first_elem_result instanceof Projects_ProjectStruct );

            if( $this->project[ 'id' ] == $first_elem_result->id ){ // we found the right project

                $found = true;

                $this->assertEquals( $this->project[ 'id' ], $first_elem_result->id );
                $this->assertEquals( $this->project[ 'password' ], $first_elem_result->password );
                $this->assertEquals( $this->project[ 'name' ], $first_elem_result->name );
                $this->assertEquals( $this->project[ 'id_customer' ], $first_elem_result->id_customer );
                $this->assertEquals( $this->project[ 'create_date' ], $first_elem_result->create_date );
                $this->assertEquals( $this->project[ 'id_engine_tm' ], $first_elem_result->id_engine_tm );
                $this->assertEquals( $this->project[ 'id_engine_mt' ], $first_elem_result->id_engine_mt );
                $this->assertEquals( $this->project[ 'status_analysis' ], $first_elem_result->status_analysis );
                $this->assertEquals( $this->project[ 'fast_analysis_wc' ], $first_elem_result->fast_analysis_wc );
                $this->assertEquals( $this->project[ 'standard_analysis_wc' ], $first_elem_result->standard_analysis_wc );
                $this->assertEquals( $this->project[ 'remote_ip_address' ], $first_elem_result->remote_ip_address );
                $this->assertEquals( $this->project[ 'instance_id' ], $first_elem_result->instance_id );
                $this->assertEquals( $this->project[ 'pretranslate_100' ], $first_elem_result->pretranslate_100 );
                $this->assertEquals( $this->project[ 'id_qa_model' ], $first_elem_result->id_qa_model );

            }


        }

        $this->assertTrue( $found );

    }

    /**
     * @group  regression
     * @covers Projects_ProjectDao::findByIdAndPassword
     */
    function test_findByIdAndPassword() {

        $result = $this->projectDao->findByIdAndPassword( $this->project[ 'id' ], $this->project[ 'password' ] );
        $this->assertTrue( $result instanceof Projects_ProjectStruct );

        $this->assertEquals( $this->project[ 'id' ], $result->id );
        $this->assertEquals( $this->project[ 'password' ], $result->password );
        $this->assertEquals( $this->project[ 'name' ], $result->name );
        $this->assertEquals( $this->project[ 'id_customer' ], $result->id_customer );
        $this->assertEquals( $this->project[ 'create_date' ], $result->create_date );
        $this->assertEquals( $this->project[ 'id_engine_tm' ], $result->id_engine_tm );
        $this->assertEquals( $this->project[ 'id_engine_mt' ], $result->id_engine_mt );
        $this->assertEquals( $this->project[ 'status_analysis' ], $result->status_analysis );
        $this->assertEquals( $this->project[ 'fast_analysis_wc' ], $result->fast_analysis_wc );
        $this->assertEquals( $this->project[ 'standard_analysis_wc' ], $result->standard_analysis_wc );
        $this->assertEquals( $this->project[ 'remote_ip_address' ], $result->remote_ip_address );
        $this->assertEquals( $this->project[ 'instance_id' ], $result->instance_id );
        $this->assertEquals( $this->project[ 'pretranslate_100' ], $result->pretranslate_100 );
        $this->assertEquals( $this->project[ 'id_qa_model' ], $result->id_qa_model );
    }

}