<?php

use Matecat\SubFiltering\MateCatFilter;
use Model\Engines\Structs\EngineStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Stomp\Transport\Frame;
use TestHelpers\AbstractTest;
use TestHelpers\InvocationInspector;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\SetContributionMTWorker;
use Utils\AsyncTasks\Workers\SetContributionWorker;
use Utils\Constants\EngineConstants;
use Utils\Contribution\SetContributionRequest;
use Utils\Engines\Lara;
use Utils\Engines\MMT;
use Utils\Engines\NONE;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\ContextList;
use Utils\TaskRunner\Commons\Params;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;


/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 07/05/16
 * Time: 23:42
 */
class SetContributionMTWorkerTest extends AbstractTest implements SplObserver {

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
     * @var SetContributionRequest
     */
    protected $contributionStruct;

    /**
     * @var QueueElement
     */
    protected $queueElement;

    /**
     * @var Frame
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
    public function setUp(): void {

        parent::setUp();

        $this->featureSet = new FeatureSet();
        $this->featureSet->loadFromString( "translation_versions,review_extended,mmt" );
        //$featureSet->loadFromString( "project_completion,translation_versions,qa_check_glossary,microsoft" );

        $this->filter = MateCatFilter::getInstance( $this->featureSet, 'en-US', 'it-IT', [] );

        //Reference Queue object
        $this->contributionStruct                       = new SetContributionRequest();
        $this->contributionStruct->fromRevision         = true;
        $this->contributionStruct->id_file              = 1888888;
        $this->contributionStruct->id_job               = 1999999;
        $this->contributionStruct->job_password         = "1d7903464318";
        $this->contributionStruct->id_segment           = 9876;
        $this->contributionStruct->segment              = $this->filter->fromLayer2ToLayer0( '<g id="pt2">WASHINGTON </g><g id="pt3">— The Treasury Department and Internal Revenue Service today requested public comment on issues relating to the shared responsibility provisions included in the Affordable Care Act that will apply to certain employers starting in 2014.</g>' );
        $this->contributionStruct->translation          = $this->filter->fromLayer2ToLayer0( '<g id="pt2">WASHINGTON </g><g id="pt3">- Il Dipartimento del Tesoro e Agenzia delle Entrate oggi ha chiesto un commento pubblico su questioni relative alle disposizioni di responsabilità condivise incluse nel Affordable Care Act che si applicheranno a certi datori di lavoro a partire dal 2014.</g>' );
        $this->contributionStruct->api_key              = 'demo@matecat.com';
        $this->contributionStruct->uid                  = 1234;
        $this->contributionStruct->oldTranslationStatus = 'NEW';
        $this->contributionStruct->oldSegment           = $this->contributionStruct->segment; //we do not change the segment source
        $this->contributionStruct->oldTranslation       = $this->contributionStruct->translation . " TEST";


        $this->queueElement            = new QueueElement();
        $this->queueElement->params    = new Params( $this->contributionStruct->getArrayCopy() );
        $this->queueElement->classLoad = SetContributionMTWorker::class;

        $this->contextList = ContextList::get( AppConfig::$TASK_RUNNER_CONFIG[ 'context_definitions' ] );

    }

    public function tearDown(): void {
        parent::tearDown();
    }


    /**
     * @test
     * @throws Exception
     */
    public function test_ExecContribution_WillCall_MMT_With_single_tm_key() {

        /**
         * @var $_worker SetContributionMTWorker
         */
        $_worker = new $this->queueElement->classLoad( @$this->getMockBuilder( AMQHandler::class )->getMock() );
        $_worker->attach( $this );

        //create a stub EnginesFactory MMT
        $stubEngine = @$this->getMockBuilder( MMT::class )
                ->disableOriginalConstructor()
                ->onlyMethods( [ 'update', 'getEngineRecord' ] )
                ->getMock();

        $engineStruct       = new EngineStruct();
        $engineStruct->id   = 1111;
        $engineStruct->type = EngineConstants::MT;
        $stubEngine->expects( $this->once() )
                ->method( 'getEngineRecord' )
                ->willReturn( $engineStruct );

        $stubEngine->expects( $stubEngineParameterSpy = $this->once() )
                ->method( 'update' )
                ->with( $this->anything() )
                ->willReturn( true );

        $_worker->setEngine( $stubEngine );

        /**
         * @var $queueElement SetContributionRequest
         */
        $contributionMockQueueObject = @$this->getMockBuilder( SetContributionRequest::class )->getMock();
        $contributionMockQueueObject->expects( $this->once() )
                ->method( 'getJobStruct' )
                ->willReturn(
                        new JobStruct(
                                [
                                        'id'           => $this->contributionStruct->id_job,
                                        'password'     => $this->contributionStruct->job_password,
                                        'source'       => 'en-US',
                                        'target'       => 'it-IT',
                                        'id_tms'       => 1,
                                        'id_mt_engine' => 1111,
                                        'tm_keys'      => '[{"tm":true,"glos":true,"owner":true,"uid_transl":null,"uid_rev":null,"name":"","key":"XXXXXXXXXXXXXXXX","r":true,"w":true,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]'
                                ]
                        )
                );

        // check that this is the right mock object, by preparing a result for a successive assertion
        $contributionMockQueueObject->expects( $this->once() )
                ->method( 'getSessionId' )
                ->willReturn( md5( $this->contributionStruct->id_file . '-' . $this->contributionStruct->id_job . '-' . $this->contributionStruct->job_password ) );

        $contributionMockQueueObject->fromRevision         = $this->contributionStruct->fromRevision;
        $contributionMockQueueObject->id_job               = $this->contributionStruct->id_job;
        $contributionMockQueueObject->id_file              = $this->contributionStruct->id_file;
        $contributionMockQueueObject->job_password         = $this->contributionStruct->job_password;
        $contributionMockQueueObject->id_segment           = $this->contributionStruct->id_segment;
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

        $inspector   = new InvocationInspector( $stubEngineParameterSpy );
        $invocations = $inspector->getInvocations();

        if ( !empty( $invocations ) ) {
            $this->assertEquals( $this->contributionStruct->segment, $invocations[ 0 ]->getParameters()[ 0 ][ 'segment' ] );
            $this->assertEquals( [ 'XXXXXXXXXXXXXXXX' ], $invocations[ 0 ]->getParameters()[ 0 ][ 'keys' ] );
            $this->assertEquals( '1999999:9876', $invocations[ 0 ]->getParameters()[ 0 ][ 'tuid' ] );
            $this->assertEquals( 'ed1814ac9699c651fdfca4912b1b6729', $invocations[ 0 ]->getParameters()[ 0 ][ 'session' ] );
        }
    }

