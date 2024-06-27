<?php

use AsyncTasks\Workers\SetContributionWorker;
use Contribution\ContributionSetStruct;
use Matecat\SubFiltering\MateCatFilter;
use TaskRunner\Commons\ContextList;
use TaskRunner\Commons\QueueElement;
use TestHelpers\AbstractTest;


/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 07/05/16
 * Time: 23:42
 */
class SetContributionWorkerTest extends AbstractTest implements SplObserver {

    protected $featureSet;
    /**
     * @var MateCatFilter
     */
    protected $filter;

    /**
     * @param SplSubject $subject
     */
    public function update( SplSubject $subject ) {
        // Do Nothing, should be used to log
        /**
         * @var $subject SetContributionWorker
         */
        $this->assertNotEmpty( $subject->getLogMsg() );

    }

    /**
     * @var ContributionSetStruct
     */
    protected $contributionStruct;

    /**
     * @var QueueElement
     */
    protected $queueElement;

    /**
     * @var StompFrame
     */
    protected $message;

    /**
     * @var ContextList
     */
    protected $contextList;

    /**
     * @var AMQHandler
     */
    protected $amq;

    /**
     * @return void
     * @throws Exception
     */
    public function setUp() {

        parent::setUp();

        $this->featureSet = new FeatureSet();
        $this->featureSet->loadFromString( "translation_versions,review_extended,mmt,airbnb" );
        //$featureSet->loadFromString( "project_completion,translation_versions,qa_check_glossary,microsoft" );

        $this->filter = MateCatFilter::getInstance( $this->featureSet, 'en-US', 'it-IT', [] );

        //Reference Queue object
        $this->contributionStruct                       = new ContributionSetStruct();
        $this->contributionStruct->fromRevision         = true;
        $this->contributionStruct->id_job               = 1999999;
        $this->contributionStruct->job_password         = "1d7903464318";
        $this->contributionStruct->segment              = $this->filter->fromLayer2ToLayer0( '<g id="pt2">WASHINGTON </g><g id="pt3">— The Treasury Department and Internal Revenue Service today requested public comment on issues relating to the shared responsibility provisions included in the Affordable Care Act that will apply to certain employers starting in 2014.</g>' );
        $this->contributionStruct->translation          = $this->filter->fromLayer2ToLayer0( '<g id="pt2">WASHINGTON </g><g id="pt3">- Il Dipartimento del Tesoro e Agenzia delle Entrate oggi ha chiesto un commento pubblico su questioni relative alle disposizioni di responsabilità condivise incluse nel Affordable Care Act che si applicheranno a certi datori di lavoro a partire dal 2014.</g>' );
        $this->contributionStruct->api_key              = 'demo@matecat.com';
        $this->contributionStruct->uid                  = 1234;
        $this->contributionStruct->oldTranslationStatus = 'NEW';
        $this->contributionStruct->oldSegment           = $this->contributionStruct->segment; //we do not change the segment source
        $this->contributionStruct->oldTranslation       = $this->contributionStruct->translation . " TEST";

        $this->queueElement            = new QueueElement();
        $this->queueElement->params    = $this->contributionStruct;
        $this->queueElement->classLoad = '\AsyncTasks\Workers\SetContributionWorker';

        $this->contextList = ContextList::get( INIT::$TASK_RUNNER_CONFIG[ 'context_definitions' ] );

    }

    public function tearDown() {
        parent::tearDown();
    }


