<?php


use Model\WordCount\CounterModel;
use TestHelpers\AbstractTest;

/**
 * @group  regression
 * @covers CounterModel::equivalentStatuses
 * User: dinies
 * Date: 13/06/16
 * Time: 16.27
 */
class EquivalentStatusesTest extends AbstractTest {
    /**
     * @var CounterModel
     */
    protected $word_counter;
    protected $method_equivalentStatuses;

    public function setUp(): void {
        parent::setUp();
        $this->word_counter              = new CounterModel();
        $mirror_word_counter             = new ReflectionClass( $this->word_counter );
        $this->method_equivalentStatuses = $mirror_word_counter->getMethod( 'equivalentStatuses' );
        
    }

    /**
     * @group  regression
     * @covers CounterModel::equivalentStatuses
     * @return boolean
     */
    public function test_equivalentStatuses_true_1() {
        $this->word_counter->setOldStatus( "TRANSLATED" );
        $this->word_counter->setNewStatus( "TRANSLATED" );
        $this->assertTrue( $this->method_equivalentStatuses->invoke( $this->word_counter ) );
    }

    /**
     * @group  regression
     * @covers CounterModel::equivalentStatuses
     * @return boolean
     */
    public function test_equivalentStatuses_false() {
        $this->word_counter->setOldStatus( "TRANSLATED" );
        $this->word_counter->setNewStatus( "APPROVED" );
        $this->assertFalse( $this->method_equivalentStatuses->invoke( $this->word_counter ) );

    }

    /**
     * @group  regression
     * @covers CounterModel::equivalentStatuses
     * @return boolean
     */
    public function test_equivalentStatuses_false2() {
        $this->word_counter->setOldStatus( "APPROVED2" );
        $this->word_counter->setNewStatus( "APPROVED" );
        $this->assertFalse( $this->method_equivalentStatuses->invoke( $this->word_counter ) );

    }

}