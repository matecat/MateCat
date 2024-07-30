<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers Jobs_JobDao
 * User: dinies
 * Date: 30/05/16
 * Time: 18.00
 */
class CacheBehaviourJobTest extends AbstractTest {
    /**
     * @var Jobs_JobStruct
     */
    protected $job_struct;
    /**
     * @var Database
     */
    protected $database_instance;
    /**
     * @var Jobs_JobDao
     */
    protected $job_Dao;
    /**
     * @var \Predis\Client
     */
    protected $cache;
    protected $id;
    protected $str_password;
    protected $sql_delete_job;

    /**
     * @throws ReflectionException
     */
    public function setUp() {
        parent::setUp();


        /**
         * job initialization
         */
        $this->str_password = "7barandfoo71";
        $this->job_struct   = new Jobs_JobStruct(
                [
                        'id'                                  => null, //SET NULL FOR AUTOINCREMENT -> in this case is only stored in cache so i will chose a casual value
                        'password'                            => $this->str_password,
                        'id_project'                          => "432500344",
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
                        'owner'                               => "barandfoo@translated.net",
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
        $this->job_Dao           = new Jobs_JobDao( $this->database_instance );
        $this->cache             = ( new RedisHandler() )->getConnection();
        $this->job_Dao->createFromStruct( $this->job_struct );

        $this->id = $this->getTheLastInsertIdByQuery( $this->database_instance );

        $this->sql_delete_job = "DELETE FROM " . INIT::$DB_DATABASE . ".`jobs` WHERE id='" . $this->id . "';";


    }

    public function tearDown() {
        $this->database_instance->getConnection()->query( $this->sql_delete_job );
        $this->cache->flushdb();
        parent::tearDown();
    }

    /**
     * @group  regression
     * @covers Jobs_JobDao
     */
    public function test_correct_behaviour_of_cache_with_jobs() {

        $this->job_struct->id = $this->id;

        $reflector = new ReflectionClass( $this->job_Dao );
        $method    = $reflector->getMethod( "_getStatementForCache" );
        $method->setAccessible( true );

        $statement = $method->invokeArgs( $this->job_Dao, [ null ] );

        //check that there is no cache
        $this->assertEmpty( unserialize(
                $this->cache->get( md5( $statement->queryString . serialize(
                                [
                                        'id_job'   => $this->id,
                                        'password' => $this->str_password
                                ]
                        ) ) )
        ) );

        $param_job_struct           = new Jobs_JobStruct( [] );
        $param_job_struct->id       = $this->id;
        $param_job_struct->password = $this->str_password;

        $this->job_Dao->setCacheTTL( 20 )->read( $param_job_struct );

        $cache_result = ( unserialize(
                $this->cache->get( md5( $statement->queryString . serialize(
                                [
                                        'id_job'   => $this->id,
                                        'password' => $this->str_password
                                ]
                        ) ) )
        ) );
        $this->cache->flushdb();

        $struct_of_second_read = $this->job_Dao->read( $param_job_struct )[ 0 ];
        $result_from_cache     = $cache_result[ '0' ];

        $this->assertEquals( $result_from_cache, $struct_of_second_read );
    }
}