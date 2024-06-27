<?php


use TestHelpers\AbstractTest;
use WordCount\CounterModel;
use WordCount\WordCountStruct;

/**
 * @group regression
 * @covers CounterModel::setOldWordCount
 * User: dinies
 * Date: 15/06/16
 * Time: 17.27
 */
class SetOldWordCountTest extends AbstractTest
{

    /**
     * @group regression
     * @covers CounterModel::setOldWordCount
    */
    public function test_setOldWordCount(){

        $word_count= new CounterModel();


        $word_count_struct= new WordCountStruct();
        $word_count_struct->setIdJob(999); //sample
        $word_count_struct->setJobPassword("989bob98"); //sample
        $word_count_struct->setNewWords(0);
        $word_count_struct->setDraftWords(0);
        $word_count_struct->setTranslatedWords(30);
        $word_count_struct->setApprovedWords(0);
        $word_count_struct->setRejectedWords(0);
        $word_count_struct->setIdSegment(65499);  //sample
        $word_count_struct->setOldStatus("TRANSLATED");
        $word_count_struct->setNewStatus("TRANSLATED");

        $mirror_word_count= new ReflectionClass($word_count);
        $old_word_count_property= $mirror_word_count->getProperty('oldWCount');
        $old_word_count_property->setAccessible(true);
        $this->assertNull($old_word_count_property->getValue($word_count));

        $word_count->setOldWordCount($word_count_struct);

        $this->assertEquals($word_count_struct, $old_word_count_property->getValue($word_count));
    }
}