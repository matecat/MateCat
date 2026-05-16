<?php

namespace unit\Controllers;

use Controller\API\V2\SegmentTranslationIssueController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Validators\SegmentTranslationIssueValidator;
use Klein\Request;
use Klein\Response;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\Jobs\JobStruct;
use Model\LQA\EntryStruct;
use Model\Translations\SegmentTranslationStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Plugins\Features\ReviewExtended\TranslationIssueModel;
use ReflectionClass;
use RuntimeException;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;

class TestableSegmentTranslationIssueController extends SegmentTranslationIssueController
{
    /** @var TranslationIssueModel&MockObject|null */
    public ?TranslationIssueModel $mockModel = null;

    public function __construct()
    {
    }

    protected function afterConstruct(): void
    {
    }

    protected function _getSegmentTranslationIssueModel(int $id_job, string $password, EntryStruct $issue): TranslationIssueModel
    {
        if ($this->mockModel !== null) {
            return $this->mockModel;
        }

        return parent::_getSegmentTranslationIssueModel($id_job, $password, $issue);
    }
}

#[AllowMockObjectsWithoutExpectations]
class SegmentTranslationIssueControllerTest extends AbstractTest
{
    private const int USER_UID = 990001;
    private const int OWNER_UID = 990002;
    private const int TEAM_MEMBER_UID = 990003;
    private const int STRANGER_UID = 990004;

    private const string OWNER_EMAIL = 'owner-990002@test.com';
    private const string TEAM_NAME = 'Test Team 990001';

    private const int JOB_ID = 990010;
    private const string JOB_PASSWORD = 'pw990010';
    private const string REVIEW_PASSWORD = 'rp990010';
    private const int PROJECT_ID = 990020;
    private const string PROJECT_PASSWORD = 'pp990020';

    private ReflectionClass $reflector;
    private TestableSegmentTranslationIssueController $controller;
    /** @var Request&MockObject */
    private Request&MockObject $requestStub;
    /** @var Response&MockObject */
    private Response&MockObject $responseMock;

    protected function setUp(): void
    {
        parent::setUp();

        Database::obtain()->begin();

        $this->reflector = new ReflectionClass(TestableSegmentTranslationIssueController::class);
        $this->controller = new TestableSegmentTranslationIssueController();

        $this->requestStub = $this->createMock(Request::class);
        $this->responseMock = $this->createMock(Response::class);
        $this->responseMock->method('json')->willReturnSelf();
        $this->responseMock->method('code')->willReturnSelf();

        $loggerMock = $this->createMock(MatecatLogger::class);

        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);
        $this->reflector->getProperty('logger')->setValue($this->controller, $loggerMock);