    /**
     * @test
     * @throws Exception
     */
    public function test_ExecContribution_WillCall_MMT_With_multiple_tm_keys() {

        /**
         * @var $_worker SetContributionMTWorker
         */
        $_worker = new $this->queueElement->classLoad( @$this->getMockBuilder( AMQHandler::class )->getMock() );
        $_worker->attach( $this );

        //create a stub EnginesFactory Match
        $stubEngine = @$this->getMockBuilder( MMT::class )
                ->onlyMethods( [ 'update', 'getEngineRecord' ] )
                ->disableOriginalConstructor()
                ->getMock();

        $engineStruct       = new EngineStruct();
        $engineStruct->id   = 1111;
        $engineStruct->type = EngineConstants::MT;
        $stubEngine->expects( $this->once() )
                ->method( 'getEngineRecord' )
                ->willReturn( $engineStruct );

        $stubEngine->expects( $stubEngineParameterSpy = $this->once() )
                ->method( 'update' )
                ->with( $this->anything() )
                ->willReturn( true );

        $_worker->setEngine( $stubEngine );

        /**
         * @var $queueElement SetContributionRequest
         */
        $contributionMockQueueObject = @$this->getMockBuilder( SetContributionRequest::class )->getMock();
        $contributionMockQueueObject->expects( $this->once() )
                ->method( 'getJobStruct' )
                ->willReturn(
                        new JobStruct(
                                [
                                        'id'           => $this->contributionStruct->id_job,
                                        'password'     => $this->contributionStruct->job_password,
                                        'source'       => 'en-US',
                                        'target'       => 'it-IT',
                                        'id_tms'       => 1,
                                        'id_mt_engine' => 1111,
                                        'tm_keys'      => '[{"tm":true,"glos":true,"owner":true,"uid_transl":null,"uid_rev":null,"name":"Test","key":"XXXXXXXXXXXXXXXXXXX","r":true,"w":true,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null},{"tm":true,"glos":true,"owner":true,"uid_transl":null,"uid_rev":null,"name":"E tre","key":"YYYYYYYYYYYYYYYYYYYY","r":true,"w":true,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]'
                                ]
                        )
                );

        $contributionMockQueueObject->expects( $this->once() )
                ->method( 'getSessionId' )
                ->willReturn( md5( $this->contributionStruct->id_file . '-' . $this->contributionStruct->id_job . '-' . $this->contributionStruct->job_password ) );

        $contributionMockQueueObject->fromRevision         = $this->contributionStruct->fromRevision;
        $contributionMockQueueObject->id_job               = $this->contributionStruct->id_job;
        $contributionMockQueueObject->id_file              = $this->contributionStruct->id_file;
        $contributionMockQueueObject->job_password         = $this->contributionStruct->job_password;
        $contributionMockQueueObject->id_segment           = $this->contributionStruct->id_segment;
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

        $inspector   = new InvocationInspector( $stubEngineParameterSpy );
        $invocations = $inspector->getInvocations();

        if ( !empty( $invocations ) ) {
            $this->assertEquals( $this->contributionStruct->segment, $invocations[ 0 ]->getParameters()[ 0 ][ 'segment' ] );
            $this->assertEquals( [ 'XXXXXXXXXXXXXXXXXXX', 'YYYYYYYYYYYYYYYYYYYY' ], $invocations[ 0 ]->getParameters()[ 0 ][ 'keys' ] );
            $this->assertEquals( '1999999:9876', $invocations[ 0 ]->getParameters()[ 0 ][ 'tuid' ] );
            $this->assertEquals( 'ed1814ac9699c651fdfca4912b1b6729', $invocations[ 0 ]->getParameters()[ 0 ][ 'session' ] );
        }
    }

