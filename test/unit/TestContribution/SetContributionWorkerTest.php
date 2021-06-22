<?php

use Contribution\ContributionSetStruct,
        Contribution\Set,
        TaskRunner\Commons\ContextList,
        TaskRunner\Commons\QueueElement;
use Matecat\SubFiltering\MateCatFilter;

/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 07/05/16
 * Time: 23:42
 */
class SetContributionWorkerTest extends AbstractTest implements SplObserver {
    protected $featureSet;
    protected $filter;

    /**
     * @param SplSubject $subject
     */
    public function update( SplSubject $subject ) {
        // Do Nothing, should be used to log
        /**
         * @var $subject \AsyncTasks\Workers\SetContributionWorker
         */
        $this->assertNotEmpty( $subject->getLogMsg() );

    }

    /**
     * @var ContributionSetStruct
     */
    protected $contributionStruct;

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

    public function setUp() {

        parent::setUp();

        $this->featureSet = new FeatureSet();
        $this->featureSet->loadFromString( "translation_versions,review_extended,mmt,airbnb" );
        //$featureSet->loadFromString( "project_completion,translation_versions,qa_check_glossary,microsoft" );

        $this->filter = MateCatFilter::getInstance( $this->featureSet, 'en-US', 'it-IT', [] );


        //purge ActiveMQ
        $curl = new MultiCurlHandler();
        $curl->createResource(
                INIT::$QUEUE_JMX_ADDRESS . "/api/jolokia/exec/org.apache.activemq:type=Broker,brokerName=localhost,destinationType=Queue,destinationName=set_contribution/purge",
                [
                        CURLOPT_HEADER         => false,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_USERAGENT      => INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER,
                        CURLOPT_CONNECTTIMEOUT => 5, // a timeout to call itself should not be too much higher :D
                        CURLOPT_SSL_VERIFYPEER => true,
                        CURLOPT_SSL_VERIFYHOST => 2,
                        CURLOPT_HTTPHEADER     => array( 'Authorization: Basic ' . base64_encode( INIT::$QUEUE_CREDENTIALS ) )
                ]
        );
        $curl->multiExec();

        //Queue submission
        $this->contributionStruct = new ContributionSetStruct();

        $this->contributionStruct->fromRevision         = true;
        $this->contributionStruct->id_job               = 1999999;
        $this->contributionStruct->job_password         = "1d7903464318";
        $this->contributionStruct->segment              = $this->filter->fromLayer2ToLayer0( '<g id="pt2">WASHINGTON </g><g id="pt3">— The Treasury Department and Internal Revenue Service today requested public comment on issues relating to the shared responsibility provisions included in the Affordable Care Act that will apply to certain employers starting in 2014.</g>' );
        $this->contributionStruct->translation          = $this->filter->fromLayer2ToLayer0( '<g id="pt2">WASHINGTON </g><g id="pt3">- Il Dipartimento del Tesoro e Agenzia delle Entrate oggi ha chiesto un commento pubblico su questioni relative alle disposizioni di responsabilità condivise incluse nel Affordable Care Act che si applicheranno a certi datori di lavoro a partire dal 2014.</g>' );
        $this->contributionStruct->api_key              = \INIT::$MYMEMORY_API_KEY;
        $this->contributionStruct->uid                  = 1234;
        $this->contributionStruct->oldTranslationStatus = 'NEW';
        $this->contributionStruct->oldSegment           = $this->contributionStruct->segment; //we do not change the segment source
        $this->contributionStruct->oldTranslation       = $this->contributionStruct->translation . " TEST";

        //enqueue the crafted object
        WorkerClient::init( new AMQHandler() );
        Set::contribution( $this->contributionStruct );

        //now check that this value is inside AMQ
        $this->contextList = ContextList::get( \INIT::$TASK_RUNNER_CONFIG[ 'context_definitions' ] );
        $this->amq         = new \AMQHandler();
        $this->amq->subscribe( $this->contextList->list[ 'CONTRIBUTION' ]->queue_name );
        $this->message = $this->amq->readFrame();
        $this->amq->ack( $this->message );

    }

