<?php

namespace Matecat\Core\Workers;

use Matecat\TestHelpers\AbstractTest;
use Model\ActivityLog\ActivityLogDao;
use Model\ActivityLog\ActivityLogStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use Model\DataAccess\Database;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\ActivityLogWorker;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\Params;
use Utils\TaskRunner\Commons\QueueElement;

#[AllowMockObjectsWithoutExpectations]
class ActivityLogWorkerTest extends AbstractTest
{
    protected function setUp(): void
    {
        parent::setUp();
        AppConfig::$SKIP_SQL_CACHE = true;
        $this->createDatabaseMock();
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    private function createWorker(): ActivityLogWorker
    {
        $amq = $this->createStub(AMQHandler::class);

        return new ActivityLogWorker($amq, Database::obtain());
    }

    #[Test]
    public function writeLogCallsDaoCreate(): void
    {
        $struct = new ActivityLogStruct();
        $struct->id_job = 1;
        $struct->id_project = 10;
        $struct->uid = 5;
        $struct->action = 1;
        $struct->ip = '127.0.0.1';
        $struct->event_date = '2026-01-01 00:00:00';

        $dao = $this->createMock(ActivityLogDao::class);
        $dao->expects($this->once())
            ->method('create')
            ->with($struct)
            ->willReturn(1);

        $worker = $this->createWorker();

        $ref = new \ReflectionMethod($worker, '_writeLog');
        $ref->invoke($worker, $struct, $dao);
    }

    #[Test]
    public function processCallsWriteLog(): void
    {
        $params = new Params();
        $params->id_job = 1;
        $params->id_project = 10;
        $params->uid = 5;
        $params->action = 1;
        $params->ip = '127.0.0.1';
        $params->event_date = '2026-01-01 00:00:00';

        $queueElement = new QueueElement();
        $queueElement->params = $params;
        $queueElement->reQueueNum = 0;

        $worker = $this->getMockBuilder(ActivityLogWorker::class)
            ->setConstructorArgs([$this->createMock(AMQHandler::class), Database::obtain()])
            ->onlyMethods(['_writeLog', '_checkDatabaseConnection'])
            ->getMock();

        $worker->expects($this->once())->method('_writeLog');
        $worker->expects($this->once())->method('_checkDatabaseConnection');

        $worker->process($queueElement);
    }
}
