<?php


use TestHelpers\AbstractTest;
use WordCount\CounterModel;
use WordCount\WordCountStruct;

/**
 * @covers CounterModel::getUpdatedValues
 * @group  regression
 * User: dinies
 * Date: 13/06/16
 * Time: 16.03
 */
class GetUpdatedValueTest extends AbstractTest {
    protected $word_counter;
    protected $job_id;
    protected $job_password;
    protected $segment_id;
    /**
     * @var WordCountStruct
     */
    protected $word_count_struct;

    public function setup() {
        parent::setUp();
        $this->job_id       = 9999; //sample
        $this->job_password = "bar999foo"; //sample
        $this->segment_id   = "789099"; //sample

        $this->word_count_struct = new WordCountStruct();
        $this->word_count_struct->setIdJob( $this->job_id );
        $this->word_count_struct->setJobPassword( $this->job_password );
        $this->word_count_struct->setIdSegment( $this->segment_id );


    }

    /**
     * @covers CounterModel::getUpdatedValues
     * @group  regression
     */
    public function test_getUpdateValue_with_ice_match_no_changes() {

        $word_count_struct = @$this->getMockBuilder( WordCountStruct::class )->getMock();
        $word_count_struct->setIdJob( $this->job_id );
        $word_count_struct->setJobPassword( $this->job_password );
        $word_count_struct->setIdSegment( $this->segment_id );
        $word_count_struct->setNewWords( 0 );
        $word_count_struct->setDraftWords( 0 );
        $word_count_struct->setTranslatedWords( 30 );
        $word_count_struct->setApprovedWords( 0 );
        $word_count_struct->setRejectedWords( 0 );
        $word_count_struct->setOldStatus( "TRANSLATED" );
        $word_count_struct->setNewStatus( "TRANSLATED" );

        $this->word_counter = new CounterModel( $this->word_count_struct );
        $this->word_counter->setOldStatus( "TRANSLATED" );
        $this->word_counter->setNewStatus( "TRANSLATED" );

        $result = $this->word_counter->getUpdatedValues( "15.00", null );
        $this->assertTrue( $result instanceof WordCountStruct );
        $this->assertEquals( $this->job_id, $result->getIdJob() );
        $this->assertEquals( $this->job_password, $result->getJobPassword() );
        $this->assertEquals( $this->segment_id, $result->getIdSegment() );
        $this->assertEquals( 0, $result->getNewWords() );
        $this->assertEquals( 0, $result->getDraftWords() );
        $this->assertEquals( 0, $result->getTranslatedWords() );
        $this->assertEquals( 0, $result->getApprovedWords() );
        $this->assertEquals( 0, $result->getRejectedWords() );
        $this->assertEquals( "TRANSLATED", $result->getOldStatus() );
        $this->assertEquals( "TRANSLATED", $result->getNewStatus() );

        $word_count_struct->expects( $this->never() )->method( 'setTranslatedWords' );

    }

    /**
     * @covers CounterModel::getUpdatedValues
     * @group  regression
     */
    public function test_getUpdateValue_with_rejection() {

        $word_count_struct = new WordCountStruct();
        $word_count_struct->setIdJob( $this->job_id );
        $word_count_struct->setJobPassword( $this->job_password );
        $word_count_struct->setIdSegment( $this->segment_id );
        $word_count_struct->setNewWords( 0 );
        $word_count_struct->setDraftWords( 0 );
        $word_count_struct->setTranslatedWords( 30 );
        $word_count_struct->setApprovedWords( 0 );
        $word_count_struct->setRejectedWords( 0 );

        $word_count_struct->setTranslatedRawWords( 40 );
        $word_count_struct->setOldStatus( "TRANSLATED" );
        $word_count_struct->setNewStatus( "REJECTED" );

        $this->word_counter = new CounterModel( $this->word_count_struct );
        $this->word_counter->setOldStatus( "TRANSLATED" );
        $this->word_counter->setNewStatus( "REJECTED" );

        $result = $this->word_counter->getUpdatedValues( 15, 20 );

        $this->assertTrue( $result instanceof WordCountStruct );
        $this->assertEquals( $this->job_id, $result->getIdJob() );
        $this->assertEquals( $this->job_password, $result->getJobPassword() );
        $this->assertEquals( $this->segment_id, $result->getIdSegment() );
        $this->assertEquals( 0, $result->getNewWords() );
        $this->assertEquals( 0, $result->getDraftWords() );
        $this->assertEquals( -15, $result->getTranslatedWords() );
        $this->assertEquals( 0, $result->getApprovedWords() );
        $this->assertEquals( 15, $result->getRejectedWords() );

        $this->assertEquals( 0, $result->getNewRawWords() );
        $this->assertEquals( 0, $result->getDraftRawWords() );
        $this->assertEquals( -20, $result->getTranslatedRawWords() );
        $this->assertEquals( 0, $result->getApprovedRawWords() );
        $this->assertEquals( 20, $result->getRejectedRawWords() );

        $this->assertEquals( "TRANSLATED", $result->getOldStatus() );
        $this->assertEquals( "REJECTED", $result->getNewStatus() );

    }


    /**
     * @covers CounterModel::getUpdatedValues
     * @group  regression
     */
    public function test_getUpdateValue_from_draft_to_approved() {

        $this->word_count_struct->setNewWords( 0 );
        $this->word_count_struct->setDraftWords( 30 );
        $this->word_count_struct->setTranslatedWords( 0 );
        $this->word_count_struct->setApprovedWords( 0 );
        $this->word_count_struct->setRejectedWords( 0 );
        $this->word_count_struct->setOldStatus( "DRAFT" );
        $this->word_count_struct->setNewStatus( "APPROVED" );

        $this->word_counter = new CounterModel( $this->word_count_struct );
        $this->word_counter->setOldStatus( "DRAFT" );
        $this->word_counter->setNewStatus( "APPROVED" );

        $result = $this->word_counter->getUpdatedValues( 15, null );

        $this->assertTrue( $result instanceof WordCountStruct );
        $this->assertEquals( $this->job_id, $result->getIdJob() );
        $this->assertEquals( $this->job_password, $result->getJobPassword() );
        $this->assertEquals( $this->segment_id, $result->getIdSegment() );
        $this->assertEquals( 0, $result->getNewWords() );
        $this->assertEquals( "-15.00", $result->getDraftWords() );
        $this->assertEquals( 0, $result->getTranslatedWords() );
        $this->assertEquals( "+15.00", $result->getApprovedWords() );
        $this->assertEquals( 0, $result->getRejectedWords() );
        $this->assertEquals( "DRAFT", $result->getOldStatus() );
        $this->assertEquals( "APPROVED", $result->getNewStatus() );
    }

    /**
     * @covers CounterModel::getUpdatedValues
     * @group  regression
     */
    public function test_getUpdateValue_with_null_argument() {

        $this->word_counter = new CounterModel();
        $this->expectException( LogicException::class );
        $this->word_counter->getUpdatedValues( 15, null );


    }

}