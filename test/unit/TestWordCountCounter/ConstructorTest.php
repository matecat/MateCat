<?php

/**
 * @group regression
 * @covers WordCount_CounterModel::__construct
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
    

public function setUp(){
    parent::setUp();
    
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
    

    /**
     * @group regression
     * @covers WordCount_CounterModel::__construct
     */
    public function test__constructor_with_ice_segments(){


        $word_counter= new WordCount_CounterModel( $this->word_count_struct );
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
     * @covers WordCount_CounterModel::__construct
     */
    public function test__constructor_with_no_args(){


        $word_counter= new WordCount_CounterModel();
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