<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers DataAccess_AbstractDao::_getFromCache
 * User: dinies
 * Date: 09/06/16
 * Time: 21.18
 */
class AbstractGetFromCacheJobTest extends AbstractTest {
    /**
     * @var Jobs_JobDao
     */
    protected $job_Dao;
    protected $reflector;
    protected $method_getFromCache;
    protected $cache_con;
    protected $cache_TTL;
    protected $cache_key;
    protected $cache_value;
    /**
     * @var Database
     */
    protected $database_instance;
    protected $sql_delete_job;
    protected $job_id;
    protected $job_password;
    protected $job_array;
    /**
     * @var Jobs_JobStruct
     */
    protected $job_struct;
    protected $method_getStatementForCache;
    protected $stmt_param;
    protected $bindParams_param;

    /**
     * @throws ReflectionException
     */
    public function setUp() {
        parent::setUp();
        $this->database_instance = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->job_Dao           = new Jobs_JobDao( $this->database_instance );
        $this->reflector         = new ReflectionClass( $this->job_Dao );

        $this->method_getFromCache = $this->reflector->getMethod( "_getFromCache" );
        $this->method_getFromCache->setAccessible( true );

        $this->reflector                   = new ReflectionClass( $this->job_Dao );
        $this->method_getStatementForCache = $this->reflector->getMethod( "_getStatementForCache" );
        $this->method_getStatementForCache->setAccessible( true );


        $this->cache_con = $this->reflector->getProperty( "cache_con" );
        $this->cache_con->setAccessible( true );
        $this->cache_con->setValue( $this->job_Dao, ( new RedisHandler() )->getConnection() );

        $this->cache_TTL = $this->reflector->getProperty( "cacheTTL" );
        $this->cache_TTL->setAccessible( true );
        $this->cache_TTL->setValue( $this->job_Dao, 30 );

        /**
         * job initialization
         */
        $this->job_password = "7barandfoo71";

        $this->job_array  = new Jobs_JobStruct(
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
        $this->job_struct = $this->job_Dao->createFromStruct( $this->job_array );

        $this->job_id = $this->database_instance->getConnection()->lastInsertId();

        $this->sql_delete_job = "DELETE FROM " . INIT::$DB_DATABASE . ".`jobs` WHERE id='" . $this->job_id . "';";

        /**
         * Initialization of match in cache for the cache hit
         */

        $this->cache_value = [
                '0' => $this->job_struct
        ];


        /**
         * Params
         */
        $this->stmt_param = new PDOStatement();

        $this->stmt_param = $this->method_getStatementForCache->invoke( $this->job_Dao, "SELECT * FROM " . INIT::$DB_DATABASE . ".`jobs` WHERE  id = :id_job AND password = :password " );

        $this->bindParams_param = [
                'id_job'   => $this->job_id,
                'password' => $this->job_password
        ];

        $this->cache_key = $this->stmt_param->queryString . serialize( $this->bindParams_param );


    }

    public function tearDown() {

        $this->database_instance->getConnection()->query( $this->sql_delete_job );

        $this->cache_con->getValue( $this->job_Dao )->flushdb();
        parent::tearDown();
    }


    /**
     * It gets from the cache a common engine tied to a frequent key.
     * @group  regression
     * @covers DataAccess_AbstractDao::_getFromCache
     */
    public function test__getFromCache_simple_engine_with_artificial_insertion_in_cache() {

        $this->cache_con->getValue( $this->job_Dao )->setex( md5( $this->cache_key ), $this->cache_TTL->getValue( $this->job_Dao ), serialize( $this->cache_value ) );
        $expected_return = $this->method_getFromCache->invoke( $this->job_Dao, $this->cache_key );
        $this->assertEquals( $this->cache_value, $expected_return );
    }

    /**
     * It gets from the cache a common engine tied to a frequent key.
     * @group  regression
     * @covers DataAccess_AbstractDao::_getFromCache
     */
    public function test__getFromCache_engine_just_created() {

        $this->job_id           = $this->getTheLastInsertIdByQuery( $this->database_instance ); // update job_id with the last insert id
        $this->bindParams_param = [
                'id_job'   => $this->job_id,
                'password' => $this->job_password
        ];

        $job_param_for_read_method           = new Jobs_JobStruct( [] );
        $job_param_for_read_method->id       = $this->job_id;
        $job_param_for_read_method->password = $this->job_password;
        $this->job_Dao->read( $job_param_for_read_method );

        $this->cache_key = $this->stmt_param->queryString . serialize( $this->bindParams_param ); // update $this->cache_key

        $expected_return = $this->method_getFromCache->invoke( $this->job_Dao, $this->cache_key );

        $this->assertEquals( $this->cache_value, $expected_return );
    }


}