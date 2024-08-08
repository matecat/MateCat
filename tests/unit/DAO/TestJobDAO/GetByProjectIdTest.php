<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers Jobs_JobDao::getByProjectId
 * User: dinies
 * Date: 27/05/16
 * Time: 11.47
 */
class GetByProjectIdTest extends AbstractTest {
    /**
     * @var Jobs_JobDao
     */
    protected $job_Dao;

    /**
     * @var Jobs_JobStruct
     */
    protected $job_struct;

    /**
     * @var Database
     */
    protected $database_instance;

    protected $id;
    protected $str_password;
    protected $str_id_project;
    protected $str_owner;
    protected $sql_delete_job;
    protected $flusher;

    public function setUp() {
        parent::setUp();

        /**
         * job initialization
         */

        $this->str_id_project = "888888";
        $this->str_password   = "7barandfoo71";
        $this->str_owner      = "barandfoo@translated.net";
        $this->job_struct     = new Jobs_JobStruct(
                [
                        'id'                                  => null, //SET NULL FOR AUTOINCREMENT
                        'password'                            => $this->str_password,
                        'id_project'                          => $this->str_id_project,
                        'job_first_segment'                   => "182655137",
                        'job_last_segment'                    => "182655236",
                        'source'                              => "nl-NL",
                        'target'                              => "de-DE",
                        'tm_keys'                             => '[{"tm":true,"glos":true,"owner":true,"uid_transl":null,"uid_rev":null,"name":"","key":"e1f9153f48c4c7e9328d","r":true,"w":true,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]',
                        'id_translator'                       => "",
                        'job_type'                            => null,
                        'total_time_to_edit'                  => "156255",
                        'avg_post_editing_effort'             => "0",
                        'id_job_to_revise'                    => null,
                        'last_opened_segment'                 => "182655204",
                        'id_tms'                              => "1",
                        'id_mt_engine'                        => "1",
                        'create_date'                         => "2016-03-30 13:18:09",
                        'last_update'                         => "2016-03-30 13:21:02",
                        'disabled'                            => "0",
                        'owner'                               => $this->str_owner,
                        'status_owner'                        => "active",
                        'status'                              => "active",
                        'status_translator'                   => null,
                        'completed'                           => false,
                        'new_words'                           => "-12.60",
                        'draft_words'                         => "0.00",
                        'translated_words'                    => "728.15",
                        'approved_words'                      => "0.00",
                        'rejected_words'                      => "0.00",
                        'subject'                             => "general",
                        'payable_rates'                       => '{"NO_MATCH":100,"50%-74%":100,"75%-84%":60,"85%-94%":60,"95%-99%":60,"100%":30,"100%_PUBLIC":30,"REPETITIONS":30,"INTERNAL":60,"MT":85}',
                        'revision_stats_typing_min'           => "0",
                        'revision_stats_translations_min'     => "0",
                        'revision_stats_terminology_min'      => "0",
                        'revision_stats_language_quality_min' => "0",
                        'revision_stats_style_min'            => "0",
                        'revision_stats_typing_maj'           => "0",
                        'revision_stats_translations_maj'     => "0",
                        'revision_stats_terminology_maj'      => "0",
                        'revision_stats_language_quality_maj' => "0",
                        'revision_stats_style_maj'            => "0",
                        'total_raw_wc'                        => "1",
                        'validator'                           => "xxxx"
                ]
        );

        $this->database_instance = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );

        $this->job_Dao = new Jobs_JobDao( $this->database_instance );

        $this->job_Dao->createFromStruct( $this->job_struct );

        $this->id = $this->getTheLastInsertIdByQuery($this->database_instance);

        $this->sql_delete_job = "DELETE FROM " . INIT::$DB_DATABASE . ".`jobs` WHERE id='" . $this->id . "';";


    }


    public function tearDown() {

        $this->database_instance->getConnection()->query( $this->sql_delete_job );
        $this->flusher = new Predis\Client( INIT::$REDIS_SERVERS );
        $this->flusher->flushdb();
        parent::tearDown();
    }

    public function test_GetByProjectId() {
        $actual_result = $this->job_Dao->getByProjectId( $this->str_id_project );
        $id            = $actual_result[ '0' ][ 'id' ];
        $this->assertEquals( $this->id, $id );
        $password = $actual_result[ '0' ][ 'password' ];
        $this->assertEquals( $this->str_password, $password );
        $id_project = $actual_result[ '0' ][ 'id_project' ];
        $this->assertEquals( $this->str_id_project, $id_project );
        $owner = $actual_result[ '0' ][ 'owner' ];
        $this->assertEquals( $this->str_owner, $owner );

    }
}