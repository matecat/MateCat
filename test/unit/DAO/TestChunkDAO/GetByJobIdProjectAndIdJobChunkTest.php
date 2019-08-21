<?php

/**
 * @group  regression
 * @covers Chunks_ChunkDao::getByIdProjectAndIdJob
 * User: dinies
 * Date: 30/06/16
 * Time: 19.01
 */
class GetByIdProjectAndIdJobChunkTest extends AbstractTest {
    /**
     * @var Chunks_ChunkDao
     */
    protected $chunk_Dao;
    /**
     * @var Projects_ProjectStruct
     */
    protected $project;
    /**
     * @var Jobs_JobStruct
     */
    protected $job;
    /**
     * @var Database
     */
    protected $database_instance;


    public function setUp() {
        parent::setUp();

        $this->database_instance = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->chunk_Dao         = new Chunks_ChunkDao( $this->database_instance );

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

        $this->database_instance->getConnection()->query(
                "INSERT INTO jobs
                    ( password, id_project, job_first_segment, job_last_segment, id_translator, tm_keys, 
                    job_type, source, target, total_time_to_edit, only_private_tm, last_opened_segment, id_tms, id_mt_engine, 
                    create_date, last_update, disabled, owner, status_owner, status_translator, status, completed, new_words, 
                    draft_words, translated_words, approved_words, rejected_words, subject, payable_rates, revision_stats_typing_min, 
                    revision_stats_translations_min, revision_stats_terminology_min, revision_stats_language_quality_min, revision_stats_style_min, 
                    revision_stats_typing_maj, revision_stats_translations_maj, revision_stats_terminology_maj, revision_stats_language_quality_maj, 
                    revision_stats_style_maj, dqf_key, avg_post_editing_effort, total_raw_wc ) VALUES (
                              '92c5e0ce9316', " . $this->project[ 'id' ] . ", '4564373', '4564383', '', 
                              '[{\"tm\":true,\"glos\":true,\"owner\":true,\"uid_transl\":null,\"uid_rev\":null,\"name\":\"2nd pass\",\"key\":\"XXXXXXXXXXXX\",\"r\":true,\"w\":true,\"r_transl\":null,\"w_transl\":null,\"r_rev\":null,\"w_rev\":null,\"source\":null,\"target\":null}]', 
                              NULL, 'en-GB', 'it-IT', '0', '0', NULL, '1', '1', '2019-06-21 15:22:14', '2019-06-21 15:23:30', '0', 
                              'domenico@translated.net', 'active', NULL, 'active', '0', '36.00', '9.00', '0.00', '0.00', '0.00', 'general', 
                              '{\"NO_MATCH\":100,\"50 % -74 % \":100,\"75 % -84 % \":60,\"85 % -94 % \":60,\"95 % -99 % \":60,\"100 % \":30,\"100 % _PUBLIC\":30,\"REPETITIONS\":30,\"INTERNAL\":60,\"MT\":80}', 
                              '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', NULL, '0', '150'
                    )"
        );
        $this->job = new Jobs_JobStruct( $this->database_instance->getConnection()->query( "SELECT * FROM jobs LIMIT 1" )->fetch() );


    }

