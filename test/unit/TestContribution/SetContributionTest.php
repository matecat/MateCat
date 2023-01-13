<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/04/16
 * Time: 20.13
 *
 */

use \Contribution\ContributionSetStruct, \Contribution\Set;
use TaskRunner\Commons\ContextList;
use \TaskRunner\Commons\QueueElement;

class SetContributionTest extends \AbstractTest {

    public function setUp(){
        parent::setUp();
        $redisHandler = new \Predis\Client( \INIT::$REDIS_SERVERS );
        $redisHandler->flushdb();
        Database::obtain()->getConnection()->exec( "DELETE FROM jobs WHERE id = 1999999" );
        Database::obtain()->getConnection()->exec( "DELETE FROM projects WHERE id = 22222222" );

        $insertJobQuery = "INSERT INTO `jobs` 
            (`id`, 
             `password`, 
             `id_project`, 
             `job_first_segment`, 
             `job_last_segment`, 
             `id_translator`, 
             `tm_keys`, 
             `job_type`, 
             `source`, 
             `target`, 
             `total_time_to_edit`, 
             `last_opened_segment`, 
             `id_tms`, 
             `id_mt_engine`, 
             `create_date`, 
             `last_update`, 
             `disabled`, 
             `owner`, 
             `status_owner`, 
             `status_translator`, 
             `status`, 
             `completed`, 
             `new_words`, 
             `draft_words`, 
             `translated_words`, 
             `approved_words`, 
             `rejected_words`, 
             `subject`, 
             `payable_rates`, 
             `revision_stats_typing_min`, 
             `revision_stats_translations_min`, 
             `revision_stats_terminology_min`, 
             `revision_stats_language_quality_min`, 
             `revision_stats_style_min`, 
             `revision_stats_typing_maj`, 
             `revision_stats_translations_maj`, 
             `revision_stats_terminology_maj`, 
             `revision_stats_language_quality_maj`, 
             `revision_stats_style_maj`, 
             `avg_post_editing_effort`, 
             `total_raw_wc`) 
        VALUES      
            ('1999999', 
            '1d7903464318', 
            '22222222', 
            '167', 
            '177', 
            'MyMemory_5cc4186a2fe329590980', 
            '[{\\\"tm\\\":true,\\\"glos\\\":true,\\\"owner\\\":true,\\\"uid_transl\\\":null,\\\"uid_rev\\\":null,\\\"name\\\":\\\"en - us_it . tmx\\\",\\\"key\\\":\\\"7f6e65cde5907af8d75a\\\",\\\"r\\\":true,\\\"w\\\":true,\\\"r_transl\\\":null,\\\"w_transl\\\":null,\\\"r_rev\\\":null,\\\"w_rev\\\":null,\\\"source\\\":null,\\\"target\\\":null}]', 
            NULL, 
            'en-US', 
            'it-IT', 
            '6870210', 
            '168', 
            '1', 
            '1', 
            '2016-04-15 20:53:25', 
            '2016-04-20 18:24:47', 
            '0', 
            'domenico@translated.net', 
            'active', 
            NULL, 
            'active', 
            '\0', 
            '94.80', 
            '0.00', 
            '10.50', 
            '0.00', 
            '0.00', 
            'general', 
            '{\\\"NO_MATCH\\\":100,\\\"50 % -74 % \\\":100,\\\"75 % -84 % \\\":60,\\\"85 % -94 % \\\":60,\\\"95 % -99 % \\\":60,\\\"100 % \\\":30,\\\"100 % _PUBLIC\\\":30,\\\"REPETITIONS\\\":30,\\\"INTERNAL\\\":60,\\\"MT\\\":80}', 
            '0', 
            '0', 
            '0', 
            '0', 
            '0', 
            '0', 
            '0', 
            '0', 
            '0', 
            '0', 
            '0', 
            '1') ";

        Database::obtain()->getConnection()->exec($insertJobQuery);
        Database::obtain()->getConnection()->exec( "INSERT INTO `projects` (`id`, `password`, `id_customer`, `name`, `create_date`, `id_engine_tm`, `id_engine_mt`, `status_analysis`, `fast_analysis_wc`, `tm_analysis_wc`, `standard_analysis_wc`, `remote_ip_address`, `pretranslate_100`, `id_qa_model`) VALUES ('22222222', 'b9e73b518ca2', 'domenico@translated.net', 'MATECAT_PROJ-201604150853', '2016-04-15 20:53:18', NULL, NULL, 'DONE', '353.00', '105.30', '105.30', '127.0.0.1', '0', NULL );" );
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
                        CURLOPT_HTTPHEADER     => array( 'Authorization: Basic ' . base64_encode( INIT::$QUEUE_CREDENTIALS ) )
                )
        );
        $curl->multiExec();

    }

    public function tearDown(){
        $redisHandler = new \Predis\Client( \INIT::$REDIS_SERVERS );
        $redisHandler->flushdb();
        Database::obtain()->getConnection()->exec( "DELETE FROM jobs WHERE id = 1999999" );
        Database::obtain()->getConnection()->exec( "DELETE FROM projects WHERE id = 22222222" );
        parent::tearDown();
    }

    public function testSetContributionEnqueue(){

        // These methods does not exist anymore on \CatUtils
        //$contributionStruct->segment              = \CatUtils::layer2ToLayer0( '<g id="pt2">WASHINGTON </g><g id="pt3">— The Treasury Department and Internal Revenue Service today requested
        // public comment on issues relating to the shared responsibility provisions included in the Affordable Care Act that will apply to certain employers starting in 2014.</g>' );
        //$contributionStruct->translation          = \CatUtils::layer2ToLayer0( '<g id="pt2">WASHINGTON </g><g id="pt3">- Il Dipartimento del Tesoro e Agenzia delle Entrate oggi ha chiesto un
        // commento pubblico su questioni relative alle disposizioni di responsabilità condivise incluse nel Affordable Care Act che si applicheranno a certi datori di lavoro a partire dal 2014.</g>' );

        $contributionStruct                       = new ContributionSetStruct();
        $contributionStruct->fromRevision         = true;
        $contributionStruct->id_job               = 1999999;
        $contributionStruct->job_password         = "1d7903464318";
        $contributionStruct->api_key              = \INIT::$MYMEMORY_API_KEY;
        $contributionStruct->uid                  = 1234;
        $contributionStruct->oldTranslationStatus = 'NEW';
        $contributionStruct->oldSegment           = $contributionStruct->segment; //we do not change the segment source
        $contributionStruct->oldTranslation       = $contributionStruct->translation . " TEST";
        $contributionStruct->props                = new TaskRunner\Commons\Params();

        //assert there is not an exception by following the flow
        WorkerClient::init( new AMQHandler() );
        Set::contribution( $contributionStruct );
        $this->assertTrue( true );

        //now check that this value is inside AMQ
        $contextList = ContextList::get( \INIT::$TASK_RUNNER_CONFIG['context_definitions'] );
        $amqh = new \AMQHandler();
        $amqh->subscribe(  $contextList->list['CONTRIBUTION']->queue_name  );
        $message = $amqh->readFrame();
        $amqh->ack( $message );

        /**
         * @var $FrameBody \TaskRunner\Commons\QueueElement
         */
        $FrameBody = new QueueElement( json_decode( $message->body, true ) );
        $this->assertEquals( '\AsyncTasks\Workers\SetContributionWorker', $FrameBody->classLoad );

        $fromAMQ_ContributionStruct = new ContributionSetStruct( $FrameBody->params->toArray() );

        $this->assertEquals( $contributionStruct, $fromAMQ_ContributionStruct );
    }

    public function testSetContributionEnqueueException(){

        // These methods does not exist anymore on \CatUtils
        //$contributionStruct->segment              = \CatUtils::layer2ToLayer0( '<g id="pt2">WASHINGTON </g><g id="pt3">— The Treasury Department and Internal Revenue Service today requested
        // public comment on issues relating to the shared responsibility provisions included in the Affordable Care Act that will apply to certain employers starting in 2014.</g>' );
        //$contributionStruct->translation          = \CatUtils::layer2ToLayer0( '<g id="pt2">WASHINGTON </g><g id="pt3">- Il Dipartimento del Tesoro e Agenzia delle Entrate oggi ha chiesto un
        // commento pubblico su questioni relative alle disposizioni di responsabilità condivise incluse nel Affordable Care Act che si applicheranno a certi datori di lavoro a partire dal 2014.</g>' );

        $contributionStruct                       = new ContributionSetStruct();
        $contributionStruct->fromRevision         = true;
        $contributionStruct->id_job               = 1999999;
        $contributionStruct->job_password         = "1d7903464318";
        $contributionStruct->api_key              = \INIT::$MYMEMORY_API_KEY;
        $contributionStruct->uid                  = 1234;
        $contributionStruct->oldTranslationStatus = 'NEW';
        $contributionStruct->oldSegment           = $contributionStruct->segment; //we do not change the segment source
        $contributionStruct->oldTranslation       = $contributionStruct->translation . " TEST";

        // Create a stub for the \AMQHandler class.
        //we want to test that Set::contribution will call send with these parameters
        $stub = $this->getMockBuilder('\AMQHandler')->getMock();

        $contextList = ContextList::get( \INIT::$TASK_RUNNER_CONFIG['context_definitions'] );
        $queueElement            = new QueueElement();
        $queueElement->params    = $contributionStruct;
        $queueElement->classLoad = '\AsyncTasks\Workers\SetContributionWorker';

        $stub->expects( $this->once() )
                ->method('send')
                ->with(
                        $this->stringContains( $contextList->list['CONTRIBUTION']->queue_name ),
                        $this->equalTo( $queueElement ),
                        $this->equalTo( array( 'persistent' => 'true' ) )
                );

        //simulate \AMQ Server Down and force an exception
        $stub->method('send')->will( $this->throwException( new \Exception( "Could not connect to localhost:61613 (10/10)" ) ) );

        //check that this exception is raised up there
        $this->setExpectedExceptionRegExp( '\Exception', '/Could not connect to .*/' );

        //init the worker client with the stub Handler
        \WorkerClient::init( $stub );
        Set::contribution( $contributionStruct );

    }

}
