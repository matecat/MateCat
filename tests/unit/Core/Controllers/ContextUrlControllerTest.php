<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

use Controller\API\App\ContextUrlController;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Users\UserStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

class TestableContextUrlController extends ContextUrlController
{
    public function __construct()
    {
    }

    protected function afterConstruct(): void
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class ContextUrlControllerTest extends AbstractTest
{
    private \PHPUnit\Framework\MockObject\Stub&IDatabase $dbStub;
    private \PHPUnit\Framework\MockObject\Stub&PDO $pdoStub;
    private \PHPUnit\Framework\MockObject\Stub&PDOStatement $stmtStub;

    private ReflectionClass $reflector;
    private TestableContextUrlController $controller;
    private Response&MockObject $responseMock;

    protected function setUp(): void
    {
        parent::setUp();

        AppConfig::$SKIP_SQL_CACHE = true;

        [$this->dbStub, $this->pdoStub, $this->stmtStub] = $this->createDatabaseMock();

        $this->controller = new TestableContextUrlController();
        $this->reflector  = new ReflectionClass(ContextUrlController::class);

        $this->responseMock = $this->createMock(Response::class);

        $resProp = $this->reflector->getProperty('response');
        $resProp->setValue($this->controller, $this->responseMock);

        $user = new UserStruct();
        $user->uid        = 1;
        $user->email      = 'test@example.org';
        $user->first_name = 'Test';
        $user->last_name  = 'User';

        $userProp = $this->reflector->getProperty('user');
        $userProp->setValue($this->controller, $user);

        $logProp = $this->reflector->getProperty('logger');
        $logProp->setValue($this->controller, $this->createMock(MatecatLogger::class));
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    private function setRequestParams(array $params): void
    {
        $request = new Request($params, [], [], ['REQUEST_URI' => '/api/v3/context-url', 'REQUEST_METHOD' => 'POST']);
        $reqProp = $this->reflector->getProperty('request');
        $reqProp->setValue($this->controller, $request);
    }


    // ── setForProject ─────────────────────────────────────────────────────

    #[Test]
    public function setForProject_throws_when_id_project_missing(): void
    {
        $this->setRequestParams(['context_url' => 'https://example.com']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);

        $this->controller->setForProject();
    }

    #[Test]
    public function setForProject_throws_when_context_url_empty(): void
    {
        $this->setRequestParams(['id_project' => '10', 'context_url' => '']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);

        $this->controller->setForProject();
    }

    #[Test]
    public function setForProject_returns_project_level_json(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('fetch')->willReturn(false);

        $this->setRequestParams(['id_project' => '42', 'context_url' => 'https://example.com/proj']);

        $this->responseMock->expects(self::once())
            ->method('json')
            ->with(self::callback(function (array $data): bool {
                return $data['level'] === 'project'
                    && $data['id_project'] === 42
                    && $data['context_url'] === 'https://example.com/proj';
            }));

        $this->controller->setForProject();
    }


    // ── setForFile ────────────────────────────────────────────────────────

    #[Test]
    public function setForFile_throws_when_id_file_missing(): void
    {
        $this->setRequestParams(['id_project' => '10', 'context_url' => 'https://example.com']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);

        $this->controller->setForFile();
    }

    #[Test]
    public function setForFile_throws_when_context_url_empty(): void
    {
        $this->setRequestParams(['id_project' => '10', 'id_file' => '5', 'context_url' => '']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);

        $this->controller->setForFile();
    }

    #[Test]
    public function setForFile_inserts_when_no_existing_record(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('fetch')->willReturn(false);
        $this->pdoStub->method('lastInsertId')->willReturn('99');

        $this->setRequestParams([
            'id_project'  => '10',
            'id_file'     => '5',
            'context_url' => 'https://example.com/file',
        ]);

        $this->responseMock->expects(self::once())
            ->method('json')
            ->with(self::callback(function (array $data): bool {
                return $data['level'] === 'file'
                    && $data['id_project'] === 10
                    && $data['id_file'] === 5
                    && $data['context_url'] === 'https://example.com/file';
            }));

        $this->controller->setForFile();
    }

    #[Test]
    public function setForFile_updates_when_existing_record_found(): void
    {
        $existingStruct = new \Model\Files\MetadataStruct();
        $existingStruct->id_project = 10;
        $existingStruct->id_file    = 5;
        $existingStruct->key        = 'context-url';
        $existingStruct->value      = 'https://old.com';

        $callCount = 0;
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturnCallback(function () use (&$callCount, $existingStruct) {
            $callCount++;
            return $callCount === 1 ? [$existingStruct] : [];
        });
        $this->stmtStub->method('rowCount')->willReturn(1);

        $this->setRequestParams([
            'id_project'  => '10',
            'id_file'     => '5',
            'context_url' => 'https://example.com/file-updated',
        ]);

        $this->responseMock->expects(self::once())
            ->method('json')
            ->with(self::callback(function (array $data): bool {
                return $data['level'] === 'file' && $data['id_file'] === 5;
            }));

        $this->controller->setForFile();
    }


    // ── setForSegment ─────────────────────────────────────────────────────

    #[Test]
    public function setForSegment_throws_when_id_segment_missing(): void
    {
        $this->setRequestParams(['context_url' => 'https://example.com']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);

        $this->controller->setForSegment();
    }

    #[Test]
    public function setForSegment_throws_when_context_url_empty(): void
    {
        $this->setRequestParams(['id_segment' => '100', 'context_url' => '']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);

        $this->controller->setForSegment();
    }

    #[Test]
    public function setForSegment_returns_segment_level_json(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $this->setRequestParams(['id_segment' => '100', 'context_url' => 'https://example.com/seg']);

        $this->responseMock->expects(self::once())
            ->method('json')
            ->with(self::callback(function (array $data): bool {
                return $data['level'] === 'segment'
                    && $data['id_segment'] === 100
                    && $data['context_url'] === 'https://example.com/seg';
            }));

        $this->controller->setForSegment();
    }
}