    /**
     * @group  regression
     * @covers Chunks_ChunkDao::getByIdProjectAndIdJob
     */
    function test_getByJobId() {

        $wrapped_result = $this->chunk_Dao->getByIdProjectAndIdJob( $this->project[ 'id' ], $this->job[ 'id' ] );
        $result         = $wrapped_result[ '0' ];
        $this->assertTrue( $result instanceof Chunks_ChunkStruct );
        $this->assertEquals( $this->job[ 'id' ], $result[ 'id' ] );
        $this->assertEquals( $this->job[ 'password' ], $result[ 'password' ] );
        $this->assertEquals( $this->job[ 'id_project' ], $result[ 'id_project' ] );
        $this->assertEquals( $this->job[ 'job_first_segment' ], $result[ 'job_first_segment' ] );
        $this->assertEquals( $this->job[ 'job_last_segment' ], $result[ 'job_last_segment' ] );
        $this->assertEquals( $this->job[ 'source' ], $result[ 'source' ] );
        $this->assertEquals( $this->job[ 'target' ], $result[ 'target' ] );
        $this->assertEquals( $this->job[ 'tm_keys' ], $result[ 'tm_keys' ] );
        $this->assertEquals( $this->job[ 'id_translator' ], $result[ 'id_translator' ] );
        $this->assertEquals( $this->job[ 'job_type' ], $result[ 'job_type' ] );
        $this->assertEquals( $this->job[ 'total_time_to_edit' ], $result[ 'total_time_to_edit' ] );
        $this->assertEquals( $this->job[ 'avg_post_editing_effort' ], $result[ 'avg_post_editing_effort' ] );
        $this->assertEquals( $this->job[ 'last_opened_segment' ], $result[ 'last_opened_segment' ] );
        $this->assertEquals( $this->job[ 'id_tms' ], $result[ 'id_tms' ] );
        $this->assertEquals( $this->job[ 'id_mt_engine' ], $result[ 'id_mt_engine' ] );
        $this->assertEquals( $this->job[ 'create_date' ], $result[ 'create_date' ] );
        $this->assertEquals( $this->job[ 'last_update' ], $result[ 'last_update' ] );
        $this->assertEquals( $this->job[ 'disabled' ], $result[ 'disabled' ] );
        $this->assertEquals( $this->job[ 'owner' ], $result[ 'owner' ] );
        $this->assertEquals( $this->job[ 'status_owner' ], $result[ 'status_owner' ] );
        $this->assertEquals( $this->job[ 'status' ], $result[ 'status' ] );
        $this->assertEquals( $this->job[ 'status_translator' ], $result[ 'status_translator' ] );
        $this->assertEquals( $this->job[ 'completed' ], $result[ 'completed' ] );
        $this->assertEquals( $this->job[ 'new_words' ], $result[ 'new_words' ] );
        $this->assertEquals( $this->job[ 'draft_words' ], $result[ 'draft_words' ] );
        $this->assertEquals( $this->job[ 'translated_words' ], $result[ 'translated_words' ] );
        $this->assertEquals( $this->job[ 'approved_words' ], $result[ 'approved_words' ] );
        $this->assertEquals( $this->job[ 'rejected_words' ], $result[ 'rejected_words' ] );
        $this->assertEquals( $this->job[ 'subject' ], $result[ 'subject' ] );
        $this->assertEquals( $this->job[ 'payable_rates' ], $result[ 'payable_rates' ] );
        $this->assertEquals( $this->job[ 'revision_stats_typing_min' ], $result[ 'revision_stats_typing_min' ] );
        $this->assertEquals( $this->job[ 'revision_stats_translations_min' ], $result[ 'revision_stats_translations_min' ] );
        $this->assertEquals( $this->job[ 'revision_stats_terminology_min' ], $result[ 'revision_stats_terminology_min' ] );
        $this->assertEquals( $this->job[ 'revision_stats_language_quality_min' ], $result[ 'revision_stats_language_quality_min' ] );
        $this->assertEquals( $this->job[ 'revision_stats_style_min' ], $result[ 'revision_stats_style_min' ] );
        $this->assertEquals( $this->job[ 'revision_stats_typing_maj' ], $result[ 'revision_stats_typing_maj' ] );
        $this->assertEquals( $this->job[ 'revision_stats_translations_maj' ], $result[ 'revision_stats_translations_maj' ] );
        $this->assertEquals( $this->job[ 'revision_stats_terminology_maj' ], $result[ 'revision_stats_terminology_maj' ] );
        $this->assertEquals( $this->job[ 'revision_stats_language_quality_maj' ], $result[ 'revision_stats_language_quality_maj' ] );
        $this->assertEquals( $this->job[ 'revision_stats_style_maj' ], $result[ 'revision_stats_style_maj' ] );
        $this->assertEquals( $this->job[ 'total_raw_wc' ], $result[ 'total_raw_wc' ] );

    }
}