    public function tearDown() {
        $this->amq->disconnect();
        $this->amq = null;
        parent::tearDown();
    }


    /**
     * @throws Exception
     */
    public function testExecContributionWithoutKeysWorker() {

        /**
         * @var $queueElement \TaskRunner\Commons\QueueElement
         */
        $queueElement = new QueueElement( json_decode( $this->message->body, true ) );
        $this->assertEquals( '\AsyncTasks\Workers\SetContributionWorker', $queueElement->classLoad );

        /**
         * @var $_worker \AsyncTasks\Workers\SetContributionWorker
         */
        $_worker = new $queueElement->classLoad( $this->amq );

        $_worker->attach( $this );
        $_worker->setPid( posix_getpid() );
        $_worker->setContext( $this->contextList->list[ 'CONTRIBUTION' ] );

        //create a stub Engine MyMemory
        $stubEngine = $this->getMockBuilder( '\Engines_MyMemory' )->disableOriginalConstructor()->getMock();

        $stubEngine->method( 'getConfigStruct' )->willReturn( Engine::getInstance( 1 )->getConfigStruct() );

        $_config                  = Engine::getInstance( 1 )->getConfigStruct();
        $_config[ 'segment' ]     = $this->contributionStruct->segment;
        $_config[ 'translation' ] = $this->contributionStruct->translation;
        $_config[ 'tnote' ]       = null;
        $_config[ 'source' ]      = 'en-US';
        $_config[ 'target' ]      = 'it-IT';
        $_config[ 'email' ]       = $this->contributionStruct->api_key;
        $_config[ 'prop' ]        = json_encode( [
                'project_id'   => 6666,
                'project_name' => "Fake Project",
                'job_id'       => 1999999
        ] );

        $stubEngine->expects( $this->once() )
                ->method( 'set' )
                ->with( $_config )
                ->willReturn(
                        Engines_Results_MyMemory_SetContributionResponse::getInstance(
                                json_decode( '{"responseData":"OK","responseStatus":200,"responseDetails":[545482283]}', true )
                        )
                );

        $_worker->setEngine( $stubEngine );

        /**
         * @var $queueElement Contribution\ContributionSetStruct
         */
        $mockParams = $this->getMockBuilder( '\Contribution\ContributionSetStruct' )->getMock();

        $mockParams->expects( $this->once() )
                ->method( 'getJobStruct' )
                ->willReturn( [
                        new \Jobs_JobStruct(
                                [
                                        'id'       => $this->contributionStruct->id_job,
                                        'password' => $this->contributionStruct->job_password,
                                        'source'   => 'en-US',
                                        'target'   => 'it-IT',
                                        'id_tms'   => 1,
                                        'tm_keys'  => "{}"
                                ]
                        )
                ] );

        $mockParams->expects( $this->once() )
                ->method( 'getProp' )->willReturn(
                        [
                                'project_id'   => 6666,
                                'project_name' => "Fake Project",
                                'job_id'       => 1999999
                        ]
                );

        $queueElement->params = $mockParams;

        $queueElement->params->fromRevision         = $this->contributionStruct->fromRevision;
        $queueElement->params->id_job               = $this->contributionStruct->id_job;
        $queueElement->params->job_password         = $this->contributionStruct->job_password;
        $queueElement->params->segment              = $this->contributionStruct->segment;
        $queueElement->params->translation          = $this->contributionStruct->translation;
        $queueElement->params->api_key              = $this->contributionStruct->api_key;
        $queueElement->params->uid                  = $this->contributionStruct->uid;
        $queueElement->params->oldTranslationStatus = $this->contributionStruct->oldTranslationStatus;
        $queueElement->params->oldSegment           = $this->contributionStruct->oldSegment;
        $queueElement->params->oldTranslation       = $this->contributionStruct->oldTranslation;


        $reflectedMethod = new ReflectionMethod( $_worker, '_execContribution' );
        $reflectedMethod->setAccessible( true );
        $reflectedMethod->invokeArgs( $_worker, [ $mockParams ] );

    }

