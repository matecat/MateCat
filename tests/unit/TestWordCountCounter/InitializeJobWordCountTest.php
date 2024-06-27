<?php


use TestHelpers\AbstractTest;
use WordCount\CounterModel;
use WordCount\WordCounterDao;
use WordCount\WordCountStruct;

/**
 * @covers CounterModel::initializeJobWordCount
 * @group  regression
 * User: dinies
 * Date: 30/06/16
 * Time: 10.52
 */
class InitializeJobWordCountTest extends AbstractTest {
    protected $database_instance;


    /**
     * @group  regression
     * @covers CounterModel::initializeJobWordCount
     */
    function test_initializeJobWordCount() {

        $wordCounterMock = @$this->getMockBuilder( WordCounterDao::class )->getMock();
        $wordCounterMock
                ->expects( $this->once() )
                ->method( 'getStatsForJob' )
                ->with(
                        $this->equalTo( 1 ),
                        $this->isEmpty(),
                        $this->equalTo( 'a_password' )
                )->willReturn(
                        json_decode( '[{
                                        "id": 1,
                                        "TOTAL": 5.4,
                                        "NEW": 2.4,
                                        "DRAFT": 0,
                                        "TRANSLATED": 0,
                                        "APPROVED": 3,
                                        "APPROVED2": 1,
                                        "REJECTED": 0,
                                        "TOTAL_RAW": 17,
                                        "NEW_RAW": 4,
                                        "DRAFT_RAW": 0,
                                        "TRANSLATED_RAW": 6,
                                        "APPROVED_RAW": 5,
                                        "APPROVED2_RAW": 2,
                                        "REJECTED_RAW": 0
                                      }]', true
                        )
                );

        $wordCounterMock
                ->expects( $spy = $this->once() )
                ->method( 'initializeWordCount' )
                ->with( $this->isInstanceOf( WordCountStruct::class ) )
                ->willReturn( 1 );

        $wordCount = new CounterModel();
        $result    = $wordCount->initializeJobWordCount( 1, 'a_password', $wordCounterMock );

        $invocation = $spy->getInvocations()[ 0 ];

        $this->assertEquals( $invocation->parameters[ 0 ], $result );
        $this->assertSame( $invocation->parameters[ 0 ], $result ); // same instance

        $this->assertTrue( $result instanceof WordCountStruct );
        $this->assertEquals( 1, $result->getIdJob() );
        $this->assertEquals( 'a_password', $result->getJobPassword() );
        $this->assertNull( $result->getIdSegment() );
        $this->assertNull( $result->getOldStatus() );
        $this->assertNull( $result->getNewStatus() );

        $this->assertEquals( 2.4, $result->getNewWords() );
        $this->assertEquals( 0, $result->getDraftWords() );
        $this->assertEquals( 0, $result->getTranslatedWords() );
        $this->assertEquals( 3, $result->getApprovedWords() );
        $this->assertEquals( 1, $result->getApproved2Words() );
        $this->assertEquals( 0, $result->getRejectedWords() );
        $this->assertEquals( 6.4, $result->getTotal() );

        $this->assertEquals( 4, $result->getNewRawWords() );
        $this->assertEquals( 0, $result->getDraftRawWords() );
        $this->assertEquals( 6, $result->getTranslatedRawWords() );
        $this->assertEquals( 5, $result->getApprovedRawWords() );
        $this->assertEquals( 2, $result->getApproved2RawWords() );
        $this->assertEquals( 0, $result->getRejectedRawWords() );
        $this->assertEquals( 17, $result->getRawTotal() );

    }
}