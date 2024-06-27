<?php

use TestHelpers\AbstractTest;


/**
 * Created by JetBrains PhpStorm.
 * User: domenico
 * Date: 09/10/13
 * Time: 15.31
 *
 */

class JobStructTest extends AbstractTest {

    /**
     * @var Jobs_JobDao
     */
    public $jobDao;

    /**
     * @var Jobs_JobStruct
     */
    public $originalJobStruct;

    public function setUp(){

        parent::setUp();

        $this->originalJobStruct = new Jobs_JobStruct(
            array(
                    'id' => null, //SET NULL FOR AUTOINCREMENT
                    'password' => "0f020dee031d",
                    'id_project' => "99999",
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
                    'owner' => "domenico@translated.net",
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

        $this->jobDao = new Jobs_JobDao( Database::obtain()->getConnection() );

    }

    public function testAutoIncrementOnCreate(){

        $jobStruct = $this->jobDao->createFromStruct( $this->originalJobStruct );

        $this->assertInstanceOf( 'Jobs_JobStruct', $jobStruct );
        $this->assertNotEquals( $jobStruct, $this->originalJobStruct );
        $this->assertTrue( (int)$jobStruct->id != 0 ); //PDO returns integers as string without mysqlnd

    }

    public function testArrayAccessGet(){
        $this->assertEquals( '0f020dee031d', $this->originalJobStruct['password'] );
    }

    public function testArrayAccesSet(){
        $jobStruct = $this->jobDao->createFromStruct( $this->originalJobStruct );

        $jobStruct['password'] = 123;
        $this->assertEquals( 123, $jobStruct['password'] );
    }

    public function testArrayAccessUnset(){
        $jobStruct = $this->jobDao->createFromStruct( $this->originalJobStruct );

        unset($jobStruct['password']);
        $this->assertNull( $jobStruct['password'] );
    }

    public function testArrayAccessOffsetExists(){
        $this->assertTrue( empty( $this->originalJobStruct['status_translator'] ) );
        $this->assertTrue( isset( $this->originalJobStruct['status_translator'] ) );
    }

    public function testPropertyAccess(){
        $jobStruct = $this->jobDao->createFromStruct( $this->originalJobStruct );

        $id = $jobStruct->id;
        $jobStruct->id = 1234;
        $this->assertNotEquals( $id, $jobStruct->id );
    }

}