        $this->seedFixtures();
        $this->setControllerUser(self::USER_UID);
    }

    protected function tearDown(): void
    {
        $conn = Database::obtain()->getConnection();
        if ($conn->inTransaction()) {
            Database::obtain()->rollback();
        }

        parent::tearDown();
    }

    private function seedFixtures(): void
    {
        $conn = Database::obtain()->getConnection();

        // Users
        $conn->exec("DELETE FROM users WHERE uid IN (" . self::USER_UID . ", " . self::OWNER_UID . ", " . self::TEAM_MEMBER_UID . ", " . self::STRANGER_UID . ")");
        $conn->exec("INSERT INTO users (uid, email, first_name, last_name) VALUES (" . self::USER_UID . ", 'user-990001@test.com', 'Test', 'User')");
        $conn->exec("INSERT INTO users (uid, email, first_name, last_name) VALUES (" . self::OWNER_UID . ", '" . self::OWNER_EMAIL . "', 'Owner', 'User')");
        $conn->exec("INSERT INTO users (uid, email, first_name, last_name) VALUES (" . self::TEAM_MEMBER_UID . ", 'member-990003@test.com', 'Member', 'User')");
        $conn->exec("INSERT INTO users (uid, email, first_name, last_name) VALUES (" . self::STRANGER_UID . ", 'stranger-990004@test.com', 'Stranger', 'User')");

        // Team
        $conn->exec("DELETE FROM teams WHERE id = 990001");
        $conn->exec("INSERT INTO teams (id, name, created_by) VALUES (990001, '" . self::TEAM_NAME . "', " . self::OWNER_UID . ")");

        // Memberships — owner and team member
        $conn->exec("DELETE FROM teams_users WHERE id_team = 990001");
        $conn->exec("INSERT INTO teams_users (uid, id_team, is_admin) VALUES (" . self::OWNER_UID . ", 990001, 1)");
        $conn->exec("INSERT INTO teams_users (uid, id_team, is_admin) VALUES (" . self::TEAM_MEMBER_UID . ", 990001, 0)");

        // Project
        $conn->exec("DELETE FROM projects WHERE id = " . self::PROJECT_ID);
        $conn->exec(
            "INSERT INTO projects (id, password, id_customer, name, create_date, status_analysis, id_team)
             VALUES (" . self::PROJECT_ID . ", '" . self::PROJECT_PASSWORD . "', '" . self::OWNER_EMAIL . "', 'Test Project', NOW(), 'DONE', 990001)"
        );

        // Job
        $conn->exec("DELETE FROM jobs WHERE id = " . self::JOB_ID);
        $conn->exec(
            "INSERT INTO jobs (id, password, id_project, source, target, owner)
             VALUES (" . self::JOB_ID . ", '" . self::JOB_PASSWORD . "', " . self::PROJECT_ID . ", 'en-US', 'it-IT', '" . self::OWNER_EMAIL . "')"
        );

        // Chunk review
        $conn->exec("DELETE FROM qa_chunk_reviews WHERE id_job = " . self::JOB_ID);
        $conn->exec(
            "INSERT INTO qa_chunk_reviews (id_job, password, review_password, source_page)
             VALUES (" . self::JOB_ID . ", '" . self::JOB_PASSWORD . "', '" . self::REVIEW_PASSWORD . "', 1)"
        );

        // LQA entry (issue)
        $conn->exec("DELETE FROM qa_entries WHERE id = 990030");
        $conn->exec(
            "INSERT INTO qa_entries (id, id_segment, id_job, id_category, severity, translation_version, start_node, start_offset, end_node, end_offset, is_full_segment, penalty_points, comment, target_text, uid, source_page, rebutted_at)
             VALUES (990030, 1, " . self::JOB_ID . ", 1, 'Minor', 1, 0, 0, 0, 5, 0, 1, 'test comment', 'target text', " . self::USER_UID . ", 1, NULL)"
        );
    }

    private function setControllerUser(int $uid): void
    {
        $user = new UserStruct();
        $user->uid = $uid;
        $user->email = "user-$uid@test.com";

        $reflector = new ReflectionClass($this->controller);
        while (!$reflector->hasProperty('user') && $reflector->getParentClass() !== false) {
            $reflector = $reflector->getParentClass();
        }

        $reflector->getProperty('user')->setValue($this->controller, $user);
        $reflector->getProperty('userIsLogged')->setValue($this->controller, true);
    }

    private function setValidator(?EntryStruct $issue = null, ?SegmentTranslationStruct $translation = null): void
    {
        $validator = $this->createMock(SegmentTranslationIssueValidator::class);

        if ($issue !== null) {
            $validator->issue = $issue;
        }

        if ($translation === null) {
            $translation = new SegmentTranslationStruct();
            $translation->id_segment = 1;
            $translation->id_job = self::JOB_ID;
            $translation->version_number = 1;
        }
        $validator->translation = $translation;

        $parentReflector = new ReflectionClass(SegmentTranslationIssueController::class);
        $parentReflector->getProperty('validator')->setValue($this->controller, $validator);
    }

    private function setRequestParams(array $params): void
    {
        $this->requestStub->method('param')->willReturnCallback(
            static fn(string $key) => $params[$key] ?? null
        );
    }

    // ─── index() ─────────────────────────────────────────────────

    #[Test]
    public function indexReturnsEmptyArrayWhenNoIssuesExist(): void
    {
        $this->setValidator();
        $this->setRequestParams(['version_number' => '1']);

        $this->responseMock
            ->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $payload): bool {
                $this->assertArrayHasKey('issues', $payload);
                $this->assertIsArray($payload['issues']);

                return true;
            }));

        $this->controller->index();
    }

    #[Test]
    public function indexUsesVersionNumberFromRequest(): void
    {
        $this->setValidator();
        $this->setRequestParams(['version_number' => '99']);

        $this->responseMock
            ->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $payload): bool {
                $this->assertArrayHasKey('issues', $payload);

                return true;
            }));

        $this->controller->index();
    }

    #[Test]
    public function indexFallsBackToTranslationVersionNumber(): void
    {
        $this->setValidator();
        $this->setRequestParams([]);

        $this->responseMock
            ->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $payload): bool {
                $this->assertArrayHasKey('issues', $payload);

                return true;
            }));

        $this->controller->index();
    }

    // ─── delete() ────────────────────────────────────────────────

    #[Test]
    public function deleteThrowsRuntimeExceptionWhenValidatorIssueIsNull(): void
    {
        $this->setRequestParams([
            'id_job' => self::JOB_ID,
            'password' => self::REVIEW_PASSWORD,
        ]);

        $validatorMock = $this->createMock(SegmentTranslationIssueValidator::class);
        $validatorMock->issue = null;
        $translation = new SegmentTranslationStruct();
        $translation->id_segment = 1;
        $translation->id_job = self::JOB_ID;
        $translation->version_number = 1;
        $validatorMock->translation = $translation;

        $parentReflector = new ReflectionClass(SegmentTranslationIssueController::class);
        $parentReflector->getProperty('validator')->setValue($this->controller, $validatorMock);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing issue');

        $this->controller->delete();
    }

    #[Test]
    public function deleteThrowsNotFoundWhenChunkReviewNotFound(): void
    {
        $issue = new EntryStruct();
        $issue->id = 990030;
        $issue->uid = self::USER_UID;
        $issue->id_segment = 1;
        $issue->id_job = self::JOB_ID;
        $issue->source_page = 1;
        $this->setValidator($issue);

        $this->setRequestParams([
            'id_job' => self::JOB_ID,
            'password' => 'nonexistent_password',
        ]);

        $modelMock = $this->createMock(TranslationIssueModel::class);
        $this->controller->mockModel = $modelMock;

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Job not found');

        $this->controller->delete();
    }

    #[Test]
    public function deleteSucceedsWhenUserIsIssueOwner(): void
    {
        $issue = new EntryStruct();
        $issue->id = 990030;
        $issue->uid = self::USER_UID;
        $issue->id_segment = 1;
        $issue->id_job = self::JOB_ID;
        $issue->source_page = 1;
        $this->setValidator($issue);

        $this->setRequestParams([
            'id_job' => self::JOB_ID,
            'password' => self::REVIEW_PASSWORD,
        ]);

        $modelMock = $this->createMock(TranslationIssueModel::class);
        $modelMock->expects($this->once())->method('delete');
        $this->controller->mockModel = $modelMock;

        $this->responseMock
            ->expects($this->once())
            ->method('code')
            ->with(200);

        $this->controller->delete();
    }

    // ─── update() ────────────────────────────────────────────────

    #[Test]
    public function updateThrowsNotFoundWhenIssueDoesNotExist(): void
    {
        $this->setValidator();
        $this->setRequestParams([
            'id_issue' => 999999999,
            'id_segment' => 1,
            'id_job' => self::JOB_ID,
            'password' => self::REVIEW_PASSWORD,
        ]);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Issue not found');

        $this->controller->update();
    }

    #[Test]
    public function updateThrowsNotFoundWhenChunkReviewNotFound(): void
    {
        $this->setValidator();
        $this->setRequestParams([
            'id_issue' => 990030,
            'id_segment' => 1,
            'id_job' => self::JOB_ID,
            'password' => 'bad_password',
        ]);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Job not found');

        $this->controller->update();
    }

    // ─── checkLoggedUserPermissions() ────────────────────────────

    #[Test]
    public function checkLoggedUserPermissionsPassesWhenUserIsEntryOwner(): void
    {
        $this->setControllerUser(self::USER_UID);
        $issue = new EntryStruct();
        $issue->id = 990030;
        $issue->uid = self::USER_UID;
        $issue->id_segment = 1;
        $issue->id_job = self::JOB_ID;
        $issue->source_page = 1;
        $this->setValidator($issue);

        $this->setRequestParams([
            'id_job' => self::JOB_ID,
            'password' => self::REVIEW_PASSWORD,
        ]);

        $modelMock = $this->createMock(TranslationIssueModel::class);
        $modelMock->method('delete');
        $this->controller->mockModel = $modelMock;

        $this->responseMock->expects($this->once())->method('code')->with(200);

        $this->controller->delete();
    }

    #[Test]
    public function checkLoggedUserPermissionsPassesWhenUserIsJobOwner(): void
    {
        $this->setControllerUser(self::OWNER_UID);
        $issue = new EntryStruct();
        $issue->id = 990030;
        $issue->uid = self::USER_UID;
        $issue->id_segment = 1;
        $issue->id_job = self::JOB_ID;
        $issue->source_page = 1;
        $this->setValidator($issue);

        $this->setRequestParams([
            'id_job' => self::JOB_ID,
            'password' => self::REVIEW_PASSWORD,
        ]);

        $modelMock = $this->createMock(TranslationIssueModel::class);
        $modelMock->method('delete');
        $this->controller->mockModel = $modelMock;

        $this->responseMock->expects($this->once())->method('code')->with(200);

        $this->controller->delete();
    }

    #[Test]
    public function checkLoggedUserPermissionsPassesWhenUserIsTeamMember(): void
    {
        $this->setControllerUser(self::TEAM_MEMBER_UID);
        $issue = new EntryStruct();
        $issue->id = 990030;
        $issue->uid = self::USER_UID;
        $issue->id_segment = 1;
        $issue->id_job = self::JOB_ID;
        $issue->source_page = 1;
        $this->setValidator($issue);

        $this->setRequestParams([
            'id_job' => self::JOB_ID,
            'password' => self::REVIEW_PASSWORD,
        ]);

        $modelMock = $this->createMock(TranslationIssueModel::class);
        $modelMock->method('delete');
        $this->controller->mockModel = $modelMock;

        $this->responseMock->expects($this->once())->method('code')->with(200);

        $this->controller->delete();
    }

    #[Test]
    public function checkLoggedUserPermissionsThrowsWhenUserIsStranger(): void
    {
        $this->setControllerUser(self::STRANGER_UID);
        $issue = new EntryStruct();
        $issue->id = 990030;
        $issue->uid = self::USER_UID;
        $issue->id_segment = 1;
        $issue->id_job = self::JOB_ID;
        $issue->source_page = 1;
        $this->setValidator($issue);

        $this->setRequestParams([
            'id_job' => self::JOB_ID,
            'password' => self::REVIEW_PASSWORD,
        ]);

        $modelMock = $this->createMock(TranslationIssueModel::class);
        $this->controller->mockModel = $modelMock;

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionMessage('Not Authorized');

        $this->controller->delete();
    }

    // ─── getComments() ───────────────────────────────────────────

    #[Test]
    public function getCommentsThrowsRuntimeExceptionWhenIssueIdIsNull(): void
    {
        $issue = new EntryStruct();
        $issue->id = null;
        $this->setValidator($issue);
        $this->setRequestParams([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing issue id');

        $this->controller->getComments();
    }

    #[Test]
    public function getCommentsReturnsEmptyArrayWhenNoCommentsExist(): void
    {
        $issue = new EntryStruct();
        $issue->id = 990030;
        $this->setValidator($issue);
        $this->setRequestParams([]);

        $this->responseMock
            ->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $payload): bool {
                $this->assertArrayHasKey('comments', $payload);
                $this->assertIsArray($payload['comments']);

                return true;
            }));

        $this->controller->getComments();
    }

    // ─── createComment() ─────────────────────────────────────────

    #[Test]
    public function createCommentThrowsRuntimeExceptionWhenIssueIdIsNull(): void
    {
        $issue = new EntryStruct();
        $issue->id = null;
        $this->setValidator($issue);
        $this->setRequestParams(['message' => 'hello', 'source_page' => '1']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing issue id');

        $this->controller->createComment();
    }

    #[Test]
    public function createCommentThrowsNotFoundWhenEntryDoesNotExist(): void
    {
        $issue = new EntryStruct();
        $issue->id = 999999999;
        $this->setValidator($issue);
        $this->setRequestParams(['message' => 'hello', 'source_page' => '1']);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Issue not found');

        $this->controller->createComment();
    }

    #[Test]
    public function createCommentSucceedsWithValidData(): void
    {
        $issue = new EntryStruct();
        $issue->id = 990030;
        $this->setValidator($issue);
        $this->setRequestParams(['message' => 'Test comment body', 'source_page' => '1']);

        $this->responseMock
            ->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $payload): bool {
                $this->assertArrayHasKey('comment', $payload);
                $this->assertIsArray($payload['comment']);

                return true;
            }));

        $this->controller->createComment();
    }

    // ─── getVersionNumber() ──────────────────────────────────────

    #[Test]
    public function getVersionNumberUsesRequestParamWhenPresent(): void
    {
        $this->setValidator();
        $this->setRequestParams(['version_number' => '5']);

        $parentReflector = new ReflectionClass(SegmentTranslationIssueController::class);
        $method = $parentReflector->getMethod('getVersionNumber');
        $result = $method->invoke($this->controller);

        $this->assertSame(5, $result);
    }

    #[Test]
    public function getVersionNumberFallsBackToTranslationVersionNumber(): void
    {
        $translation = new SegmentTranslationStruct();
        $translation->id_segment = 1;
        $translation->id_job = self::JOB_ID;
        $translation->version_number = 7;
        $this->setValidator(null, $translation);
        $this->setRequestParams([]);

        $parentReflector = new ReflectionClass(SegmentTranslationIssueController::class);
        $method = $parentReflector->getMethod('getVersionNumber');
        $result = $method->invoke($this->controller);

        $this->assertSame(7, $result);
    }

    // ─── create() ────────────────────────────────────────────────

    #[Test]
    public function createCallsModelSaveAndReturnsJson(): void
    {
        $this->setValidator();
        $this->setRequestParams([
            'id_segment' => 1,
            'id_job' => self::JOB_ID,
            'id_category' => 1,
            'severity' => 'Minor',
            'target_text' => 'translated text',
            'start_node' => 0,
            'start_offset' => 0,
            'end_node' => 0,
            'end_offset' => 5,
            'comment' => 'test issue',
            'password' => self::REVIEW_PASSWORD,
            'revision_number' => 1,
        ]);

        $savedStruct = new EntryStruct();
        $savedStruct->id = 990099;
        $savedStruct->id_segment = 1;
        $savedStruct->id_job = self::JOB_ID;
        $savedStruct->id_category = 1;
        $savedStruct->severity = 'Minor';
        $savedStruct->source_page = 1;
        $savedStruct->translation_version = 1;
        $savedStruct->start_node = 0;
        $savedStruct->start_offset = 0;
        $savedStruct->end_node = 0;
        $savedStruct->end_offset = 5;
        $savedStruct->is_full_segment = false;
        $savedStruct->penalty_points = 1;
        $savedStruct->target_text = 'translated text';

        $modelMock = $this->createMock(TranslationIssueModel::class);
        $modelMock->expects($this->once())->method('save')->willReturn($savedStruct);
        $this->controller->mockModel = $modelMock;

        $this->responseMock
            ->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $payload): bool {
                $this->assertArrayHasKey('issue', $payload);
                $this->assertIsArray($payload['issue']);

                return true;
            }));

        $this->controller->create();
    }

    // ─── update() full path ──────────────────────────────────────

    #[Test]
    public function updateThrowsAuthorizationErrorWhenUserIsStranger(): void
    {
        $this->setControllerUser(self::STRANGER_UID);
        $this->setValidator();

        $this->setRequestParams([
            'id_issue' => 990030,
            'id_segment' => 1,
            'id_job' => self::JOB_ID,
            'id_category' => 1,
            'severity' => 'Major',
            'target_text' => 'new text',
            'start_node' => 0,
            'start_offset' => 0,
            'end_node' => 0,
            'end_offset' => 5,
            'comment' => 'updated',
            'password' => self::REVIEW_PASSWORD,
        ]);

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionMessage('Not Authorized');

        $this->controller->update();
    }

    // ─── afterConstruct() ────────────────────────────────────────

    #[Test]
    public function afterConstructAppendsValidators(): void
    {
        $realReflector = new ReflectionClass(SegmentTranslationIssueController::class);
        /** @var SegmentTranslationIssueController $realController */
        $realController = $realReflector->newInstanceWithoutConstructor();

        $request = $this->createStub(Request::class);
        $request->method('param')->willReturnCallback(
            static fn(string $key) => match ($key) {
                'id_job' => self::JOB_ID,
                'password' => self::JOB_PASSWORD,
                default => null,
            }
        );
        $response = $this->createStub(Response::class);

        $baseReflector = new ReflectionClass(\Controller\Abstracts\KleinController::class);
        $baseReflector->getProperty('request')->setValue($realController, $request);
        $baseReflector->getProperty('response')->setValue($realController, $response);
        $baseReflector->getProperty('params')->setValue($realController, [
            'id_job' => (string)self::JOB_ID,
            'password' => self::JOB_PASSWORD,
        ]);

        $afterConstruct = $realReflector->getMethod('afterConstruct');
        $afterConstruct->invoke($realController);

        /** @var list<mixed> $validators */
        $validators = $baseReflector->getProperty('validators')->getValue($realController);
        $this->assertCount(2, $validators);
        $this->assertInstanceOf(\Controller\API\Commons\Validators\LoginValidator::class, $validators[0]);
        $this->assertInstanceOf(\Controller\API\Commons\Validators\ChunkPasswordValidator::class, $validators[1]);
    }
}
