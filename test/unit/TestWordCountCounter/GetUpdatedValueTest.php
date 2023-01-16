<?php

/**
 * @covers WordCount_CounterModel::getUpdatedValues
 * @group regression
 * User: dinies
 * Date: 13/06/16
 * Time: 16.03
 */
class GetUpdatedValueTest extends AbstractTest
{
    protected $word_counter;
    protected $job_id;
    protected $job_password;
    protected $segment_id;
    /**
     * @var WordCount_Struct
     */
    protected $word_count_struct;

    public function setup()
    {
        parent::setUp();
        $this->job_id= 9999; //sample
        $this->job_password= "bar999foo"; //sample
        $this->segment_id= "789099"; //sample

        $this->word_count_struct= new WordCount_Struct();
        $this->word_count_struct->setIdJob($this->job_id);
        $this->word_count_struct->setJobPassword($this->job_password);
        $this->word_count_struct->setIdSegment($this->segment_id);


    }

    /**
     * @covers WordCount_CounterModel::getUpdatedValues
     * @group regression
     */
    public function test_getUpdateValue_with_ice_match_no_changes()
    {

        $this->word_count_struct->setNewWords(0);
        $this->word_count_struct->setDraftWords(0);
        $this->word_count_struct->setTranslatedWords(30);
        $this->word_count_struct->setApprovedWords(0);
        $this->word_count_struct->setRejectedWords(0);
        $this->word_count_struct->setOldStatus("TRANSLATED");
        $this->word_count_struct->setNewStatus("TRANSLATED");

        $this->word_counter = new WordCount_CounterModel($this->word_count_struct);
        $this->word_counter->setOldStatus("TRANSLATED");
        $this->word_counter->setNewStatus("TRANSLATED");
        
        $result = $this->word_counter->getUpdatedValues("15.00");
        $this->assertTrue($result instanceof WordCount_Struct);
        $this->assertEquals($this->job_id, $result->getIdJob());
        $this->assertEquals($this->job_password, $result->getJobPassword());
        $this->assertEquals($this->segment_id, $result->getIdSegment());
        $this->assertEquals(0, $result->getNewWords());
        $this->assertEquals(0, $result->getDraftWords());
        $this->assertEquals(0, $result->getTranslatedWords());
        $this->assertEquals(0, $result->getApprovedWords());
        $this->assertEquals(0, $result->getRejectedWords());
        $this->assertEquals("TRANSLATED", $result->getOldStatus());
        $this->assertEquals("TRANSLATED", $result->getNewStatus());
    }

    /**
     * @covers WordCount_CounterModel::getUpdatedValues
     * @group regression
     */
    public function test_getUpdateValue_with_rejection()
    {

        $this->word_count_struct->setNewWords(0);
        $this->word_count_struct->setDraftWords(0);
        $this->word_count_struct->setTranslatedWords(30);
        $this->word_count_struct->setApprovedWords(0);
        $this->word_count_struct->setRejectedWords(0);
        $this->word_count_struct->setOldStatus("TRANSLATED");
        $this->word_count_struct->setNewStatus("REJECTED");

        $this->word_counter = new WordCount_CounterModel($this->word_count_struct);
        $this->word_counter->setOldStatus("TRANSLATED");
        $this->word_counter->setNewStatus("REJECTED");

        $result = $this->word_counter->getUpdatedValues("15.00");

        $this->assertTrue($result instanceof WordCount_Struct);
        $this->assertEquals($this->job_id, $result->getIdJob());
        $this->assertEquals($this->job_password, $result->getJobPassword());
        $this->assertEquals($this->segment_id, $result->getIdSegment());
        $this->assertEquals(0, $result->getNewWords());
        $this->assertEquals(0, $result->getDraftWords());
        $this->assertEquals("-15.00", $result->getTranslatedWords());
        $this->assertEquals(0, $result->getApprovedWords());
        $this->assertEquals("+15.00", $result->getRejectedWords());
        $this->assertEquals("TRANSLATED", $result->getOldStatus());
        $this->assertEquals("REJECTED", $result->getNewStatus());
    }


    /**
     * @covers WordCount_CounterModel::getUpdatedValues
     * @group regression
     */
    public function test_getUpdateValue_from_draft_to_approved()
    {

        $this->word_count_struct->setNewWords(0);
        $this->word_count_struct->setDraftWords(30);
        $this->word_count_struct->setTranslatedWords(0);
        $this->word_count_struct->setApprovedWords(0);
        $this->word_count_struct->setRejectedWords(0);
        $this->word_count_struct->setOldStatus("DRAFT");
        $this->word_count_struct->setNewStatus("APPROVED");

        $this->word_counter = new WordCount_CounterModel($this->word_count_struct);
        $this->word_counter->setOldStatus("DRAFT");
        $this->word_counter->setNewStatus("APPROVED");

        $result = $this->word_counter->getUpdatedValues("15.00");

        $this->assertTrue($result instanceof WordCount_Struct);
        $this->assertEquals($this->job_id, $result->getIdJob());
        $this->assertEquals($this->job_password, $result->getJobPassword());
        $this->assertEquals($this->segment_id, $result->getIdSegment());
        $this->assertEquals(0, $result->getNewWords());
        $this->assertEquals("-15.00", $result->getDraftWords());
        $this->assertEquals(0, $result->getTranslatedWords());
        $this->assertEquals("+15.00", $result->getApprovedWords());
        $this->assertEquals(0, $result->getRejectedWords());
        $this->assertEquals("DRAFT", $result->getOldStatus());
        $this->assertEquals("APPROVED", $result->getNewStatus());
    }

    /**
     * @covers WordCount_CounterModel::getUpdatedValues
     * @group regression
     */
    public function test_getUpdateValue_with_null_argument()
    {

        $this->word_counter = new WordCount_CounterModel();
        $this->setExpectedException('LogicException');
        $this->word_counter->getUpdatedValues("15.00");


    }

}