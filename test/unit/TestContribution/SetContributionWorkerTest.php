<?php

use Contribution\ContributionStruct,
        Contribution\Set,
        TaskRunner\Commons\ContextList,
        TaskRunner\Commons\QueueElement;

/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 07/05/16
 * Time: 23:42
 */
class SetContributionWorkerTest extends AbstractTest implements SplObserver {

    /**
     * SplObserver emulation
     * @param SplSubject $subject
     */
    public function update( SplSubject $subject ) {
        // Do Nothing, should log
    }

    /**
     * @var ContributionStruct
     */
    protected $contributionStruct;

    public function setUp(){

        parent::setUp();

        //purge ActiveMQ
        $curl = new MultiCurlHandler();
        $curl->createResource(
                INIT::$QUEUE_JMX_ADDRESS . "/api/jolokia/exec/org.apache.activemq:type=Broker,brokerName=localhost,destinationType=Queue,destinationName=set_contribution/purge",
                array(
                        CURLOPT_HEADER         => false,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_USERAGENT      => INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER,
                        CURLOPT_CONNECTTIMEOUT => 5, // a timeout to call itself should not be too much higher :D
                        CURLOPT_SSL_VERIFYPEER => true,
                        CURLOPT_SSL_VERIFYHOST => 2,
                        CURLOPT_HTTPHEADER     => array( 'Authorization: Basic ' . base64_encode( "admin:admin" ) )
                )
        );
        $curl->multiExec();

    }

    /**
     * @throws Exception
     */
    public function testExecContributionWorker(){

        //Queue submission
        $this->contributionStruct = new ContributionStruct();

        $this->contributionStruct->fromRevision         = true;
        $this->contributionStruct->id_job               = 1999999;
        $this->contributionStruct->job_password         = "1d7903464318";
        $this->contributionStruct->segment              = \CatUtils::view2rawxliff( '<g id="pt2">WASHINGTON </g><g id="pt3">— The Treasury Department and Internal Revenue Service today requested public comment on issues relating to the shared responsibility provisions included in the Affordable Care Act that will apply to certain employers starting in 2014.</g>' );
        $this->contributionStruct->translation          = \CatUtils::view2rawxliff( '<g id="pt2">WASHINGTON </g><g id="pt3">- Il Dipartimento del Tesoro e Agenzia delle Entrate oggi ha chiesto un commento pubblico su questioni relative alle disposizioni di responsabilità condivise incluse nel Affordable Care Act che si applicheranno a certi datori di lavoro a partire dal 2014.</g>' );
        $this->contributionStruct->api_key              = \INIT::$MYMEMORY_API_KEY;
        $this->contributionStruct->uid                  = 1234;
        $this->contributionStruct->oldTranslationStatus = 'NEW';
        $this->contributionStruct->oldSegment           = $this->contributionStruct->segment; //we do not change the segment source
        $this->contributionStruct->oldTranslation       = $this->contributionStruct->translation . " TEST";

        //enqueue the crafted object
        WorkerClient::init( new AMQHandler() );
        Set::contribution( $this->contributionStruct );

        //now check that this value is inside AMQ
        $contextList = ContextList::get( \INIT::$TASK_RUNNER_CONFIG[ 'context_definitions' ] );
        $amqh        = new \AMQHandler();
        $amqh->subscribe( $contextList->list[ 'CONTRIBUTION' ]->queue_name );
        $message = $amqh->readFrame();
        $amqh->ack( $message );

        var_dump( $message );

        /**
         * @var $queueElement \TaskRunner\Commons\QueueElement
         */
        $queueElement = new QueueElement( json_decode( $message->body, true ) );
        $this->assertEquals( '\AsyncTasks\Workers\SetContributionWorker', $queueElement->classLoad );

        /**
         * @var $_worker \AsyncTasks\Workers\SetContributionWorker
         */
        $_worker = new $queueElement->classLoad( $amqh );

        $_worker->attach( $this ) ;
        $_worker->setPid( posix_getpid() );
        $_worker->setContext( $contextList->list[ 'CONTRIBUTION' ] );

        //create a stub Engine MyMemory
        $stubEngine               = $this->getMockBuilder( '\Engines_MyMemory' )->disableOriginalConstructor()->getMock();

        $stubEngine->method( 'getConfigStruct' )->willReturn( Engine::getInstance( 1 )->getConfigStruct() );

        $stubEngine->expects( $this->once() )
                ->method( 'set' )->willReturn(
                        Engines_Results_MyMemory_SetContributionResponse::getInstance(
                                json_decode( '{"responseData":"OK","responseStatus":200,"responseDetails":[545482283]}', true )
                        )
                );

        $_worker->setEngine( $stubEngine );

        /**
         * @var $queueElement Contribution\ContributionStruct
         */
        $mockParams = $this->getMockBuilder( '\Contribution\ContributionStruct' )->getMock();

        $mockParams->expects( $this->once() )
                ->method( 'getJobStruct' )
                ->willReturn( array(
                        new \Jobs_JobStruct(
                                array(
                                        'id'       => $this->contributionStruct->id_job,
                                        'password' => $this->contributionStruct->job_password,
                                        'source'   => 'en-US',
                                        'target'   => 'it-IT',
                                        'id_tms'   => 1,
                                        'tm_keys'  => "{}"
                                )
                        )
                ) );

        $mockParams->expects( $this->once() )
                ->method( 'getProp' )->willReturn(
                        array(
                                'project_id'   => 6666,
                                'project_name' => "Fake Project",
                                'job_id'       => 1999999
                        )
                );

        $queueElement->params = $mockParams;

        $queueElement->params->fromRevision          = $this->contributionStruct->fromRevision;
        $queueElement->params->id_job                = $this->contributionStruct->id_job;
        $queueElement->params->job_password          = $this->contributionStruct->job_password;
        $queueElement->params->segment               = $this->contributionStruct->segment;
        $queueElement->params->translation           = $this->contributionStruct->translation;
        $queueElement->params->api_key               = $this->contributionStruct->api_key;
        $queueElement->params->uid                   = $this->contributionStruct->uid;
        $queueElement->params->oldTranslationStatus  = $this->contributionStruct->oldTranslationStatus;
        $queueElement->params->oldSegment            = $this->contributionStruct->oldSegment;
        $queueElement->params->oldTranslation        = $this->contributionStruct->oldTranslation;


        $reflectedMethod = new ReflectionMethod( $_worker, '_execContribution' );
        $reflectedMethod->setAccessible( true );
        $reflectedMethod->invokeArgs( $_worker, array( $mockParams ) );

    }

}
