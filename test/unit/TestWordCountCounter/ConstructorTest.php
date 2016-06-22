<?php

/**
 * @group regression
 * @covers WordCount_Counter::__construct
 * User: dinies
 * Date: 13/06/16
 * Time: 10.34
 */
class ConstructorTest extends AbstractTest
{
    /**
     * @var  Database
     */
    protected $database_instance;
    protected $sql_delete_job;
    protected $sql_delete_first_segment;
    protected $sql_delete_second_segment;
    protected $job_id;
    protected $job_password;
    protected $first_segment_id;
    protected $second_segment_id;
    protected $job_Dao;
    protected $job_struct;
    protected $word_count_struct;
    
   // TODO::remove the db initializations of job and segments because they aren't nedeed at all in the constructor, just choose example values for id and password

public function setUp(){
    parent::setUp();

    $this->database_instance = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );

    /**
     * job initialization
     */

    $this->job_password = "7ec09d1cad61";
    $this->job_struct = new Jobs_JobStruct(
        array('id' => NULL, //SET NULL FOR AUTOINCREMENT -> in this case is only stored in cache so i will chose a casual value
            'password' => $this->job_password,
            'id_project' => "47",
            'job_first_segment' => "5659",
            'job_last_segment' => "5660",
            'source' => "de-DE",
            'target' => "it-IT",
            'tm_keys' => '[]',
            'id_translator' => "",
            'job_type' => null,
            'total_time_to_edit' => "0",
            'avg_post_editing_effort' => "0",
            'id_job_to_revise' => null,
            'last_opened_segment' => "5659",
            'id_tms' => "1",
            'id_mt_engine' => "1",
            'create_date' => "2016-06-13 10:15:30",
            'last_update' => "2016-06-13 10:15:45",
            'disabled' => "0",
            'owner' => "bar@foo.net",
            'status_owner' => "active",
            'status' => "active",
            'status_translator' => null,
            'completed' => "\0",
            'new_words' => "00.00",
            'draft_words' => "0.00",
            'translated_words' => "30.00",
            'approved_words' => "0.00",
            'rejected_words' => "0.00",
            'subject' => "medical_pharmaceutical",
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
            'dqf_key' => null,
            'total_raw_wc' => "1",
            'validator' => "xxxx"
        )
    );

    $this->job_Dao = new Jobs_JobDao($this->database_instance);
    $this->job_Dao->createFromStruct($this->job_struct);
    $this->job_id = $this->database_instance->getConnection()->lastInsertId();
    $this->sql_delete_job = "DELETE FROM ".INIT::$DB_DATABASE.".`jobs` WHERE id='" . $this->job_id . "';";


    /**
     * Segments initialization
     */
    $sql_insert_first_segment = "INSERT INTO ".INIT::$DB_DATABASE.".`segments` 
    ( internal_id, id_file, id_file_part, segment, segment_hash, raw_word_count, xliff_mrk_id, xliff_ext_prec_tags, xliff_ext_succ_tags, show_in_cattool,xliff_mrk_ext_prec_tags,xliff_mrk_ext_succ_tags) values
    ( '21922356366' , ".$this->job_id." , null , '- Auf der Fußhaut natürlich vorhandene Hornhautbakterien zersetzen sich durch auftretenden Schweiß an Ihren Füßen.' , 'e0170a2e381f1969056a7eb5e5bd0ac9', '15.00' , null, '', '' , '1' , null , null )";

    $this->database_instance->query($sql_insert_first_segment);
    $this->first_segment_id= $this->database_instance->last_insert();


    $sql_insert_second_segment = "INSERT INTO ".INIT::$DB_DATABASE.".`segments`
    ( internal_id, id_file, id_file_part, segment, segment_hash, raw_word_count, xliff_mrk_id, xliff_ext_prec_tags, xliff_ext_succ_tags, show_in_cattool,xliff_mrk_ext_prec_tags,xliff_mrk_ext_succ_tags) values 
    ( '261922356366' , ".$this->job_id." , null , '- Auf der Fußhaut natürlich vorhandene Hornhautbakterien zersetzen sich durch auftretenden Schweiß an Ihren Füßen.' , 'e0170a2e381f1969056a7eb5e5bd0ac9', '15.00' , null, '', '' , '1' , null , null ) ";

    $this->database_instance->query($sql_insert_second_segment);
    $this->second_segment_id= $this->database_instance->last_insert();

    $this->sql_delete_first_segment = "DELETE FROM ".INIT::$DB_DATABASE.".`segments` WHERE id='" . $this->first_segment_id . "';";
    $this->sql_delete_second_segment = "DELETE FROM ".INIT::$DB_DATABASE.".`segments` WHERE id='" . $this->second_segment_id . "';";