    /**
     * @throws Exception
     */
    public function testExecContributionWithKeysWorker() {

        /**
         * @var $queueElement \TaskRunner\Commons\QueueElement
         */
        $queueElement = new QueueElement( json_decode( $this->message->body, true ) );
        $this->assertEquals( '\AsyncTasks\Workers\SetContributionWorker', $queueElement->classLoad );

        /**
         * @var $_worker \AsyncTasks\Workers\SetContributionWorker
         */
        $_worker = new $queueElement->classLoad( $this->amq );

        $_worker->attach( $this );
        $_worker->setPid( posix_getpid() );
        $_worker->setContext( $this->contextList->list[ 'CONTRIBUTION' ] );

        //create a stub Engine MyMemory
        $stubEngine = $this->getMockBuilder( '\Engines_MyMemory' )->disableOriginalConstructor()->getMock();

        $stubEngine->method( 'getConfigStruct' )->willReturn( Engine::getInstance( 1 )->getConfigStruct() );

        $_config                  = Engine::getInstance( 1 )->getConfigStruct();
        $_config[ 'segment' ]     = $this->contributionStruct->segment;
        $_config[ 'translation' ] = $this->contributionStruct->translation;
        $_config[ 'tnote' ]       = null;
        $_config[ 'source' ]      = 'en-US';
        $_config[ 'target' ]      = 'it-IT';
        $_config[ 'email' ]       = $this->contributionStruct->api_key;
        $_config[ 'id_user' ]     = [
                '3c6a7d60684f4e697cfa',
                '77297ac61e056d8645d0',
        ];
        $_config[ 'prop' ]        = json_encode( [
                'project_id'   => 6666,
                'project_name' => "Fake Project",
                'job_id'       => 1999999
        ] );

        $stubEngine->expects( $this->once() )
                ->method( 'set' )
                ->with( $_config )
                ->willReturn(
                        Engines_Results_MyMemory_SetContributionResponse::getInstance(
                                json_decode( '{"responseData":"OK","responseStatus":200,"responseDetails":[545518095,545518096]}', true )
                        )
                );

        $_worker->setEngine( $stubEngine );

        /**
         * @var $queueElement Contribution\ContributionSetStruct
         */
        $mockParams = $this->getMockBuilder( '\Contribution\ContributionSetStruct' )->getMock();

        $mockParams->expects( $this->once() )
                ->method( 'getJobStruct' )
                ->willReturn( [
                        new \Jobs_JobStruct(
                                [
                                        'id'       => $this->contributionStruct->id_job,
                                        'password' => $this->contributionStruct->job_password,
                                        'source'   => 'en-US',
                                        'target'   => 'it-IT',
                                        'id_tms'   => 1,
                                        'tm_keys'  => '[{"tm":true,"glos":true,"owner":true,"uid_transl":null,"uid_rev":null,"name":"Test","key":"3c6a7d60684f4e697cfa","r":true,"w":true,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null},{"tm":true,"glos":true,"owner":true,"uid_transl":null,"uid_rev":null,"name":"E tre","key":"77297ac61e056d8645d0","r":true,"w":true,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]'
                                ]
                        )
                ] );

        $mockParams->expects( $this->once() )
                ->method( 'getProp' )->willReturn(
                        [
                                'project_id'   => 6666,
                                'project_name' => "Fake Project",
                                'job_id'       => 1999999
                        ]
                );

        $queueElement->params = $mockParams;

        $queueElement->params->fromRevision         = $this->contributionStruct->fromRevision;
        $queueElement->params->id_job               = $this->contributionStruct->id_job;
        $queueElement->params->job_password         = $this->contributionStruct->job_password;
        $queueElement->params->segment              = $this->contributionStruct->segment;
        $queueElement->params->translation          = $this->contributionStruct->translation;
        $queueElement->params->api_key              = $this->contributionStruct->api_key;
        $queueElement->params->uid                  = $this->contributionStruct->uid;
        $queueElement->params->oldTranslationStatus = $this->contributionStruct->oldTranslationStatus;
        $queueElement->params->oldSegment           = $this->contributionStruct->oldSegment;
        $queueElement->params->oldTranslation       = $this->contributionStruct->oldTranslation;


        $reflectedMethod = new ReflectionMethod( $_worker, '_execContribution' );
        $reflectedMethod->setAccessible( true );
        $reflectedMethod->invokeArgs( $_worker, [ $mockParams ] );

    }

