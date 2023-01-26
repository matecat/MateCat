<?php

/**
 * @group regression
 * @covers WordCount_CounterModel::setOldStatus
 * User: dinies
 * Date: 13/06/16
 * Time: 12.34
 */
class SetOldStatusTest extends AbstractTest
{
    /**
     * @var WordCount_CounterModel
     */
    protected $word_counter;
    protected $mirror_word_counter;
    protected $old_status;
    protected $old_status_call;


    public function setUp()
    {
        $this->word_counter = new WordCount_CounterModel();
        $this->mirror_word_counter = new ReflectionClass($this->word_counter);
        $this->old_status = $this->mirror_word_counter->getProperty('oldStatus');
        $this->old_status->setAccessible(true);

        $this->old_status_call = $this->mirror_word_counter->getProperty('oldStatusCall');
        $this->old_status_call->setAccessible(true);
    }


    /**
     * @group regression
     * @covers WordCount_CounterModel::setOldStatus
     *
     * @param "NEW"
     */
    public function test_setOldStatus_NEW()
    {
        $this->word_counter->setOldStatus( "NEW" );
        $this->assertEquals("NewWords", $this->old_status_call->getValue($this->word_counter));
        $this->assertEquals("NEW", $this->old_status->getValue($this->word_counter));
    }

    /**
     * @group regression
     * @covers WordCount_CounterModel::setOldStatus
     *
     * @param "DRAFT"
     */
    public function test_setOldStatus_DRAFT()
    {
        $this->word_counter->setOldStatus( "DRAFT" );
        $this->assertEquals("DraftWords", $this->old_status_call->getValue($this->word_counter));
        $this->assertEquals("DRAFT", $this->old_status->getValue($this->word_counter));
    }

    /**
     * @group regression
     * @covers WordCount_CounterModel::setOldStatus
     *
     * @param "TRANSLATED"
     */
    public function test_setOldStatus_TRANSLATED()
    {
        $this->word_counter->setOldStatus( "TRANSLATED" );
        $this->assertEquals("TranslatedWords", $this->old_status_call->getValue($this->word_counter));
        $this->assertEquals("TRANSLATED", $this->old_status->getValue($this->word_counter));
    }

    /**
     * @group regression
     * @covers WordCount_CounterModel::setOldStatus
     *
     * @param "APPROVED"
     */
    public function test_setOldStatus_APPROVED()
    {
        $this->word_counter->setOldStatus( "APPROVED" );
        $this->assertEquals("ApprovedWords", $this->old_status_call->getValue($this->word_counter));
        $this->assertEquals("APPROVED", $this->old_status->getValue($this->word_counter));
    }
    /**
     * @group  regression
     * @covers WordCount_CounterModel::setOldStatus
     *
     * @param "REJECTED"
     */
    public function test_setOldStatus_REJECTED()
    {
        $this->word_counter->setOldStatus( "REJECTED" );
        $this->assertEquals("RejectedWords", $this->old_status_call->getValue($this->word_counter));
        $this->assertEquals("REJECTED", $this->old_status->getValue($this->word_counter));
    }

    /**
     * @group  regression
     * @covers WordCount_CounterModel::setOldStatus
     *
     * @param "FIXED"
     */
    public function test_setOldStatus_FIXED()
    {
        $this->word_counter->setOldStatus( "FIXED" );
        $this->assertEquals("TranslatedWords", $this->old_status_call->getValue($this->word_counter));
        $this->assertEquals("FIXED", $this->old_status->getValue($this->word_counter));
    }

    /**
     * @group  regression
     * @covers WordCount_CounterModel::setOldStatus
     *
     * @param "REBUTTED"
     */
    public function test_setOldStatus_REBUTTED()
    {
        $this->word_counter->setOldStatus( "REBUTTED" );
        $this->assertEquals("TranslatedWords", $this->old_status_call->getValue($this->word_counter));
        $this->assertEquals("REBUTTED", $this->old_status->getValue($this->word_counter));
    }

    /**
     * @group  regression
     * @covers WordCount_CounterModel::setOldStatus
     *
     * @param "BARANDFOO"
     */
    public function test_setOldStatus_BARANDFOO()
    {
        $this->setExpectedException('BadMethodCallException');
        $this->word_counter->setOldStatus( "BARANDFOO" );
    }

}