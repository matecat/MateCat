<?php

use TestHelpers\AbstractTest;


/**
 * @group  regression
 * @covers DataAccess_AbstractDao::_fetchObject
 * User: dinies
 * Date: 09/06/16
 * Time: 20.46
 */
class AbstractFetchObjectJobTest extends AbstractTest {

    /**
     * @var \Predis\Client
     */
    protected $cache;
    /**
     * @var Jobs_JobDao
     */
    protected $job_Dao;
    /**
     * @var Jobs_JobStruct
     */
    protected $fetchClass_param;
    protected $job_struct;
    protected $job_array;

    protected $sql_insert_job;
    protected $sql_delete_job;

    /**
     * @var Database
     */
    protected $database_instance;
    protected $job_id;
    protected $job_password;

    protected $reflector;
    protected $method_fetchObject;
    protected $method_getStatementForCache;
    protected $method_setInCache;
    protected $stmt_param;
    protected $bindParams_param;


    public function setUp() {
        parent::setUp();
        $this->database_instance = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->job_Dao           = new Jobs_JobDao( $this->database_instance );

        /**
         * job initialization
         */
        $this->job_password = "7barandfoo71";

        $this->job_array  = new Jobs_JobStruct(
                [
                        'id'                      => null, //SET NULL FOR AUTOINCREMENT -> in this case is only stored in cache so i will chose a casual value
                        'password'                => $this->job_password,
                        'id_project'              => "432999999",
                        'job_first_segment'       => "182655137",
                        'job_last_segment'        => "182655236",
                        'source'                  => "nl-NL",
                        'target'                  => "de-DE",
                        'tm_keys'                 => '[{"tm":true,"glos":true,"owner":true,"uid_transl":null,"uid_rev":null,"name":"","key":"e1f9153f48c4c7e9328d","r":true,"w":true,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]',
                        'id_translator'           => "",
                        'job_type'                => null,
                        'total_time_to_edit'      => "156255",
                        'avg_post_editing_effort' => "0",
                        'id_job_to_revise'        => null,
                        'last_opened_segment'     => "182655204",
                        'id_tms'                  => "1",
                        'id_mt_engine'            => "1",
                        'create_date'             => "2016-03-30 13:18:09",
                        'last_update'             => "2016-03-30 13:21:02",
                        'disabled'                => "0",
                        'owner'                   => "barandfoo@translated.net",
                        'status_owner'            => "active",
                        'status'                  => "active",
                        'status_translator'       => null,
                        'completed'               => false,
                        'new_words'               => "-12.60",
                        'draft_words'             => "0.00",
                        'translated_words'        => "728.15",
                        'approved_words'          => "0.00",
                        'rejected_words'          => "0.00",
                        'subject'                 => "general",
                        'payable_rates'           => '{"NO_MATCH":100,"50%-74%":100,"75%-84%":60,"85%-94%":60,"95%-99%":60,"100%":30,"100%_PUBLIC":30,"REPETITIONS":30,"INTERNAL":60,"MT":85}',
                        'total_raw_wc'            => "1",
                        'validator'               => "xxxx"
                ]
        );
        $this->job_struct = $this->job_Dao->createFromStruct( $this->job_array );

        $this->job_id = $this->getTheLastInsertIdByQuery( $this->database_instance );

        $this->sql_delete_job = "DELETE FROM " . INIT::$DB_DATABASE . ".`jobs` WHERE id='" . $this->job_id . "';";


        $this->reflector          = new ReflectionClass( $this->job_Dao );
        $this->method_fetchObject = $this->reflector->getMethod( "_fetchObject" );
        $this->method_fetchObject->setAccessible( true );


        $this->reflector         = new ReflectionClass( $this->job_Dao );
        $this->method_setInCache = $this->reflector->getMethod( "_setInCache" );
        $this->method_setInCache->setAccessible( true );


        $this->reflector                   = new ReflectionClass( $this->job_Dao );
        $this->method_getStatementForCache = $this->reflector->getMethod( "_getStatementForCache" );
        $this->method_getStatementForCache->setAccessible( true );
        /**
         * Params
         */

        $this->stmt_param = $this->method_getStatementForCache->invokeArgs( $this->job_Dao, [ null ] );


        $this->fetchClass_param           = new \Jobs_JobStruct();
        $this->fetchClass_param->id       = $this->job_id;
        $this->fetchClass_param->password = $this->job_password;

        $this->bindParams_param = [
                'id_job'   => $this->job_id,
                'password' => $this->job_password
        ];

    }