    public function testWorkerExceptionForNoTMConfiguredInJob() {

        /**
         * @var $queueElement \TaskRunner\Commons\QueueElement
         */
        $queueElement = new QueueElement( json_decode( $this->message->body, true ) );
        $this->assertEquals( '\AsyncTasks\Workers\SetContributionWorker', $queueElement->classLoad );

        /**
         * @var $_worker \AsyncTasks\Workers\SetContributionWorker
         */
        $_worker = new $queueElement->classLoad( $this->amq );

        $_worker->attach( $this );
        $_worker->setPid( posix_getpid() );
        $_worker->setContext( $this->contextList->list[ 'CONTRIBUTION' ] );

        /**
         * @var $queueElement Contribution\ContributionSetStruct
         */
        $mockParams = $this->getMockBuilder( '\Contribution\ContributionSetStruct' )->getMock();

        $mockParams->expects( $this->once() )
                ->method( 'getJobStruct' )
                ->willReturn( [
                        new \Jobs_JobStruct(
                                [
                                        'id'       => $this->contributionStruct->id_job,
                                        'password' => $this->contributionStruct->job_password,
                                        'source'   => 'en-US',
                                        'target'   => 'it-IT',
                                        'id_tms'   => 0,
                                        'tm_keys'  => '[]'
                                ]
                        )
                ] );

        //expect no call con getProp
        $mockParams->expects( $this->exactly( 0 ) )->method( 'getProp' );

        $queueElement->params = $mockParams;

        $queueElement->params->fromRevision         = $this->contributionStruct->fromRevision;
        $queueElement->params->id_job               = $this->contributionStruct->id_job;
        $queueElement->params->job_password         = $this->contributionStruct->job_password;
        $queueElement->params->segment              = $this->contributionStruct->segment;
        $queueElement->params->translation          = $this->contributionStruct->translation;
        $queueElement->params->api_key              = $this->contributionStruct->api_key;
        $queueElement->params->uid                  = $this->contributionStruct->uid;
        $queueElement->params->oldTranslationStatus = $this->contributionStruct->oldTranslationStatus;
        $queueElement->params->oldSegment           = $this->contributionStruct->oldSegment;
        $queueElement->params->oldTranslation       = $this->contributionStruct->oldTranslation;

        $reflectedMethod = new ReflectionMethod( $_worker, '_execContribution' );
        $reflectedMethod->setAccessible( true );

        $this->setExpectedException( 'TaskRunner\Exceptions\EndQueueException', "No TM engine configured for the job. Skip, OK" );
        $reflectedMethod->invokeArgs( $_worker, [ $mockParams ] );

    }

