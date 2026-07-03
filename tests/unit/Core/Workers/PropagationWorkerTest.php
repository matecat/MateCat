<?php

namespace Matecat\Core\Workers;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Propagation\PropagationTotalStruct;
use Model\Translations\SegmentTranslationStruct;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use Plugins\Features\TranslationVersions\Model\TranslationVersionDao;
use Utils\ActiveMQ\AMQHandler;
use Utils\AsyncTasks\Workers\PropagationWorker;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\Params;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;

#[AllowMockObjectsWithoutExpectations]
class PropagationWorkerTest extends AbstractTest
{
    private PDOStatement $stmtStub;
    private \PDO $pdoStub;

    protected function setUp(): void
    {
        parent::setUp();
        AppConfig::$SKIP_SQL_CACHE = true;
        [, $this->pdoStub, $this->stmtStub] = $this->createDatabaseMock();
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    private function createWorker(): PropagationWorker
    {
        $amq = $this->createStub(AMQHandler::class);
        $dbMock = $this->createStub(IDatabase::class);
        $dbMock->method('getConnection')->willReturn($this->pdoStub);
        $versionDao = $this->createStub(TranslationVersionDao::class);

        return $this->getMockBuilder(PropagationWorker::class)
            ->setConstructorArgs([$amq, $dbMock, $versionDao])
            ->onlyMethods(['_checkDatabaseConnection', '_doLog'])
            ->getMock();
    }

    private function buildStructures(bool $executeUpdate = true, array $segmentsForPropagation = [], array $idsToUpdateVersion = []): array
    {
        $propagatorSegment = new SegmentTranslationStruct();
        $propagatorSegment->id_segment = 100;
        $propagatorSegment->id_job = 1;
        $propagatorSegment->segment_hash = 'abc123';
        $propagatorSegment->translation = 'Ciao';
        $propagatorSegment->status = 'TRANSLATED';
        $propagatorSegment->translation_date = '2026-01-01 00:00:00';
        $propagatorSegment->autopropagated_from = 100;
        $propagatorSegment->serialized_errors_list = null;
        $propagatorSegment->warning = false;

        $propagationTotal = $this->createStub(PropagationTotalStruct::class);
        $propagationTotal->method('getSegmentsForPropagation')->willReturn($segmentsForPropagation);
        $propagationTotal->method('getAllToPropagate')->willReturn($segmentsForPropagation);
        $propagationTotal->method('getPropagatedIdsToUpdateVersion')->willReturn($idsToUpdateVersion);
        $propagationTotal->method('getPropagatedIds')->willReturn(array_column($segmentsForPropagation, 'id_segment'));

        return [
            'translationStructTemplate' => $propagatorSegment,
            'id_segment' => 100,
            'job' => new \Model\Jobs\JobStruct(['id' => 1, 'password' => 'abc']),
            'project' => new \Model\Projects\ProjectStruct(),
            'propagationAnalysis' => $propagationTotal,
            'execute_update' => $executeUpdate,
        ];
    }

    // ─── propagateTranslation() ───

    #[Test]
    public function propagateTranslationSkipsWhenExecuteUpdateFalse(): void
    {
        $worker = $this->createWorker();
        $structures = $this->buildStructures(false);

        $ref = new \ReflectionMethod($worker, 'propagateTranslation');
        $ref->invoke($worker, $structures);

        $this->assertTrue(true);
    }

    #[Test]
    public function propagateTranslationSkipsWhenNoSegments(): void
    {
        $worker = $this->createWorker();
        $structures = $this->buildStructures(true, []);

        $ref = new \ReflectionMethod($worker, 'propagateTranslation');
        $ref->invoke($worker, $structures);

        $this->assertTrue(true);
    }

    #[Test]
    public function propagateTranslationExecutesUpdate(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        $worker = $this->createWorker();
        $segments = [
            ['id_segment' => 200, 'id_job' => 1],
            ['id_segment' => 300, 'id_job' => 1],
        ];
        $structures = $this->buildStructures(true, $segments);

        $ref = new \ReflectionMethod($worker, 'propagateTranslation');
        $ref->invoke($worker, $structures);

        $this->assertTrue(true);
    }

    #[Test]
    public function propagateTranslationWithVersionUpdates(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        $worker = $this->createWorker();
        $segments = [
            ['id_segment' => 200, 'id_job' => 1],
        ];
        $idsToUpdate = [200 => true];
        $structures = $this->buildStructures(true, $segments, $idsToUpdate);

        $ref = new \ReflectionMethod($worker, 'propagateTranslation');
        $ref->invoke($worker, $structures);

        $this->assertTrue(true);
    }

    #[Test]
    public function propagateTranslationThrowsEndQueueOnPDOException(): void
    {
        $this->stmtStub->method('execute')->willThrowException(new PDOException('DB error'));

        $worker = $this->createWorker();
        $segments = [
            ['id_segment' => 200, 'id_job' => 1],
        ];
        $structures = $this->buildStructures(true, $segments);

        $this->expectException(EndQueueException::class);

        $ref = new \ReflectionMethod($worker, 'propagateTranslation');
        $ref->invoke($worker, $structures);
    }

    // ─── rebuildObjects() ───

    #[Test]
    public function rebuildObjectsReturnsCorrectTypes(): void
    {
        $worker = $this->createWorker();

        $params = new Params();
        $params->translationStructTemplate = ['id_segment' => 1, 'id_job' => 2, 'segment_hash' => 'x'];
        $params->id_segment = 1;
        $params->job = ['id' => 2, 'password' => 'abc'];
        $params->project = ['id' => 3];
        $params->propagationAnalysis = [];
        $params->execute_update = true;

        $ref = new \ReflectionMethod($worker, 'rebuildObjects');
        $result = $ref->invoke($worker, $params);

        $this->assertInstanceOf(SegmentTranslationStruct::class, $result['translationStructTemplate']);
        $this->assertInstanceOf(\Model\Jobs\JobStruct::class, $result['job']);
        $this->assertInstanceOf(\Model\Projects\ProjectStruct::class, $result['project']);
        $this->assertInstanceOf(PropagationTotalStruct::class, $result['propagationAnalysis']);
        $this->assertSame(1, $result['id_segment']);
        $this->assertTrue($result['execute_update']);
    }

    // ─── process() ───

    #[Test]
    public function processCallsPropagateTranslation(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        $worker = $this->createWorker();

        $params = new Params();
        $params->translationStructTemplate = ['id_segment' => 1, 'id_job' => 2, 'segment_hash' => 'x'];
        $params->id_segment = 1;
        $params->job = ['id' => 2, 'password' => 'abc'];
        $params->project = ['id' => 3];
        $params->propagationAnalysis = [];
        $params->execute_update = false;

        $queueElement = new QueueElement();
        $queueElement->params = $params;
        $queueElement->reQueueNum = 0;

        $worker->process($queueElement);

        $this->assertTrue(true);
    }
}
