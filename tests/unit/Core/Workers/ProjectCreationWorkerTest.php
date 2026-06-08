<?php

namespace Matecat\Core\Workers;

use Matecat\TestHelpers\AbstractTest;
use Model\ProjectCreation\ProjectManager;
use PDOException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\ProjectCreationWorker;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\Params;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;

#[AllowMockObjectsWithoutExpectations]
class ProjectCreationWorkerTest extends AbstractTest
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

    private function createWorker(): ProjectCreationWorker
    {
        $amq = $this->createStub(AMQHandler::class);

        return $this->getMockBuilder(ProjectCreationWorker::class)
            ->setConstructorArgs([$amq])
            ->onlyMethods(['_checkDatabaseConnection', '_doLog', 'createProjectManager', 'publishProjectResults'])
            ->getMock();
    }

    private function createQueueElement(int $reQueueNum = 0, ?array $params = null): QueueElement
    {
        $queueElement = new QueueElement();
        $queueElement->reQueueNum = $reQueueNum;

        if ($params !== null) {
            $p = new Params();
            foreach ($params as $k => $v) {
                $p->$k = $v;
            }
            $queueElement->params = $p;
        }

        return $queueElement;
    }

    // ─── process() ───

    #[Test]
    public function processCreatesProjectSuccessfully(): void
    {
        $projectManager = $this->createMock(ProjectManager::class);
        $projectManager->expects($this->once())->method('createProject');

        $worker = $this->createWorker();
        $worker->method('createProjectManager')->willReturn($projectManager);

        $queueElement = $this->createQueueElement(0, ['id_project' => 1, 'project_name' => 'Test']);
        $worker->process($queueElement);
    }

    #[Test]
    public function processThrowsEndQueueOnPDOException(): void
    {
        $projectManager = $this->createMock(ProjectManager::class);
        $projectManager->method('createProject')->willThrowException(new PDOException('DB error'));

        $worker = $this->createWorker();
        $worker->method('createProjectManager')->willReturn($projectManager);

        $this->expectException(EndQueueException::class);

        $queueElement = $this->createQueueElement(0, ['id_project' => 1, 'project_name' => 'Test']);
        $worker->process($queueElement);
    }

    #[Test]
    public function processPublishesResultsEvenOnPDOException(): void
    {
        $projectManager = $this->createMock(ProjectManager::class);
        $projectManager->method('createProject')->willThrowException(new PDOException('DB error'));

        $worker = $this->createWorker();
        $worker->method('createProjectManager')->willReturn($projectManager);
        $worker->expects($this->once())->method('publishProjectResults');

        $queueElement = $this->createQueueElement(0, ['id_project' => 1, 'project_name' => 'Test']);

        try {
            $worker->process($queueElement);
        } catch (EndQueueException) {
        }
    }

    // ─── _checkForReQueueEnd() ───

    #[Test]
    public function checkForReQueueEndThrowsAtLimit(): void
    {
        $worker = $this->createWorker();

        $this->expectException(EndQueueException::class);
        $worker->process($this->createQueueElement(100, ['id_project' => 1]));
    }

    #[Test]
    public function checkForReQueueEndPassesBelowLimit(): void
    {
        $projectManager = $this->createMock(ProjectManager::class);

        $worker = $this->createWorker();
        $worker->method('createProjectManager')->willReturn($projectManager);

        $queueElement = $this->createQueueElement(5, ['id_project' => 1, 'project_name' => 'Test']);
        $worker->process($queueElement);

        $this->assertTrue(true);
    }

    // ─── _publishResults() ───

    #[Test]
    public function publishResultsHandlesUninitializedStructure(): void
    {
        $amq = $this->createStub(AMQHandler::class);

        $worker = $this->getMockBuilder(ProjectCreationWorker::class)
            ->setConstructorArgs([$amq])
            ->onlyMethods(['_checkDatabaseConnection', '_doLog', 'publishProjectResults'])
            ->getMock();

        $worker->expects($this->never())->method('publishProjectResults');

        $ref = new \ReflectionMethod($worker, '_publishResults');
        $ref->invoke($worker);
    }
}
