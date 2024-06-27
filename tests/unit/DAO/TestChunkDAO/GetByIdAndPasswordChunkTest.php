<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers Chunks_ChunkDao::getByIdAndPassword
 * User: dinies
 * Date: 22/06/16
 * Time: 12.47
 */
class GetByIdAndPasswordChunkTest extends AbstractTest {
    /**
     * @var Chunks_ChunkDao
     */
    protected $chunk_Dao;
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

        $this->database_instance->getConnection()->query(
                "INSERT INTO jobs
                    ( password, id_project, job_first_segment, job_last_segment, id_translator, tm_keys, 
                    job_type, source, target, total_time_to_edit, only_private_tm, last_opened_segment, id_tms, id_mt_engine, 
                    create_date, last_update, disabled, owner, status_owner, status_translator, status, completed, new_words, 
                    draft_words, translated_words, approved_words, rejected_words, subject, payable_rates, avg_post_editing_effort, total_raw_wc,
                     `approved2_words`, `new_raw_words`, `draft_raw_words`, `translated_raw_words`, `approved_raw_words`, `approved2_raw_words`, `rejected_raw_words`
                     ) VALUES (
                              '92c5e0ce9316', '55718', '4564373', '4564383', '', 
                              '[{\"tm\":true,\"glos\":true,\"owner\":true,\"uid_transl\":null,\"uid_rev\":null,\"name\":\"2nd pass\",\"key\":\"XXXXXXXXXXXX\",\"r\":true,\"w\":true,\"r_transl\":null,\"w_transl\":null,\"r_rev\":null,\"w_rev\":null,\"source\":null,\"target\":null}]', 
                              NULL, 'en-GB', 'it-IT', '0', '0', NULL, '1', '1', '2019-06-21 15:22:14', '2019-06-21 15:23:30', '0', 
                              'domenico@translated.net', 'active', NULL, 'active', false, '36.00', '9.00', '0.00', '0.00', '0.00', 'general', 
                              '{\"NO_MATCH\":100,\"50 % -74 % \":100,\"75 % -84 % \":60,\"85 % -94 % \":60,\"95 % -99 % \":60,\"100 % \":30,\"100 % _PUBLIC\":30,\"REPETITIONS\":30,\"INTERNAL\":60,\"MT\":80}', 
                              '0', '150', 0,0,0,0,0,0,0
                    )"
        );

        $jobId = $this->database_instance->getConnection()->lastInsertId();
        $this->job = $this->database_instance->getConnection()->query( "SELECT * FROM jobs WHERE id = $jobId LIMIT 1" )->fetch();

    }

    /**
     * @group  regression
     * @covers Chunks_ChunkDao::getByIdAndPassword
     */
    function test_getByIdAndPassword_with_success() {

        $result = $this->chunk_Dao->getByIdAndPassword( $this->job[ 'id' ], $this->job[ 'password' ] );
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
        $this->assertEquals( $this->job[ 'total_raw_wc' ], $result[ 'total_raw_wc' ] );
    }

    /**
     * @group  regression
     * @covers Chunks_ChunkDao::getByIdAndPassword
     */
    function test_getByIdAndPassword_with_failure() {

        $this->job[ 'id' ] += 100;
        $this->expectException( Exception::class );
        $this->chunk_Dao->getByIdAndPassword( $this->job[ 'id' ], $this->job[ 'password' ] );

    }
}