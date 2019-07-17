<?php

/**
 * @group  regression
 * @covers Jobs_JobDao::read
 * User: dinies
 * Date: 31/05/16
 * Time: 12.32
 */
class ReadJobTest extends AbstractTest {
    /**
     * @var \Predis\Client
     */
    protected $flusher;
    /**
     * @var Jobs_JobDao
     */
    protected $job_Dao;
    protected $sql_delete_job;
    /**
     * @var Database
     */
    protected $database_instance;
    protected $job_id;
    protected $job_password;
    protected $job_owner;
    /**
     * @var Jobs_JobStruct
     */
    protected $job_struct;
    protected $job_struct_param;


    public function setUp() {
        parent::setUp();
        $this->database_instance = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->job_Dao           = new Jobs_JobDao( $this->database_instance );


        /**
         * job initialization
         */
        $this->job_password = "7barandfoo71";
        $this->job_owner    = "barandfoo@translated.net";
        $this->job_struct   = new Jobs_JobStruct(
                [
                        'id'                                  => null, //SET NULL FOR AUTOINCREMENT -> in this case is only stored in cache so i will chose a casual value
                        'password'                            => $this->job_password,
                        'id_project'                          => "432999999",
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
                        'owner'                               => $this->job_owner,
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
        $this->job_Dao->createFromStruct( $this->job_struct );

        $this->job_id = $this->getTheLastInsertIdByQuery($this->database_instance);

        $this->sql_delete_job = "DELETE FROM  " . INIT::$DB_DATABASE . ".`jobs` WHERE id='" . $this->job_id . "';";

    }

    public function tearDown() {
        $this->database_instance->getConnection()->query( $this->sql_delete_job );
        $this->flusher = new Predis\Client( INIT::$REDIS_SERVERS );
        $this->flusher->flushdb();
        parent::tearDown();
    }

    /**
     * @group  regression
     * @covers Jobs_JobDao::read
     */
    public function test_read_job_without_params() {
        $this->job_struct_param = new Jobs_JobStruct( [] );
        $result                 = $this->job_Dao->read( $this->job_struct_param );
        $this->assertEmpty( $result );

    }

    /**
     * @group  regression
     * @covers Jobs_JobDao::read
     */
    public function test_read__job_with_success_id_and_password_given() {
        $this->job_struct_param           = new Jobs_JobStruct( [] );
        $this->job_struct_param->id       = $this->job_id;
        $this->job_struct_param->password = $this->job_password;
        $result_wrapped                   = $this->job_Dao->read( $this->job_struct_param );
        $result                           = $result_wrapped[ '0' ];
        $this->assertTrue( $result instanceof Jobs_JobStruct );
        $this->assertEquals( $this->job_id, $result->id );
        $this->assertEquals( $this->job_password, $result->password );
        $this->assertEquals( "432999999", $result->id_project );
        $this->assertEquals( "182655137", $result->job_first_segment );
        $this->assertEquals( "182655236", $result->job_last_segment );
        $this->assertEquals( "nl-NL", $result->source );
        $this->assertEquals( "de-DE", $result->target );
        $tm_keys = '[{"tm":true,"glos":true,"owner":true,"uid_transl":null,"uid_rev":null,"name":"","key":"e1f9153f48c4c7e9328d","r":true,"w":true,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]';
        $this->assertEquals( $tm_keys, $result->tm_keys );
        $this->assertEquals( "", $result->id_translator );
        $this->assertNull( $result->job_type );
        $this->assertEquals( "156255", $result->total_time_to_edit );
        $this->assertEquals( "0", $result->avg_post_editing_effort );
        $this->assertFalse( isset($result->id_job_to_revise) );
        $this->assertEquals( "182655204", $result->last_opened_segment );
        $this->assertEquals( "1", $result->id_tms );
        $this->assertEquals( "1", $result->id_mt_engine );
        $this->assertRegExp( '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2]?[0-9]:[0-5][0-9]:[0-5][0-9]$/', $result->create_date );
        $this->assertRegExp( '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2]?[0-9]:[0-5][0-9]:[0-5][0-9]$/', $result->last_update );
        $this->assertEquals( "0", $result->disabled );
        $this->assertEquals( $this->job_owner, $result->owner );
        $this->assertEquals( "active", $result->status_owner );
        $this->assertEquals( "active", $result->status );
        $this->assertNull( $result->status_translator );

        $this->assertEquals( 0, $result->completed );
        $this->assertEquals( "-12.60", $result->new_words );
        $this->assertEquals( "0.00", $result->draft_words );
        $this->assertEquals( "728.15", $result->translated_words );
        $this->assertEquals( "0.00", $result->approved_words );
        $this->assertEquals( "0.00", $result->rejected_words );
        $this->assertEquals( "general", $result->subject );
        $payable_rates = '{"NO_MATCH":100,"50%-74%":100,"75%-84%":60,"85%-94%":60,"95%-99%":60,"100%":30,"100%_PUBLIC":30,"REPETITIONS":30,"INTERNAL":60,"MT":85}';
        $this->assertEquals( $payable_rates, $result->payable_rates );
        $this->assertEquals( "0", $result->revision_stats_typing_min );
        $this->assertEquals( "0", $result->revision_stats_translations_min );
        $this->assertEquals( "0", $result->revision_stats_terminology_min );
        $this->assertEquals( "0", $result->revision_stats_language_quality_min );
        $this->assertEquals( "0", $result->revision_stats_style_min );
        $this->assertEquals( "0", $result->revision_stats_typing_maj );
        $this->assertEquals( "0", $result->revision_stats_translations_maj );
        $this->assertEquals( "0", $result->revision_stats_terminology_maj );
        $this->assertEquals( "0", $result->revision_stats_language_quality_maj );
        $this->assertEquals( "0", $result->revision_stats_style_maj );
        $this->assertEquals( "1", $result->total_raw_wc );
        $this->assertNull( $result->validator );

    }
}