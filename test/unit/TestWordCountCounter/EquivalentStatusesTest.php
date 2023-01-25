<?php

/**
 * @group regression
 * @covers WordCount_CounterModel::equivalentStatuses
 * User: dinies
 * Date: 13/06/16
 * Time: 16.27
 */
class EquivalentStatusesTest extends AbstractTest
{
    /**
     * @var WordCount_CounterModel
     */
    protected $word_counter;
    protected $method_equivalentStatuses;
    public function setUp()
    {
        $this->word_counter = new WordCount_CounterModel();
        $mirror_word_counter= new ReflectionClass($this->word_counter);
        $this->method_equivalentStatuses = $mirror_word_counter->getMethod('equivalentStatuses');
        $this->method_equivalentStatuses->setAccessible(true);
    }
    /**
     * @group regression
     * @covers WordCount_CounterModel::equivalentStatuses
     * @return boolean
     */
    public function test_equivalentStatuses_true_1(){
        $this->word_counter->setOldStatus("TRANSLATED");
        $this->word_counter->setNewStatus("TRANSLATED");
        $this->assertTrue($this->method_equivalentStatuses->invoke($this->word_counter));
    }


    /**
     * @group regression
     * @covers WordCount_CounterModel::equivalentStatuses
     * @return boolean
     */
    public function test_equivalentStatuses_true_2(){
        $this->word_counter->setOldStatus("REJECTED");
        $this->word_counter->setNewStatus("REBUTTED");
        $this->assertFalse($this->method_equivalentStatuses->invoke($this->word_counter));

    }

    /**
     * @group regression
     * @covers WordCount_CounterModel::equivalentStatuses
     * @return boolean
     */
    public function test_equivalentStatuses_false(){
        $this->word_counter->setOldStatus("FIXED");
        $this->word_counter->setNewStatus("REBUTTED");
        $this->assertTrue($this->method_equivalentStatuses->invoke($this->word_counter));

    }
}