    /**
     * @test
     * @throws ReflectionException
     */
    public function testWorker_WillCall_Engine_NONE_With_No_TM_Engine_Configured() {

        /**
         * @var $_worker SetContributionMTWorker
         */
        $_worker = new $this->queueElement->classLoad( @$this->getMockBuilder( AMQHandler::class )->getMock() );
        $_worker->attach( $this );

        /**
         * @var $queueElement SetContributionRequest
         */
        $contributionMockQueueObject = @$this
                ->getMockBuilder( SetContributionRequest::class )
                ->onlyMethods( [ 'getJobStruct' ] )
                ->getMock();

        $contributionMockQueueObject->expects( $this->once() )
                ->method( 'getJobStruct' )
                ->willReturn(
                        new JobStruct(
                                [
                                        'id'           => $this->contributionStruct->id_job,
                                        'password'     => $this->contributionStruct->job_password,
                                        'source'       => 'en-US',
                                        'target'       => 'it-IT',
                                        'id_tms'       => 0,
                                        'id_mt_engine' => 0,
                                        'tm_keys'      => '[]'
                                ]
                        )
                );

        $reflectedMethod = new ReflectionMethod( $_worker, '_execContribution' );
        $reflectedMethod->setAccessible( true );
        $reflectedMethod->invokeArgs( $_worker, [ $contributionMockQueueObject ] );

        $reflectionProperty = new ReflectionProperty( $_worker, '_engine' );
        $reflectionProperty->setAccessible( true );
        $engineLoaded = $reflectionProperty->getValue( $_worker );

        $this->assertInstanceOf( NONE::class, $engineLoaded );

    }

    /**
     * @test
     * @throws ReflectionException
     */
    public function testExceptionForEngineSetFailure_MMT() {

        /**
         * @var $_worker SetContributionMTWorker
         */
        $_worker = new $this->queueElement->classLoad( @$this->getMockBuilder( AMQHandler::class )->getMock() );
        $_worker->attach( $this );

        //create a stub EnginesFactory Match
        $stubEngine = @$this
                ->getMockBuilder( '\Utils\Engines\MMT' )
                ->onlyMethods( [ 'update', 'getEngineRecord' ] )
                ->disableOriginalConstructor()
                ->getMock();

        $engineStruct       = new EngineStruct();
        $engineStruct->id   = 0;
        $engineStruct->type = EngineConstants::MT;
        $stubEngine->expects( $this->once() )
                ->method( 'getEngineRecord' )
                ->willReturn( $engineStruct );

        $stubEngine->expects( $this->once() )
                ->method( 'update' )
                ->with( $this->anything() )
                ->willReturn( false );

        $_worker->setEngine( $stubEngine );

        /**
         * @var $queueElement SetContributionRequest
         */
        $contributionMockQueueObject = @$this->getMockBuilder( SetContributionRequest::class )->getMock();
        $contributionMockQueueObject->expects( $this->once() )
                ->method( 'getJobStruct' )
                ->willReturn(
                        new JobStruct(
                                [
                                        'id'           => $this->contributionStruct->id_job,
                                        'password'     => $this->contributionStruct->job_password,
                                        'source'       => 'en-US',
                                        'target'       => 'it-IT',
                                        'id_tms'       => 0,
                                        'id_mt_engine' => 0,
                                        'tm_keys'      => '[]'
                                ]
                        )
                );

        $reflectedMethod = new ReflectionMethod( $_worker, '_execContribution' );
        $reflectedMethod->setAccessible( true );

        $this->expectException( ReQueueException::class );
        $reflectedMethod->invokeArgs( $_worker, [ $contributionMockQueueObject ] );

    }