    /**
     * @throws Exception
     */
    public function test_ExecContribution_WillCall_MemoryEngine_With_single_tm_key() {

        /**
         * @var $_worker SetContributionWorker
         */
        $_worker = new $this->queueElement->classLoad( @$this->getMockBuilder( '\AMQHandler' )->getMock() );
        $_worker->attach( $this );

        //create a stub Engine MyMemory
        $stubEngine = @$this->getMockBuilder( '\Engines_MyMemory' )->disableOriginalConstructor()->getMock();

        $stubEngine->expects( $stubEngineParameterSpy = $this->once() )
                ->method( 'update' )
                ->with( $this->anything() )
                ->willReturn(
                        Engines_Results_MyMemory_SetContributionResponse::getInstance(
                                json_decode( '{"responseData":"OK","responseStatus":200,"responseDetails":[545482283]}', true )
                        )
                );

        $_worker->setEngine( $stubEngine );

        /**
         * @var $queueElement Contribution\ContributionSetStruct
         */
        $contributionMockQueueObject = @$this->getMockBuilder( '\Contribution\ContributionSetStruct' )->getMock();
        $contributionMockQueueObject->expects( $this->once() )->method( 'getProp' );
        $contributionMockQueueObject->expects( $this->once() )
                ->method( 'getJobStruct' )
                ->willReturn(
                        new Jobs_JobStruct(
                                [
                                        'id'       => $this->contributionStruct->id_job,
                                        'password' => $this->contributionStruct->job_password,
                                        'source'   => 'en-US',
                                        'target'   => 'it-IT',
                                        'id_tms'   => 1,
                                        'tm_keys'  => '[{"tm":true,"glos":true,"owner":true,"uid_transl":null,"uid_rev":null,"name":"","key":"XXXXXXXXXXXXXXXX","r":true,"w":true,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]'
                                ]
                        )
                );

        $contributionMockQueueObject->fromRevision         = $this->contributionStruct->fromRevision;
        $contributionMockQueueObject->id_job               = $this->contributionStruct->id_job;
        $contributionMockQueueObject->job_password         = $this->contributionStruct->job_password;
        $contributionMockQueueObject->segment              = $this->contributionStruct->segment;
        $contributionMockQueueObject->translation          = $this->contributionStruct->translation;
        $contributionMockQueueObject->api_key              = $this->contributionStruct->api_key;
        $contributionMockQueueObject->uid                  = $this->contributionStruct->uid;
        $contributionMockQueueObject->oldTranslationStatus = $this->contributionStruct->oldTranslationStatus;
        $contributionMockQueueObject->oldSegment           = $this->contributionStruct->oldSegment;
        $contributionMockQueueObject->oldTranslation       = $this->contributionStruct->oldTranslation;


        $reflectedMethod = new ReflectionMethod( $_worker, '_execContribution' );
        $reflectedMethod->setAccessible( true );
        $reflectedMethod->invokeArgs( $_worker, [ $contributionMockQueueObject ] );

        $invocations = $stubEngineParameterSpy->getInvocations();
        $this->assertEquals( $this->contributionStruct->segment, $invocations[ 0 ]->parameters[ 0 ][ 'segment' ] );
        $this->assertEquals( [ 'XXXXXXXXXXXXXXXX' ], $invocations[ 0 ]->parameters[ 0 ][ 'id_user' ] );

    }

    /**
     * @throws Exception
     */
    public function test_ExecContribution_WillCall_MemoryEngine_With_multiple_tm_keys() {

        /**
         * @var $_worker SetContributionWorker
         */
        $_worker = new $this->queueElement->classLoad( @$this->getMockBuilder( '\AMQHandler' )->getMock() );
        $_worker->attach( $this );

        //create a stub Engine MyMemory
        $stubEngine = @$this->getMockBuilder( '\Engines_MyMemory' )->disableOriginalConstructor()->getMock();

        $stubEngine->expects( $stubEngineParameterSpy = $this->once() )
                ->method( 'update' )
                ->with( $this->anything() )
                ->willReturn(
                        Engines_Results_MyMemory_SetContributionResponse::getInstance(
                                json_decode( '{"responseData":"OK","responseStatus":200,"responseDetails":[545518095,545518096]}', true )
                        )
                );

        $_worker->setEngine( $stubEngine );

        /**
         * @var $queueElement Contribution\ContributionSetStruct
         */
        $contributionMockQueueObject = @$this->getMockBuilder( '\Contribution\ContributionSetStruct' )->getMock();
        $contributionMockQueueObject->expects( $this->once() )->method( 'getProp' );
        $contributionMockQueueObject->expects( $this->once() )
                ->method( 'getJobStruct' )
                ->willReturn(
                        new Jobs_JobStruct(
                                [
                                        'id'       => $this->contributionStruct->id_job,
                                        'password' => $this->contributionStruct->job_password,
                                        'source'   => 'en-US',
                                        'target'   => 'it-IT',
                                        'id_tms'   => 1,
                                        'tm_keys'  => '[{"tm":true,"glos":true,"owner":true,"uid_transl":null,"uid_rev":null,"name":"Test","key":"XXXXXXXXXXXXXXXXXXX","r":true,"w":true,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null},{"tm":true,"glos":true,"owner":true,"uid_transl":null,"uid_rev":null,"name":"E tre","key":"YYYYYYYYYYYYYYYYYYYY","r":true,"w":true,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]'
                                ]
                        )
                );

        $contributionMockQueueObject->fromRevision         = $this->contributionStruct->fromRevision;
        $contributionMockQueueObject->id_job               = $this->contributionStruct->id_job;
        $contributionMockQueueObject->job_password         = $this->contributionStruct->job_password;
        $contributionMockQueueObject->segment              = $this->contributionStruct->segment;
        $contributionMockQueueObject->translation          = $this->contributionStruct->translation;
        $contributionMockQueueObject->api_key              = $this->contributionStruct->api_key;
        $contributionMockQueueObject->uid                  = $this->contributionStruct->uid;
        $contributionMockQueueObject->oldTranslationStatus = $this->contributionStruct->oldTranslationStatus;
        $contributionMockQueueObject->oldSegment           = $this->contributionStruct->oldSegment;
        $contributionMockQueueObject->oldTranslation       = $this->contributionStruct->oldTranslation;

        $reflectedMethod = new ReflectionMethod( $_worker, '_execContribution' );
        $reflectedMethod->setAccessible( true );
        $reflectedMethod->invokeArgs( $_worker, [ $contributionMockQueueObject ] );

        $invocations = $stubEngineParameterSpy->getInvocations();
        $this->assertEquals( $this->contributionStruct->segment, $invocations[ 0 ]->parameters[ 0 ][ 'segment' ] );
        $this->assertEquals( [ 'XXXXXXXXXXXXXXXXXXX', 'YYYYYYYYYYYYYYYYYYYY' ], $invocations[ 0 ]->parameters[ 0 ][ 'id_user' ] );

    }