    $this->word_count_struct= new WordCount_Struct();
    $this->word_count_struct->setIdJob($this->job_id);
    $this->word_count_struct->setJobPassword($this->job_password);
    $this->word_count_struct->setNewWords(0);
    $this->word_count_struct->setDraftWords(0);
    $this->word_count_struct->setTranslatedWords(30);
    $this->word_count_struct->setApprovedWords(0);
    $this->word_count_struct->setRejectedWords(0);
    $this->word_count_struct->setIdSegment($this->first_segment_id);
    $this->word_count_struct->setOldStatus("TRANSLATED");
    $this->word_count_struct->setNewStatus("TRANSLATED");



}

    public function tearDown(){
        $this->database_instance->query($this->sql_delete_job);
        $this->database_instance->query($this->sql_delete_first_segment);
        $this->database_instance->query($this->sql_delete_second_segment);
        parent::tearDown();
    }

    /**
     * @group regression
     * @covers WordCount_Counter::__construct
     */
    public function test__constructor_with_ice_segments(){


        $word_counter= new WordCount_Counter( $this->word_count_struct );
        $mirror_word_counter= new ReflectionClass($word_counter);
        $constCache= $mirror_word_counter->getProperty('constCache');
        $constCache->setAccessible(true);

        $this->assertCount(7, $constCache->getValue($word_counter));
        $this->assertEquals("STATUS_NEW", $constCache->getValue($word_counter)['NEW']);
        $this->assertEquals("STATUS_DRAFT", $constCache->getValue($word_counter)['DRAFT']);
        $this->assertEquals("STATUS_TRANSLATED", $constCache->getValue($word_counter)['TRANSLATED']);
        $this->assertEquals("STATUS_APPROVED", $constCache->getValue($word_counter)['APPROVED']);
        $this->assertEquals("STATUS_REJECTED", $constCache->getValue($word_counter)['REJECTED']);
        $this->assertEquals("STATUS_FIXED", $constCache->getValue($word_counter)['FIXED']);
        $this->assertEquals("STATUS_REBUTTED", $constCache->getValue($word_counter)['REBUTTED']);


        $old_w_count= $mirror_word_counter->getProperty('oldWCount');
        $old_w_count->setAccessible(true);
        $this->assertTrue( $old_w_count->getValue($word_counter) instanceof WordCount_Struct);
        $this->assertEquals( $this->word_count_struct, $old_w_count->getValue($word_counter));

        $new_status_call= $mirror_word_counter->getProperty('newStatusCall');
        $new_status_call->setAccessible(true);
        $this->assertNull($new_status_call->getValue($word_counter));


        $old_status_call= $mirror_word_counter->getProperty('oldStatusCall');
        $old_status_call->setAccessible(true);
        $this->assertNull($old_status_call->getValue($word_counter));


        $new_status= $mirror_word_counter->getProperty('newStatus');
        $new_status->setAccessible(true);
        $this->assertNull($new_status->getValue($word_counter));

        $old_status= $mirror_word_counter->getProperty('oldStatus');
        $old_status->setAccessible(true);
        $this->assertNull($old_status->getValue($word_counter));

        $project= $mirror_word_counter->getProperty('project');
        $project->setAccessible(true);
        $this->assertNull($project->getValue($word_counter));



    }

    /**
     * @group regression
     * @covers WordCount_Counter::__construct
     */
    public function test__constructor_with_no_args(){


        $word_counter= new WordCount_Counter();
        $mirror_word_counter= new ReflectionClass($word_counter);
        $constCache= $mirror_word_counter->getProperty('constCache');
        $constCache->setAccessible(true);

        $this->assertCount(7, $constCache->getValue($word_counter));
        $this->assertEquals("STATUS_NEW", $constCache->getValue($word_counter)['NEW']);
        $this->assertEquals("STATUS_DRAFT", $constCache->getValue($word_counter)['DRAFT']);
        $this->assertEquals("STATUS_TRANSLATED", $constCache->getValue($word_counter)['TRANSLATED']);
        $this->assertEquals("STATUS_APPROVED", $constCache->getValue($word_counter)['APPROVED']);
        $this->assertEquals("STATUS_REJECTED", $constCache->getValue($word_counter)['REJECTED']);
        $this->assertEquals("STATUS_FIXED", $constCache->getValue($word_counter)['FIXED']);
        $this->assertEquals("STATUS_REBUTTED", $constCache->getValue($word_counter)['REBUTTED']);


        $old_w_count= $mirror_word_counter->getProperty('oldWCount');
        $old_w_count->setAccessible(true);

        $this->assertNull( $old_w_count->getValue($word_counter));

    }


}