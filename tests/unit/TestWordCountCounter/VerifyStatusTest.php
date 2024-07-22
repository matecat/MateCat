<?php


use TestHelpers\AbstractTest;
use WordCount\CounterModel;

/**
 * @group  regression
 * @covers CounterModel::_verifyStatus
 * User: dinies
 * Date: 13/06/16
 * Time: 12.39
 */
class VerifyStatusTest extends AbstractTest {
    /**
     * @group  regression
     * @covers CounterModel::_verifyStatus
     */
    public function test__verifyStatus_without_exception_thrown() {
        $word_counter         = new CounterModel();
        $mirror_word_counter  = new ReflectionClass( $word_counter );
        $method__verifyStatus = $mirror_word_counter->getMethod( '_verifyStatus' );
        $method__verifyStatus->setAccessible( true );

        $method__verifyStatus->invoke( $word_counter, "NEW" );
        $method__verifyStatus->invoke( $word_counter, "DRAFT" );
        $method__verifyStatus->invoke( $word_counter, "TRANSLATED" );
        $method__verifyStatus->invoke( $word_counter, "APPROVED" );
        $method__verifyStatus->invoke( $word_counter, "REJECTED" );
        $method__verifyStatus->invoke( $word_counter, "FIXED" );
        $method__verifyStatus->invoke( $word_counter, "REBUTTED" );
        $this->assertTrue( true ); // test no exceptions
    }

    /**
     * @group  regression
     * @covers CounterModel::_verifyStatus
     */
    public function test__verifyStatus_with_exception_thrown_because_status_is_invalid() {
        $word_counter         = new CounterModel();
        $mirror_word_counter  = new ReflectionClass( $word_counter );
        $method__verifyStatus = $mirror_word_counter->getMethod( '_verifyStatus' );
        $method__verifyStatus->setAccessible( true );

        $this->setExpectedException( 'BadMethodCallException' );
        $method__verifyStatus->invoke( $word_counter, "BARANDFOO" );
    }
}