    public function tearDown() {
        $this->cache = new Predis\Client( INIT::$REDIS_SERVERS );

        $this->database_instance->getConnection()->query( $this->sql_delete_job );
        $this->cache->flushdb();
        parent::tearDown();
    }

    /**
     * @group  regression
     * @covers DataAccess_AbstractDao::_fetchObject
     */
    public function test__fetchObject_with_cache_hit() {

        /**
         * Initialization of match in cache for the cache hit
         */

        $cache_value = [
                '0' => $this->job_struct
        ];

        $cache_key = $this->stmt_param->queryString . serialize( $this->bindParams_param );


        $cache_TTL = ( new ReflectionClass( $this->job_Dao ) )->getProperty( "cacheTTL" );
        $cache_TTL->setAccessible( true );
        $cache_TTL->setValue( $this->job_Dao, 30 );


        $method_setCacheConnection = ( new ReflectionClass( $this->job_Dao ) )->getMethod( '_cacheSetConnection' );
        $method_setCacheConnection->setAccessible( true );
        $method_setCacheConnection->invoke( $this->job_Dao );


        $this->method_setInCache->invoke( $this->job_Dao, $cache_key, $cache_value );
        /**
         * Cached
         *
         * Mock Object Creation
         */
        $mock_job_Dao = $this->getMockBuilder( '\Jobs_JobDao' )
                ->setConstructorArgs( [ $this->database_instance ] )
                ->setMethods( [ '_setInCache' ] )
                ->getMock();

        $mock_job_Dao->expects( $this->never() )
                ->method( '_setInCache' );

        $mock_job_Dao->setCacheTTL( 20 );

        /**
         * _fetchObject invocation
         */
        $wrapped_result = $this->method_fetchObject->invoke( $mock_job_Dao, $this->stmt_param, $this->fetchClass_param, $this->bindParams_param );

        $result = $wrapped_result[ '0' ];

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
//        $this->assertNull( $result->id_job_to_revise ); // id_job_to_revise does not exists anymore
        $this->assertEquals( "182655204", $result->last_opened_segment );
        $this->assertEquals( "1", $result->id_tms );
        $this->assertEquals( "1", $result->id_mt_engine );
        $this->assertRegExp( '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2]?[0-9]:[0-5][0-9]:[0-5][0-9]$/', $result->create_date );
        $this->assertRegExp( '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2]?[0-9]:[0-5][0-9]:[0-5][0-9]$/', $result->last_update );
        $this->assertEquals( "0", $result->disabled );
        $this->assertEquals( "barandfoo@translated.net", $result->owner );
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
        $this->assertEquals( "1", $result->total_raw_wc );
        $this->assertFalse( isset( $result->validator ) );


    }

    /**
     * @group  regression
     * @covers DataAccess_AbstractDao::_fetchObject
     */
    public function test__fetchObject_with_cache_miss() {

        /**
         * Not cached
         * Mock Object Creation
         */


        $mock_job_Dao = $this->getMockBuilder( '\Jobs_JobDao' )
                ->setConstructorArgs( [ $this->database_instance ] )
                ->setMethods( [ '_setInCache' ] )
                ->getMock();

        $mock_job_Dao->expects( $this->exactly( 1 ) )
                ->method( '_setInCache' );

        $mock_job_Dao->setCacheTTL( 20 );

        /**
         * _fetchObject invocation
         */
        $wrapped_result = $this->method_fetchObject->invoke( $mock_job_Dao, $this->stmt_param, $this->fetchClass_param, $this->bindParams_param );
        $result         = $wrapped_result[ '0' ];


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
//        $this->assertNull( $result->id_job_to_revise ); // id_job_to_revise does not exists anymore
        $this->assertEquals( "182655204", $result->last_opened_segment );
        $this->assertEquals( "1", $result->id_tms );
        $this->assertEquals( "1", $result->id_mt_engine );
        $this->assertRegExp( '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2]?[0-9]:[0-5][0-9]:[0-5][0-9]$/', $result->create_date );
        $this->assertRegExp( '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2]?[0-9]:[0-5][0-9]:[0-5][0-9]$/', $result->last_update );
        $this->assertEquals( "0", $result->disabled );
        $this->assertEquals( "barandfoo@translated.net", $result->owner );
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
        $this->assertEquals( "1", $result->total_raw_wc );
        $this->assertFalse( isset( $result->validator ) );

    }


}
