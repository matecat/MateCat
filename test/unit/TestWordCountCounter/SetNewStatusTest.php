<?php

/**
 * @group regression
 * @covers CounterModel::setNewStatus
 * User: dinies
 * Date: 13/06/16
 * Time: 12.25
 */
class SetNewStatusTest extends AbstractTest
{

    /**
     * @var CounterModel
     */
    protected $word_counter;
    protected $mirror_word_counter;
    protected $new_status;
    protected $new_status_call;


    public function setUp()
    {
        $this->word_counter = new CounterModel();
        $this->mirror_word_counter = new ReflectionClass($this->word_counter);
        $this->new_status = $this->mirror_word_counter->getProperty('newStatus');
        $this->new_status->setAccessible(true);

        $this->new_status_call = $this->mirror_word_counter->getProperty('newStatusCall');
        $this->new_status_call->setAccessible(true);
    }


    /**
     * @group regression
     * @covers CounterModel::setNewStatus
     *
     * @param "NEW"
     */
    public function test_setNewStatus_NEW()
    {
        $this->word_counter->setNewStatus( "NEW" );
        $this->assertEquals("NewWords", $this->new_status_call->getValue($this->word_counter));
        $this->assertEquals("NEW", $this->new_status->getValue($this->word_counter));
    }

    /**
     * @group regression
     * @covers CounterModel::setNewStatus
     *
     * @param "DRAFT"
     */
    public function test_setNewStatus_DRAFT()
    {
        $this->word_counter->setNewStatus( "DRAFT" );
        $this->assertEquals("DraftWords", $this->new_status_call->getValue($this->word_counter));
        $this->assertEquals("DRAFT", $this->new_status->getValue($this->word_counter));
    }

    /**
     * @group regression
     * @covers CounterModel::setNewStatus
     *
     * @param "TRANSLATED"
     */
    public function test_setNewStatus_TRANSLATED()
    {
        $this->word_counter->setNewStatus( "TRANSLATED" );
        $this->assertEquals("TranslatedWords", $this->new_status_call->getValue($this->word_counter));
        $this->assertEquals("TRANSLATED", $this->new_status->getValue($this->word_counter));
    }

    /**
     * @group regression
     * @covers CounterModel::setNewStatus
     *
     * @param "APPROVED"
     */
    public function test_setNewStatus_APPROVED()
    {
        $this->word_counter->setNewStatus( "APPROVED" );
        $this->assertEquals("ApprovedWords", $this->new_status_call->getValue($this->word_counter));
        $this->assertEquals("APPROVED", $this->new_status->getValue($this->word_counter));
    }
    /**
     * @group  regression
     * @covers CounterModel::setNewStatus
     *
     * @param "REJECTED"
     */
    public function test_setNewStatus_REJECTED()
    {
        $this->word_counter->setNewStatus( "REJECTED" );
        $this->assertEquals("RejectedWords", $this->new_status_call->getValue($this->word_counter));
        $this->assertEquals("REJECTED", $this->new_status->getValue($this->word_counter));
    }

    /**
     * @group  regression
     * @covers CounterModel::setNewStatus
     *
     * @param "FIXED"
     */
    public function test_setNewStatus_FIXED()
    {
        $this->word_counter->setNewStatus( "FIXED" );
        $this->assertEquals("TranslatedWords", $this->new_status_call->getValue($this->word_counter));
        $this->assertEquals("FIXED", $this->new_status->getValue($this->word_counter));
    }

    /**
     * @group  regression
     * @covers CounterModel::setNewStatus
     *
     * @param "REBUTTED"
     */
    public function test_setNewStatus_REBUTTED()
    {
        $this->word_counter->setNewStatus( "REBUTTED" );
        $this->assertEquals("TranslatedWords", $this->new_status_call->getValue($this->word_counter));
        $this->assertEquals("REBUTTED", $this->new_status->getValue($this->word_counter));
    }

    /**
     * @group  regression
     * @covers CounterModel::setNewStatus
     *
     * @param "BARANDFOO"
     */
    public function test_setNewStatus_BARANDFOO()
    {
        $this->setExpectedException('BadMethodCallException');
        $this->word_counter->setNewStatus( "BARANDFOO" );
    }
    
}