    /**
     * @test
     * @throws ReflectionException
     */
    public function testExceptionForEngineSetFailure_Lara() {

        /**
         * @var $_worker SetContributionMTWorker
         */
        $_worker = new $this->queueElement->classLoad( @$this->getMockBuilder( AMQHandler::class )->getMock() );
        $_worker->attach( $this );

        //create a stub EnginesFactory Match
        $stubEngine = @$this
                ->getMockBuilder( Lara::class )
                ->onlyMethods( [ 'update', 'getEngineRecord' ] )
                ->disableOriginalConstructor()
                ->getMock();

        $engineStruct       = new EngineStruct();
        $engineStruct->id   = 0;
        $engineStruct->type = EngineConstants::MT;
        $stubEngine->expects( $this->once() )
                ->method( 'getEngineRecord' )
                ->willReturn( $engineStruct );

        $stubEngine->expects( $this->once() )
                ->method( 'update' )
                ->with( $this->anything() )
                ->willReturn( false ); // set a failure in set on Lara engine

        $_worker->setEngine( $stubEngine );

        /**
         * @var $queueElement SetContributionRequest
         */
        $contributionMockQueueObject = @$this->getMockBuilder( SetContributionRequest::class )->getMock();
        $contributionMockQueueObject->expects( $this->once() )
                ->method( 'getJobStruct' )
                ->willReturn(
                        new JobStruct(
                                [
                                        'id'           => $this->contributionStruct->id_job,
                                        'password'     => $this->contributionStruct->job_password,
                                        'source'       => 'en-US',
                                        'target'       => 'it-IT',
                                        'id_tms'       => 0,
                                        'id_mt_engine' => 0,
                                        'tm_keys'      => '[]'
                                ]
                        )
                );

        $reflectedMethod = new ReflectionMethod( $_worker, '_execContribution' );
        $reflectedMethod->setAccessible( true );

        $this->expectException( ReQueueException::class );
        $reflectedMethod->invokeArgs( $_worker, [ $contributionMockQueueObject ] );

    }

    /**
     * @test
     * @throws ReflectionException
     */
    public function should_load_the_correct_engine() {

        /**
         * @var $_worker SetContributionMTWorker
         */
        $_worker = new $this->queueElement->classLoad( @$this->getMockBuilder( AMQHandler::class )->getMock() );
        $_worker->attach( $this );

        $reflectedMethod = new ReflectionMethod( $_worker, '_loadEngine' );
        $reflectedMethod->setAccessible( true );
        $reflectedMethod->invokeArgs( $_worker, [
                new JobStruct(
                        [
                                'id_tms'       => 1,
                                'id_mt_engine' => 2,
                        ]
                )
        ] );

        $reflectionProperty = new ReflectionProperty( $_worker, '_engine' );
        $reflectionProperty->setAccessible( true );
        $engineLoaded = $reflectionProperty->getValue( $_worker );

        $this->assertInstanceOf( MMT::class, $engineLoaded );

    }

    /**
     * @test
     * @throws ReflectionException
     */
    public function should_throw_exception_with_wrong_engine() {

        /**
         * @var $_worker SetContributionMTWorker
         */
        $_worker = new $this->queueElement->classLoad( @$this->getMockBuilder( AMQHandler::class )->getMock() );
        $_worker->attach( $this );

        $this->expectException( EndQueueException::class );
        $this->expectExceptionMessage( "EnginesFactory 91827364 not found" );
        $this->expectExceptionCode( SetContributionWorker::ERR_NO_TM_ENGINE );

        $reflectedMethod = new ReflectionMethod( $_worker, '_loadEngine' );
        $reflectedMethod->setAccessible( true );
        $reflectedMethod->invokeArgs( $_worker, [
                new JobStruct(
                        [
                                'id_tms'       => 1,
                                'id_mt_engine' => 91827364, // fake
                        ]
                )
        ] );

    }

}
