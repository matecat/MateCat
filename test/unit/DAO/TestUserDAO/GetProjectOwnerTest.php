<?php

/**
 * @group  regression
 * @covers Users_UserDao::getProjectOwner
 * User: dinies
 * Date: 27/05/16
 * Time: 18.21
 */
class GetProjectOwnerTest extends AbstractTest {
    /**
     * @var \Predis\Client
     */
    protected $flusher;
    /**
     * @var Jobs_JobDao
     */
    protected $job_Dao;

    /**
     * @var Users_UserDao
     */
    protected $user_Dao;
    protected $user_struct_param;
    protected $sql_delete_user;
    protected $sql_insert_user;
    protected $sql_delete_job;

    /**
     * @var Database
     */
    protected $database_instance;
    protected $uid_user;
    protected $id_job;
    protected $email_owner;
    /**
     * @var Jobs_JobStruct
     */
    protected $job_struct;


    public function setUp() {
        parent::setUp();
        $this->database_instance = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->user_Dao          = new Users_UserDao( $this->database_instance );

        /**
         * user insertion
         */
        $this->email_owner     = "bar@foo.net";
        $this->sql_insert_user = "INSERT INTO " . INIT::$DB_DATABASE . ".`users` (`uid`, `email`, `salt`, `pass`, `create_date`, `first_name`, `last_name` ) VALUES (NULL, '" . $this->email_owner . "', '12345trewq', '987654321qwerty', '2016-04-11 13:41:54', 'Bar', 'Foo' );";
        $this->database_instance->getConnection()->query( $this->sql_insert_user );
        $this->uid_user = $this->getTheLastInsertIdByQuery($this->database_instance);

        $this->sql_delete_user = "DELETE FROM " . INIT::$DB_DATABASE . ".`users` WHERE uid='" . $this->uid_user . "';";


        /**
         * job initialization
         */


        $this->job_struct = new Jobs_JobStruct(
                [
                        'id'                                  => null, //SET NULL FOR AUTOINCREMENT
                        'password'                            => "7barandfoo71",
                        'id_project'                          => "888888",
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
                        'owner'                               => $this->email_owner,
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


        $this->job_Dao = new Jobs_JobDao( $this->database_instance );
        $this->job_Dao->createFromStruct( $this->job_struct );
        $this->id_job = $this->getTheLastInsertIdByQuery($this->database_instance);

        $this->sql_delete_job = "DELETE FROM jobs WHERE id='" . $this->id_job . "';";


    }


    public function tearDown() {
        $this->database_instance->getConnection()->query( $this->sql_delete_job );
        $this->database_instance->getConnection()->query( $this->sql_delete_user );
        $this->flusher = new Predis\Client( INIT::$REDIS_SERVERS );
        $this->flusher->flushdb();
        parent::tearDown();
    }

    public function test_getProjectOwner() {
        /** @var Users_UserStruct $user */
        $user = $this->user_Dao->getProjectOwner( $this->id_job );
        $this->assertTrue( $user instanceof Users_UserStruct );
        $this->assertEquals( $this->uid_user, $user->uid );
        $this->assertEquals( $this->email_owner, $user->email );
        $this->assertRegExp( '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2]?[0-9]:[0-5][0-9]:[0-5][0-9]$/', $user->create_date );
        $this->assertEquals( "Bar", $user->first_name );
        $this->assertEquals( "Foo", $user->last_name );
        $this->assertEquals( '12345trewq', $user->salt );
        $this->assertEquals( '987654321qwerty', $user->pass );
        $this->assertNull( $user->oauth_access_token );
    }

    public function test_getProjectOwner_mocked() {

        /**
         * @var Users_UserDao
         */
        $mock_user_Dao = $this->getMockBuilder( Users_UserDao::class )
                ->setConstructorArgs( [ $this->database_instance ] )
                ->setMethods( [ '_buildResult', '_fetch_array' ] )
                ->getMock();

//        $mock_user_Dao->expects( $this->exactly( 1 ) )
//                ->method( '_fetch_array' );

//        $mock_user_Dao->expects( $this->exactly( 1 ) )
//                ->method( '_buildResult' );

        /** @var Users_UserStruct $user */
        $user = $mock_user_Dao->getProjectOwner( $this->id_job );

        $this->assertTrue( $user instanceof Users_UserStruct );
        $this->assertEquals( $this->uid_user, $user->uid );
        $this->assertEquals( $this->email_owner, $user->email );
        $this->assertRegExp( '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-2]?[0-9]:[0-5][0-9]:[0-5][0-9]$/', $user->create_date );
        $this->assertEquals( "Bar", $user->first_name );
        $this->assertEquals( "Foo", $user->last_name );
        $this->assertEquals( '12345trewq', $user->salt );
        $this->assertEquals( '987654321qwerty', $user->pass );
        $this->assertNull( $user->oauth_access_token );

    }

}