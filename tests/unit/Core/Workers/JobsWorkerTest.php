<?php

namespace Matecat\Core\Workers;

use Matecat\TestHelpers\AbstractTest;
use Model\EditLog\EditLogSegmentStruct;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use Model\DataAccess\Database;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\JobsWorker;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\Params;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;

#[AllowMockObjectsWithoutExpectations]
class JobsWorkerTest extends AbstractTest
{
    /** Collects every record the worker writes through the logger injected by createWorker(). */
    private TestHandler $logHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logHandler = new TestHandler();
        AppConfig::$SKIP_SQL_CACHE = true;
        $this->createDatabaseMock();
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    private function createWorker(?JobDao $jobDao = null): JobsWorker
    {
        $amq = $this->createStub(AMQHandler::class);

        $worker = $this->getMockBuilder(JobsWorker::class)
            ->setConstructorArgs([$amq, obtainTestDatabase(), $jobDao, new MatecatLogger(new Logger('test', [$this->logHandler]))])
            ->onlyMethods(['_checkDatabaseConnection'])
            ->getMock();

        return $worker;
    }

    private function createQueueElement(int $reQueueNum = 0): QueueElement
    {
        $params = new Params();
        $params->id = 1;
        $params->password = 'abc';
        $params->source = 'en-US';
        $params->target = 'it-IT';

        $queueElement = new QueueElement();
        $queueElement->params = $params;
        $queueElement->reQueueNum = $reQueueNum;

        return $queueElement;
    }

    // ─── process() ───

    #[Test]
    public function processCallsRecountAvgPee(): void
    {
        $jobDao = $this->createMock(JobDao::class);
        $jobDao->method('getAllModifiedSegmentsForPee')->willReturn([]);
        $jobDao->expects($this->once())->method('updateJobWeightedPeeAndTTE');

        $worker = $this->createWorker($jobDao);
        $worker->process($this->createQueueElement());
    }

    #[Test]
    public function processLogsTheSkipWhenTheChunkChangedSinceTheFrameWasQueued(): void
    {
        $jobDao = $this->createMock(JobDao::class);
        $jobDao->method('getAllModifiedSegmentsForPee')->willReturn([]);
        $jobDao->method('updateJobWeightedPeeAndTTE')->willReturn(0);

        $worker = $this->createWorker($jobDao);

        $worker->process($this->createQueueElement());

        $records = $this->logHandler->getRecords();
        self::assertCount(2, $records);
        self::assertStringContainsString('no row changed: the chunk was merged or split again', $records[1]->message);
    }

    #[Test]
    public function processDoesNotLogASkipWhenTheChunkWasWritten(): void
    {
        $jobDao = $this->createMock(JobDao::class);
        $jobDao->method('getAllModifiedSegmentsForPee')->willReturn([]);
        $jobDao->method('updateJobWeightedPeeAndTTE')->willReturn(1);

        $worker = $this->createWorker($jobDao);

        $worker->process($this->createQueueElement());

        self::assertCount(1, $this->logHandler->getRecords());
    }

    #[Test]
    public function processThrowsEndQueueOnMaxRequeue(): void
    {
        $jobDao = $this->createMock(JobDao::class);
        $worker = $this->createWorker($jobDao);

        $this->expectException(EndQueueException::class);
        $worker->process($this->createQueueElement(100));
    }

    // ─── _recountAvgPee() ───

    #[Test]
    public function recountAvgPeeComputesWeightedPee(): void
    {
        $segment1 = new EditLogSegmentStruct();
        $segment1->suggestion = 'Hello world';
        $segment1->translation = 'Ciao mondo';
        $segment1->raw_word_count = 10;
        $segment1->time_to_edit = 5000;

        $segment2 = new EditLogSegmentStruct();
        $segment2->suggestion = 'Good morning';
        $segment2->translation = 'Good morning';
        $segment2->raw_word_count = 5;
        $segment2->time_to_edit = 3000;

        $jobDao = $this->createMock(JobDao::class);
        $jobDao->method('getAllModifiedSegmentsForPee')->willReturn([$segment1, $segment2]);
        $jobDao->expects($this->once())
            ->method('updateJobWeightedPeeAndTTE')
            ->with($this->callback(function (JobStruct $job) {
                return $job->total_time_to_edit === 8000
                    && $job->avg_post_editing_effort > 0;
            }));

        $worker = $this->createWorker($jobDao);

        $jobStruct = new JobStruct();
        $jobStruct->id = 1;
        $jobStruct->password = 'abc';
        $jobStruct->target = 'it-IT';

        $ref = new \ReflectionMethod($worker, '_recountAvgPee');
        $ref->invoke($worker, $jobStruct);
    }

    #[Test]
    public function recountAvgPeeHandlesEmptySegments(): void
    {
        $jobDao = $this->createMock(JobDao::class);
        $jobDao->method('getAllModifiedSegmentsForPee')->willReturn([]);
        $jobDao->expects($this->once())
            ->method('updateJobWeightedPeeAndTTE')
            ->with($this->callback(function (JobStruct $job) {
                return $job->avg_post_editing_effort === 0.0
                    && $job->total_time_to_edit === 0;
            }));

        $worker = $this->createWorker($jobDao);

        $jobStruct = new JobStruct();
        $jobStruct->id = 1;
        $jobStruct->password = 'abc';
        $jobStruct->target = 'en-US';

        $ref = new \ReflectionMethod($worker, '_recountAvgPee');
        $ref->invoke($worker, $jobStruct);
    }
}
