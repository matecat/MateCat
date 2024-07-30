<?php


use TestHelpers\AbstractTest;
use WordCount\CounterModel;
use WordCount\WordCountStruct;

/**
 * @group regression
 * @covers CounterModel::sumDifferentials
 * User: dinies
 * Date: 14/06/16
 * Time: 17.16
 */
class SumDifferentialsTest extends AbstractTest
{
    protected $word_counter;
    protected $job_id;
    protected $job_password;
    protected $segment_id;
    /**
     * @var WordCountStruct
     */
    protected $word_count_struct;

    public function setup()
    {
        parent::setUp();
        $this->job_id= 9999; //sample
        $this->job_password= "bar999foo"; //sample
        $this->segment_id= "789099"; //sample

        $this->word_count_struct= new WordCountStruct();
        $this->word_count_struct->setIdJob($this->job_id);
        $this->word_count_struct->setJobPassword($this->job_password);
        $this->word_count_struct->setIdSegment($this->segment_id);


    }
    /**
     * @covers CounterModel::sumDifferentials
     * @group regression
     *
     */
    public function test_sumDifferentials_with_rejection()
    {

        $this->word_count_struct->setNewWords(0);
        $this->word_count_struct->setDraftWords(0);
        $this->word_count_struct->setTranslatedWords(30);
        $this->word_count_struct->setApprovedWords(0);
        $this->word_count_struct->setRejectedWords(0);
        $this->word_count_struct->setOldStatus("TRANSLATED");
        $this->word_count_struct->setNewStatus("REJECTED");

        $this->word_counter = new CounterModel($this->word_count_struct);
        $this->word_counter->setOldStatus("TRANSLATED");
        $this->word_counter->setNewStatus("REJECTED");


        $word_count_struct_param= new WordCountStruct();
        $word_count_struct_param->setIdJob($this->job_id);
        $word_count_struct_param->setJobPassword($this->job_password);
        $word_count_struct_param->setIdSegment($this->segment_id);
        $word_count_struct_param->setNewWords(0);
        $word_count_struct_param->setDraftWords(0);
        $word_count_struct_param->setTranslatedWords("-15.00");
        $word_count_struct_param->setApprovedWords(0);
        $word_count_struct_param->setRejectedWords("+15.00");
        $word_count_struct_param->setOldStatus("TRANSLATED");
        $word_count_struct_param->setNewStatus("REJECTED");

        $struct_wrapper[]= $word_count_struct_param;
        $result = $this->word_counter->sumDifferentials($struct_wrapper);

        $this->assertTrue($result instanceof WordCountStruct);
        $this->assertEquals($this->job_id, $result->getIdJob());
        $this->assertEquals($this->job_password, $result->getJobPassword());
        $this->assertEquals($this->segment_id, $result->getIdSegment());
        $this->assertEquals(0, $result->getNewWords());
        $this->assertEquals(0, $result->getDraftWords());
        $this->assertEquals(-15, $result->getTranslatedWords());
        $this->assertEquals(0, $result->getApprovedWords());
        $this->assertEquals(15, $result->getRejectedWords());
        $this->assertEquals("TRANSLATED", $result->getOldStatus());
        $this->assertEquals("REJECTED", $result->getNewStatus());
    }

    /**
     * @covers CounterModel::sumDifferentials
     * @group regression
     *
     */
    public function test_sumDifferentials_with_chunks_of_split_from_NEW_to_APPROVED()
    {

        $this->word_count_struct->setNewWords(23);
        $this->word_count_struct->setDraftWords(0);
        $this->word_count_struct->setTranslatedWords(0);
        $this->word_count_struct->setApprovedWords(0);
        $this->word_count_struct->setRejectedWords(0);
        $this->word_count_struct->setOldStatus("NEW");
        $this->word_count_struct->setNewStatus("APPROVED");


        $this->word_counter = new CounterModel($this->word_count_struct);
        $this->word_counter->setOldStatus("NEW");
        $this->word_counter->setNewStatus("APPROVED");



        $first_word_struct_param= new WordCountStruct();
        $first_word_struct_param->setIdJob($this->job_id);
        $first_word_struct_param->setJobPassword($this->job_password);
        $first_word_struct_param->setIdSegment($this->segment_id);
        $first_word_struct_param->setNewWords("-5.00");
        $first_word_struct_param->setDraftWords(0);
        $first_word_struct_param->setTranslatedWords(0);
        $first_word_struct_param->setApprovedWords("+5.00");
        $first_word_struct_param->setRejectedWords(0);
        $first_word_struct_param->setOldStatus("NEW");
        $first_word_struct_param->setNewStatus("APPROVED");


        $second_word_struct_param= new WordCountStruct();
        $second_word_struct_param->setIdJob($this->job_id);
        $second_word_struct_param->setJobPassword($this->job_password);
        $second_word_struct_param->setIdSegment($this->segment_id);
        $second_word_struct_param->setNewWords("-18.00");
        $second_word_struct_param->setDraftWords(0);
        $second_word_struct_param->setTranslatedWords(0);
        $second_word_struct_param->setApprovedWords("+18.00");
        $second_word_struct_param->setRejectedWords(0);
        $second_word_struct_param->setOldStatus("NEW");
        $second_word_struct_param->setNewStatus("APPROVED");

        $struct_wrapper= array(
            '0' => $first_word_struct_param,
            '1' => $second_word_struct_param
                    );

        $result = $this->word_counter->sumDifferentials($struct_wrapper);

        $this->assertTrue($result instanceof WordCountStruct);
        $this->assertEquals($this->job_id, $result->getIdJob());
        $this->assertEquals($this->job_password, $result->getJobPassword());
        $this->assertEquals($this->segment_id, $result->getIdSegment());
        $this->assertEquals(-23, $result->getNewWords());
        $this->assertEquals(0, $result->getDraftWords());
        $this->assertEquals(0, $result->getTranslatedWords());
        $this->assertEquals(23, $result->getApprovedWords());
        $this->assertEquals(0, $result->getRejectedWords());
        $this->assertEquals("NEW", $result->getOldStatus());
        $this->assertEquals("APPROVED", $result->getNewStatus());
    }
}