<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/04/16
 * Time: 20.13
 *
 */

use Contribution\ContributionSetStruct;
use Contribution\Set;
use TaskRunner\Commons\ContextList;
use TaskRunner\Commons\QueueElement;
use TestHelpers\AbstractTest;

class SetContributionTest extends AbstractTest {

    public function setUp() {
        parent::setUp();

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
            false, 
            '94.80', 
            '0.00', 
            '10.50', 
            '0.00', 
            '0.00', 
            'general', 
            '{\\\"NO_MATCH\\\":100,\\\"50 % -74 % \\\":100,\\\"75 % -84 % \\\":60,\\\"85 % -94 % \\\":60,\\\"95 % -99 % \\\":60,\\\"100 % \\\":30,\\\"100 % _PUBLIC\\\":30,\\\"REPETITIONS\\\":30,\\\"INTERNAL\\\":60,\\\"MT\\\":80}', 
            '1') ";

        Database::obtain()->getConnection()->exec( $insertJobQuery );
        Database::obtain()->getConnection()->exec( "INSERT INTO `projects` (`id`, `password`, `id_customer`, `name`, `create_date`, `id_engine_tm`, `id_engine_mt`, `status_analysis`, `fast_analysis_wc`, `tm_analysis_wc`, `standard_analysis_wc`, `remote_ip_address`, `pretranslate_100`, `id_qa_model`) VALUES ('22222222', 'b9e73b518ca2', 'domenico@translated.net', 'MATECAT_PROJ-201604150853', '2016-04-15 20:53:18', NULL, NULL, 'DONE', '353.00', '105.30', '105.30', '127.0.0.1', '0', NULL );" );

    }

    public function tearDown() {
        $redisHandler = ( new RedisHandler() )->getConnection();
        $redisHandler->flushdb();
        Database::obtain()->getConnection()->exec( "DELETE FROM jobs WHERE id = 1999999" );
        Database::obtain()->getConnection()->exec( "DELETE FROM projects WHERE id = 22222222" );
        parent::tearDown();
    }

    /**
     * @throws Exception
     */
    public function testSetContributionEnqueue() {

        $contributionStruct                       = new ContributionSetStruct();
        $contributionStruct->fromRevision         = true;
        $contributionStruct->id_job               = 1999999;
        $contributionStruct->job_password         = "1d7903464318";
        $contributionStruct->api_key              = INIT::$MYMEMORY_API_KEY;
        $contributionStruct->uid                  = 1234;
        $contributionStruct->oldTranslationStatus = 'NEW';
        $contributionStruct->oldSegment           = $contributionStruct->segment; //we do not change the segment source
        $contributionStruct->oldTranslation       = $contributionStruct->translation . " TEST";
        $contributionStruct->props                = new TaskRunner\Commons\Params();

        $queueElement            = new QueueElement();
        $queueElement->params    = $contributionStruct;
        $queueElement->classLoad = '\AsyncTasks\Workers\SetContributionWorker';

        $contextList = ContextList::get( INIT::$TASK_RUNNER_CONFIG['context_definitions'] );

        $amqHandlerMock = @$this->getMockBuilder( '\AMQHandler' )->getMock();

        $amqHandlerMock->expects( $spy = $this->exactly( 1 ) )
                ->method( 'publishToQueues' )
                ->with(
                        $this->equalTo( WorkerClient::$_QUEUES[ 'CONTRIBUTION' ]->queue_name ),
                        $this->equalTo( new Stomp\Transport\Message( strval( $queueElement ), [ 'persistent' => WorkerClient::$_HANDLER->persistent ] ) )
                );

        // inject the mock
        WorkerClient::init( $amqHandlerMock );

        //assert there is not an exception by following the flow
        Set::contribution( $contributionStruct );
        $this->assertTrue( true );

        $invocations = $spy->getInvocations();

        $this->assertContains( '\\\\AsyncTasks\\\\Workers\\\\SetContributionWorker', $invocations[ 0 ]->parameters[ 1 ]->body );

    }

    public function testSetContributionEnqueueException() {

        $contributionStruct                       = new ContributionSetStruct();
        $contributionStruct->fromRevision         = true;
        $contributionStruct->id_job               = 1999999;
        $contributionStruct->job_password         = "1d7903464318";
        $contributionStruct->api_key              = INIT::$MYMEMORY_API_KEY;
        $contributionStruct->uid                  = 1234;
        $contributionStruct->oldTranslationStatus = 'NEW';
        $contributionStruct->oldSegment           = $contributionStruct->segment; //we do not change the segment source
        $contributionStruct->oldTranslation       = $contributionStruct->translation . " TEST";

        // Create a stub for the \AMQHandler class.
        //we want to test that Set::contribution will call send with these parameters
        $stub = @$this->getMockBuilder( '\AMQHandler' )->getMock();

        $queueElement            = new QueueElement();
        $queueElement->params    = $contributionStruct;
        $queueElement->classLoad = '\AsyncTasks\Workers\SetContributionWorker';

        $stub->expects( $this->once() )
                ->method( 'publishToQueues' )
                ->with(
                        $this->equalTo( WorkerClient::$_QUEUES[ 'CONTRIBUTION' ]->queue_name ),
                        $this->equalTo( new Stomp\Transport\Message( strval( $queueElement ), [ 'persistent' => WorkerClient::$_HANDLER->persistent ] ) )
                );

        //simulate \AMQ Server Down and force an exception
        $stub->method( 'publishToQueues' )->willThrowException(
                new Exception( "Could not connect to localhost:61613 (10/10)" )
        );

        // Check that this exception is raised up.
        //
        // Without this row ( expectException )
        // PHPUnit will not check for its content,
        // but instead it will raise the exception
        $this->expectException( Exception::class );
        $this->expectExceptionMessageRegExp( '/Could not connect to .*/' );

        //init the worker client with the stub Handler
        WorkerClient::init( $stub );
        Set::contribution( $contributionStruct );

    }

}