    /**
     * @throws ReflectionException
     */
    public function testWorker_WillCall_Engine_NONE_With_No_TM_Engine_Configured() {

        /**
         * @var $_worker SetContributionWorker
         */
        $_worker = new $this->queueElement->classLoad( @$this->getMockBuilder( '\AMQHandler' )->getMock() );
        $_worker->attach( $this );

        /**
         * @var $queueElement Contribution\ContributionSetStruct
         */
        $contributionMockQueueObject = @$this->getMockBuilder( '\Contribution\ContributionSetStruct' )->getMock();

        $contributionMockQueueObject->expects( $this->once() )->method( 'getProp' );
        $contributionMockQueueObject->expects( $this->once() )
                ->method( 'getJobStruct' )
                ->willReturn(
                        new Jobs_JobStruct(
                                [
                                        'id'       => $this->contributionStruct->id_job,
                                        'password' => $this->contributionStruct->job_password,
                                        'source'   => 'en-US',
                                        'target'   => 'it-IT',
                                        'id_tms'   => 0,
                                        'tm_keys'  => '[]'
                                ]
                        )
                );

        $reflectedMethod = new ReflectionMethod( $_worker, '_execContribution' );
        $reflectedMethod->setAccessible( true );
        $reflectedMethod->invokeArgs( $_worker, [ $contributionMockQueueObject ] );

        $reflectionProperty = new ReflectionProperty( $_worker, '_engine' );
        $reflectionProperty->setAccessible( true );
        $engineLoaded = $reflectionProperty->getValue( $_worker );

        $this->assertInstanceOf( Engines_NONE::class, $engineLoaded );

    }

    /**
     * @throws ReflectionException
     */
    public function testExceptionForMyMemorySetFailure() {

        /**
         * @var $_worker SetContributionWorker
         */
        $_worker = new $this->queueElement->classLoad( @$this->getMockBuilder( '\AMQHandler' )->getMock() );
        $_worker->attach( $this );

        //create a stub Engine MyMemory
        $stubEngine = @$this->getMockBuilder( '\Engines_MyMemory' )->disableOriginalConstructor()->getMock();

        $stubEngine->expects( $this->once() )
                ->method( 'update' )
                ->with( $this->anything() )
                ->willReturn( new Engines_Results_MyMemory_SetContributionResponse( [ 'responseStatus' => 500, 'responseData' => [] ] ) );

        $_worker->setEngine( $stubEngine );

        /**
         * @var $queueElement Contribution\ContributionSetStruct
         */
        $contributionMockQueueObject = @$this->getMockBuilder( '\Contribution\ContributionSetStruct' )->getMock();

        $contributionMockQueueObject->expects( $this->once() )->method( 'getProp' );
        $contributionMockQueueObject->expects( $this->once() )
                ->method( 'getJobStruct' )
                ->willReturn(
                        new Jobs_JobStruct(
                                [
                                        'id'       => $this->contributionStruct->id_job,
                                        'password' => $this->contributionStruct->job_password,
                                        'source'   => 'en-US',
                                        'target'   => 'it-IT',
                                        'id_tms'   => 0,
                                        'tm_keys'  => '[]'
                                ]
                        )
                );

        $reflectedMethod = new ReflectionMethod( $_worker, '_execContribution' );
        $reflectedMethod->setAccessible( true );

        $this->expectException( 'TaskRunner\Exceptions\ReQueueException' );
        $reflectedMethod->invokeArgs( $_worker, [ $contributionMockQueueObject ] );

    }

}
