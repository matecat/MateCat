<?php

use TestHelpers\AbstractTest;


/**
 * @group regression
 * @covers Jobs_JobDao::destroyCache
 * User: dinies
 * Date: 27/05/16
 * Time: 11.48
 */
class DestroyCacheJobTest extends AbstractTest
{
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
    /**
     * @var \Predis\Client
     */
    protected $cache;

    public function setUp()
    {
        parent::setUp();
        /**
         * job initialization
         */
        $this->id= "808080";
        $this->str_id_project = "888888";
        $this->str_password = "7barandfoo71";
        $this->str_owner="barandfoo@translated.net";
        $this->job_struct = new Jobs_JobStruct(
            array('id' => $this->id, //SET NULL FOR AUTOINCREMENT -> in this case is only stored in cache so i will chose a casual value
                'password' => $this->str_password,
                'id_project' => $this->str_id_project,
                'job_first_segment' => "182655137",
                'job_last_segment' => "182655236",
                'source' => "nl-NL",
                'target' => "de-DE",
                'tm_keys' => '[{"tm":true,"glos":true,"owner":true,"uid_transl":null,"uid_rev":null,"name":"","key":"e1f9153f48c4c7e9328d","r":true,"w":true,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]',
                'id_translator' => "",
                'job_type' => null,
                'total_time_to_edit' => "156255",
                'avg_post_editing_effort' => "0",
                'id_job_to_revise' => null,
                'last_opened_segment' => "182655204",
                'id_tms' => "1",
                'id_mt_engine' => "1",
                'create_date' => "2016-03-30 13:18:09",
                'last_update' => "2016-03-30 13:21:02",
                'disabled' => "0",
                'owner' => $this->str_owner,
                'status_owner' => "active",
                'status' => "active",
                'status_translator' => null,
                'completed' => false,
                'new_words' => "-12.60",
                'draft_words' => "0.00",
                'translated_words' => "728.15",
                'approved_words' => "0.00",
                'rejected_words' => "0.00",
                'subject' => "general",
                'payable_rates' => '{"NO_MATCH":100,"50%-74%":100,"75%-84%":60,"85%-94%":60,"95%-99%":60,"100%":30,"100%_PUBLIC":30,"REPETITIONS":30,"INTERNAL":60,"MT":85}',
                'revision_stats_typing_min' => "0",
                'revision_stats_translations_min' => "0",
                'revision_stats_terminology_min' => "0",
                'revision_stats_language_quality_min' => "0",
                'revision_stats_style_min' => "0",
                'revision_stats_typing_maj' => "0",
                'revision_stats_translations_maj' => "0",
                'revision_stats_terminology_maj' => "0",
                'revision_stats_language_quality_maj' => "0",
                'revision_stats_style_maj' => "0",
                'total_raw_wc' => "1",
                'validator' => "xxxx"
            )
        );

        $this->database_instance = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $this->job_Dao = new Jobs_JobDao($this->database_instance);
        $this->cache= new Predis\Client(INIT::$REDIS_SERVERS);
    }

    public function tearDown()
    {

        $this->cache->flushdb();
        parent::tearDown();
    }

    /**
     * @group regression
     * @covers Jobs_JobDao::destroyCache
     */
    public function test_DestroyCache_with_ID_and_Password()
    {

        $cache_key = "SELECT * FROM ".INIT::$DB_DATABASE.".`jobs` WHERE id ='". $this->id ."' AND password = '". $this->str_password ." '";

        
        $key = md5($cache_key);
        $value = serialize($this->job_struct);
        $this->cache->setex($key,20, $value);
        $output_before_destruction=$this->cache->get($key);
        $this->assertEquals($value,$output_before_destruction);
        $this->assertTrue(unserialize($output_before_destruction) instanceof Jobs_JobStruct);
        $this->job_Dao->destroyCache($this->job_struct);
        $output_after_destruction=$this->cache->get($cache_key);
        $this->assertNull($output_after_destruction);
        $this->assertFalse(unserialize($output_after_destruction) instanceof Jobs_JobStruct);
    }

    /**
     * @group regression
     * @covers Jobs_JobDao::destroyCache
     */
    public function test_DestroyCache_with_ID_Project()
    {

        $cache_key = "SELECT * FROM ( SELECT * FROM  ".INIT::$DB_DATABASE.".`jobs` WHERE id ='". $this->str_id_project ."' ORDER BY id DESC) t GROUP BY id ; ";


        $key = md5($cache_key);
        $value = serialize($this->job_struct);
        $this->cache->setex($key,20, $value);
        $output_before_destruction=$this->cache->get($key);
        $this->assertEquals($value,$output_before_destruction);
        $this->assertTrue(unserialize($output_before_destruction) instanceof Jobs_JobStruct);
        $this->job_Dao->destroyCache($this->job_struct);
        $output_after_destruction=$this->cache->get($cache_key);
        $this->assertNull($output_after_destruction);
        $this->assertFalse(unserialize($output_after_destruction) instanceof Jobs_JobStruct);
    }



}