    public function testExceptionForMyMemorySetFailure() {

        /**
         * @var $queueElement \TaskRunner\Commons\QueueElement
         */
        $queueElement = new QueueElement( json_decode( $this->message->body, true ) );
        $this->assertEquals( '\AsyncTasks\Workers\SetContributionWorker', $queueElement->classLoad );

        /**
         * @var $_worker \AsyncTasks\Workers\SetContributionWorker
         */
        $_worker = new $queueElement->classLoad( $this->amq );

        $_worker->attach( $this );
        $_worker->setPid( posix_getpid() );
        $_worker->setContext( $this->contextList->list[ 'CONTRIBUTION' ] );

        //create a stub Engine MyMemory
        $stubEngine = $this->getMockBuilder( '\Engines_MyMemory' )->disableOriginalConstructor()->getMock();

        $stubEngine->method( 'getConfigStruct' )->willReturn( Engine::getInstance( 1 )->getConfigStruct() );

        $_config                  = Engine::getInstance( 1 )->getConfigStruct();
        $_config[ 'segment' ]     = $this->contributionStruct->segment;
        $_config[ 'translation' ] = $this->contributionStruct->translation;
        $_config[ 'tnote' ]       = null;
        $_config[ 'source' ]      = 'en-US';
        $_config[ 'target' ]      = 'it-IT';
        $_config[ 'email' ]       = $this->contributionStruct->api_key;
        $_config[ 'id_user' ]     = [
                '3c6a7d60684f4e697cfa',
                '77297ac61e056d8645d0',
        ];

        $_config[ 'prop' ] = json_encode( [
                'project_id'   => 6666,
                'project_name' => "Fake Project",
                'job_id'       => 1999999
        ] );

        $stubEngine->expects( $this->once() )
                ->method( 'set' )
                ->with( $_config )
                ->willReturn( false );

        $_worker->setEngine( $stubEngine );

        /**
         * @var $queueElement Contribution\ContributionSetStruct
         */
        $mockParams = $this->getMockBuilder( '\Contribution\ContributionSetStruct' )->getMock();

        $mockParams->expects( $this->once() )
                ->method( 'getJobStruct' )
                ->willReturn( [
                        new \Jobs_JobStruct(
                                [
                                        'id'       => $this->contributionStruct->id_job,
                                        'password' => $this->contributionStruct->job_password,
                                        'source'   => 'en-US',
                                        'target'   => 'it-IT',
                                        'id_tms'   => 1,
                                        'tm_keys'  => '[{"tm":true,"glos":true,"owner":true,"uid_transl":null,"uid_rev":null,"name":"Test","key":"3c6a7d60684f4e697cfa","r":true,"w":true,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null},{"tm":true,"glos":true,"owner":true,"uid_transl":null,"uid_rev":null,"name":"E tre","key":"77297ac61e056d8645d0","r":true,"w":true,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]'
                                ]
                        )
                ] );

        $mockParams->expects( $this->once() )
                ->method( 'getProp' )->willReturn(
                        [
                                'project_id'   => 6666,
                                'project_name' => "Fake Project",
                                'job_id'       => 1999999
                        ]
                );

        $queueElement->params = $mockParams;

        $queueElement->params->fromRevision         = $this->contributionStruct->fromRevision;
        $queueElement->params->id_job               = $this->contributionStruct->id_job;
        $queueElement->params->job_password         = $this->contributionStruct->job_password;
        $queueElement->params->segment              = $this->contributionStruct->segment;
        $queueElement->params->translation          = $this->contributionStruct->translation;
        $queueElement->params->api_key              = $this->contributionStruct->api_key;
        $queueElement->params->uid                  = $this->contributionStruct->uid;
        $queueElement->params->oldTranslationStatus = $this->contributionStruct->oldTranslationStatus;
        $queueElement->params->oldSegment           = $this->contributionStruct->oldSegment;
        $queueElement->params->oldTranslation       = $this->contributionStruct->oldTranslation;


        $reflectedMethod = new ReflectionMethod( $_worker, '_execContribution' );
        $reflectedMethod->setAccessible( true );

        $this->setExpectedException( 'TaskRunner\Exceptions\ReQueueException' );
        $reflectedMethod->invokeArgs( $_worker, [ $mockParams ] );

    }

}
