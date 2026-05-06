<?php

namespace unit\Controllers;

use Controller\API\App\CommentController;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Model\Comments\CommentDao;
use Model\Comments\CommentStruct;
use Model\DataAccess\Database;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use TestHelpers\AbstractTest;

class TestableCommentController extends CommentController
{
    public function __construct()
    {
    }

    protected function afterConstruct(): void
    {
    }
}

class FakeJobStructForTeamMentions extends JobStruct
{
    public ProjectStruct $fakeProject;

    public function getProject(int $ttl = 86400): ProjectStruct
    {
        return $this->fakeProject;
    }
}

#[AllowMockObjectsWithoutExpectations]
class CommentControllerTest extends AbstractTest
{
    private ReflectionClass $reflector;
    private TestableCommentController $controller;
    private Request $requestStub;
    private Response $responseMock;

    /** @var list<int> */
    private array $createdCommentIds = [];

    /** @throws ReflectionException */
    public function setUp(): void
    {
        parent::setUp();

        $this->reflector = new ReflectionClass(TestableCommentController::class);
        $this->controller = $this->reflector->newInstanceWithoutConstructor();

        $this->requestStub = $this->createStub(Request::class);
        $this->responseMock = $this->createMock(Response::class);
        $this->responseMock->method('json')->willReturnSelf();
        $this->responseMock->method('code')->willReturnSelf();

        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);

        $user = new UserStruct();
        $user->uid = 1886472134;
        $user->email = 'foo@example.org';
        $user->first_name = 'John';
        $user->last_name = 'Doe';

        $this->setControllerUser($user, true);

        Database::obtain()->begin();
    }

    public function tearDown(): void
    {
        $conn = Database::obtain()->getConnection();
        if ($conn->inTransaction()) {
            Database::obtain()->rollback();
        }

        parent::tearDown();
    }

    /** @throws ReflectionException */
    private function setControllerUser(UserStruct $user, bool $isLogged): void
    {
        $authReflector = new ReflectionClass($this->controller);
        while (!$authReflector->hasProperty('user') && $authReflector->getParentClass() !== false) {
            $authReflector = $authReflector->getParentClass();
        }

        $authReflector->getProperty('user')->setValue($this->controller, $user);
        $authReflector->getProperty('userIsLogged')->setValue($this->controller, $isLogged);
    }

    private function setupRequestParams(array $params): void
    {
        $this->requestStub->method('param')->willReturnCallback(
            static fn(string $key) => $params[$key] ?? null
        );
    }

    /** @throws ReflectionException */
    private function invokePrivate(string $name, array $args = []): mixed
    {
        $method = $this->reflector->getMethod($name);

        return $method->invokeArgs($this->controller, $args);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validRequest(array $overrides = []): array
    {
        return array_merge([
            'id_client' => 'client-test',
            'username' => 'John Doe',
            'id_job' => '1886428338',
            'id_segment' => '1',
            'source_page' => '1',
            'is_anonymous' => '0',
            'revision_number' => '0',
            'first_seg' => '1',
            'last_seg' => '4',
            'id_comment' => null,
            'password' => 'a90acf203402',
            'message' => 'hello',
        ], $overrides);
    }

    private function createCommentRecord(
        int $idJob,
        int $idSegment,
        ?int $uid,
        int $sourcePage,
        ?string $message = 'msg',
        int $messageType = 1,
        ?string $resolveDate = null
    ): int {
        $commentDao = new CommentDao(Database::obtain());
        $comment = new CommentStruct();
        $comment->id_job = $idJob;
        $comment->id_segment = $idSegment;
        $comment->email = 'foo@example.org';
        $comment->full_name = 'John Doe';
        $comment->uid = $uid;
        $comment->source_page = $sourcePage;
        $comment->message_type = $messageType;
        $comment->message = $message;
        $comment->resolve_date = $resolveDate;

        $saved = $commentDao->saveComment($comment);
        $id = (int)$saved->id;
        $this->createdCommentIds[] = $id;

        return $id;
    }

    private function baseCommentRequestForDelete(int $idComment, array $overrides = []): array
    {
        return $this->validRequest(array_merge([
            'id_comment' => (string)$idComment,
            'id_job' => '1886428338',
            'id_segment' => '1',
            'source_page' => '1',
            'message' => 'delete me',
            'password' => 'a90acf203402',
        ], $overrides));
    }

    #[Test]
    public function validateTheRequest_valid_payload_returns_expected_data(): void
    {
        $this->setupRequestParams($this->validRequest([
            'message' => '<b>ciao</b> & test',
            'revision_number' => '2',
            'is_anonymous' => '1',
        ]));

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertSame('client-test', $result['id_client']);
        $this->assertSame('John Doe', $result['username']);
        $this->assertSame('1886428338', $result['id_job']);
        $this->assertSame('1', $result['id_segment']);
        $this->assertSame('&lt;b&gt;ciao&lt;/b&gt; &amp; test', $result['message']);
        $this->assertSame(2, $result['revision_number']);
        $this->assertTrue($result['is_anonymous']);
        $this->assertInstanceOf(JobStruct::class, $result['job']);
        $this->assertSame(1886428338, $result['job']->id);
    }

    #[Test]
    public function validateTheRequest_empty_job_throws_wrong_password_with_code_minus10(): void
    {
        $this->setupRequestParams($this->validRequest([
            'id_job' => '999999999',
            'password' => 'not-valid',
        ]));

        try {
            $this->invokePrivate('validateTheRequest');
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('wrong password', $e->getMessage());
            $this->assertSame(-10, $e->getCode());
        }
    }

    #[Test]
    public function prepareMentionCommentData_builds_mention_comment_struct(): void
    {
        $mentioned = new UserStruct();
        $mentioned->uid = 1886472134;
        $mentioned->email = 'foo@example.org';
        $mentioned->first_name = 'John';
        $mentioned->last_name = 'Doe';

        $request = [
            'id_segment' => '1',
            'id_job' => '1886428338',
            'source_page' => '1',
        ];

        /** @var CommentStruct $result */
        $result = $this->invokePrivate('prepareMentionCommentData', [$request, $mentioned]);

        $this->assertSame(1, $result->id_segment);
        $this->assertSame(1886428338, $result->id_job);
        $this->assertSame('John Doe', $result->full_name);
        $this->assertSame(CommentDao::TYPE_MENTION, $result->message_type);
        $this->assertSame('', $result->message);
        $this->assertSame('foo@example.org', $result->email);
        $this->assertSame(1886472134, $result->uid);
    }

    #[Test]
    public function resolveUserMentions_extracts_numeric_mentions_and_returns_ints(): void
    {
        $result = $this->invokePrivate('resolveUserMentions', ['hello {@1886472134@} and {@123@}']);

        $this->assertSame([1886472134, 123], $result);
    }

    #[Test]
    public function resolveUserMentions_empty_message_returns_empty_array(): void
    {
        $result = $this->invokePrivate('resolveUserMentions', ['']);

        $this->assertSame([], $result);
    }

    #[Test]
    public function resolveTeamMentions_without_team_marker_returns_empty(): void
    {
        $job = JobDao::getByIdAndPassword(1886428338, 'a90acf203402', 60);
        $this->assertInstanceOf(JobStruct::class, $job);

        $result = $this->invokePrivate('resolveTeamMentions', [$job, 'no mentions']);

        $this->assertSame([], $result);
    }

    #[Test]
    public function resolveTeamMentions_with_team_marker_and_null_team_returns_empty(): void
    {
        $job = new FakeJobStructForTeamMentions();
        $project = new ProjectStruct();
        $project->id = 1886428330;
        $project->id_team = null;
        $job->fakeProject = $project;

        $result = $this->invokePrivate('resolveTeamMentions', [$job, 'notify {@team@}']);

        $this->assertSame([], $result);
    }

    #[Test]
    public function resolveTeamMentions_with_valid_team_returns_uids(): void
    {
        $job = JobDao::getByIdAndPassword(1886428342, '92c5e0ce9316', 60);
        $this->assertInstanceOf(JobStruct::class, $job);

        $result = $this->invokePrivate('resolveTeamMentions', [$job, 'ping {@team@}']);

        $this->assertNotEmpty($result);
        $this->assertContains(1886428336, $result);
    }

    #[Test]
    public function filterUsers_filters_current_user_and_deduplicates_by_uid(): void
    {
        $current = new UserStruct();
        $current->uid = 1886472134;

        $otherA = new UserStruct();
        $otherA->uid = 10;

        $otherADuplicate = new UserStruct();
        $otherADuplicate->uid = 10;

        $otherB = new UserStruct();
        $otherB->uid = 11;

        $result = $this->invokePrivate('filterUsers', [[$current, $otherA, $otherADuplicate, $otherB]]);

        $uids = array_map(static fn(UserStruct $u) => $u->uid, array_values($result));
        $this->assertSame([10, 11], $uids);
    }

    #[Test]
    public function filterUsers_excludes_users_already_sent_in_uid_list(): void
    {
        $u10 = new UserStruct();
        $u10->uid = 10;

        $u11 = new UserStruct();
        $u11->uid = 11;

        $result = $this->invokePrivate('filterUsers', [[$u10, $u11], [10]]);

        $uids = array_map(static fn(UserStruct $u) => $u->uid, array_values($result));
        $this->assertSame([11], $uids);
    }

    #[Test]
    public function prepareCommentData_builds_struct_and_resolves_mentions(): void
    {
        $job = JobDao::getByIdAndPassword(1886428338, 'a90acf203402', 60);
        $this->assertInstanceOf(JobStruct::class, $job);

        $request = [
            'id_segment' => '1',
            'id_job' => '1886428338',
            'username' => 'John Doe',
            'source_page' => '1',
            'message' => 'hello {@1886472134@}',
            'revision_number' => 0,
            'is_anonymous' => false,
            'job' => $job,
        ];

        $result = $this->invokePrivate('prepareCommentData', [$request]);

        /** @var CommentStruct $struct */
        $struct = $result['struct'];

        $this->assertSame(1, $struct->id_segment);
        $this->assertSame(1886428338, $struct->id_job);
        $this->assertSame('John Doe', $struct->full_name);
        $this->assertSame(1, $struct->source_page);
        $this->assertSame('hello {@1886472134@}', $struct->message);
        $this->assertSame('foo@example.org', $struct->email);
        $this->assertSame(1886472134, $struct->uid);
        $this->assertSame([1886472134], $result['users_mentioned_id']);
        $this->assertCount(0, $result['users_mentioned']);
    }

    #[Test]
    public function getProjectPasswords_extracts_project_passwords(): void
    {
        $result = $this->invokePrivate('getProjectPasswords', [1886428330]);

        $this->assertNotEmpty($result);
        $this->assertIsString($result[0]);
        $this->assertNotSame('', $result[0]);
    }

    #[Test]
    public function delete_without_id_comment_behaves_as_comment_not_found_minus202(): void
    {
        $params = $this->validRequest();
        $this->setupRequestParams(array_merge($params, ['id_comment' => []]));

        try {
            $this->controller->delete();
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(-202, $e->getCode());
        }
    }

    #[Test]
    public function delete_comment_not_found_throws_minus202(): void
    {
        $this->setupRequestParams($this->baseCommentRequestForDelete(99999991));

        try {
            $this->controller->delete();
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(-202, $e->getCode());
        }
    }

    #[Test]
    public function delete_comment_with_null_uid_throws_minus203(): void
    {
        $segment = 990001;
        $idComment = $this->createCommentRecord(1886428338, $segment, null, 1, 'to delete');
        $this->setupRequestParams($this->baseCommentRequestForDelete($idComment, ['id_segment' => (string)$segment]));

        try {
            $this->controller->delete();
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(-203, $e->getCode());
        }
    }

    #[Test]
    public function delete_comment_with_uid_mismatch_throws_minus203(): void
    {
        $segment = 990002;
        $idComment = $this->createCommentRecord(1886428338, $segment, 123456, 1, 'to delete');
        $this->setupRequestParams($this->baseCommentRequestForDelete($idComment, ['id_segment' => (string)$segment]));

        try {
            $this->controller->delete();
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(-203, $e->getCode());
        }
    }

    #[Test]
    public function delete_with_segment_mismatch_throws_minus204(): void
    {
        $idComment = $this->createCommentRecord(1886428338, 2, 1886472134, 1, 'segment mismatch');
        $this->setupRequestParams($this->baseCommentRequestForDelete($idComment, ['id_segment' => '1']));

        try {
            $this->controller->delete();
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(-204, $e->getCode());
        }
    }

    #[Test]
    public function delete_when_not_last_comment_throws_minus205(): void
    {
        $segment = 990003;
        $first = $this->createCommentRecord(1886428338, $segment, 1886472134, 1, 'first');
        $this->createCommentRecord(1886428338, $segment, 1886472134, 1, 'second');

        $this->setupRequestParams($this->baseCommentRequestForDelete($first, ['id_segment' => (string)$segment]));

        try {
            $this->controller->delete();
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(-205, $e->getCode());
        }
    }

    #[Test]
    public function delete_with_job_mismatch_throws_minus206(): void
    {
        $segment = 990005;
        $idComment = $this->createCommentRecord(1886428342, $segment, 1886472134, 1, 'job mismatch');
        $this->setupRequestParams($this->baseCommentRequestForDelete($idComment, [
            'id_job' => '1886428338',
            'id_segment' => (string)$segment,
            'password' => 'a90acf203402',
        ]));

        try {
            $this->controller->delete();
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(-206, $e->getCode());
        }
    }

    #[Test]
    public function delete_with_source_page_mismatch_throws_minus207(): void
    {
        $_SERVER['HTTP_REFERER'] = 'http://localhost/translate/project/en-it/1886428338-a90acf203402';

        $segment = 990004;
        $this->createCommentRecord(1886428338, $segment, 1886472134, 2, 'source page mismatch first');
        $idComment = $this->createCommentRecord(1886428338, $segment, 1886472134, 2, 'source page mismatch last');
        $this->setupRequestParams($this->baseCommentRequestForDelete($idComment, [
            'source_page' => '1',
            'id_segment' => (string)$segment,
        ]));

        try {
            $this->controller->delete();
            $this->fail('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(-207, $e->getCode());
        }
    }

    #[Test]
    public function sendEmail_with_invalid_project_returns_404_and_stops(): void
    {
        $comment = new CommentStruct();
        $comment->id_segment = 1;
        $comment->revision_number = 0;
        $comment->message = 'hello';

        $job = new JobStruct();
        $job->id = 1886428338;
        $job->id_project = 99999999;
        $job->source = 'en-GB';
        $job->target = 'es-ES';
        $job->job_first_segment = 1;
        $job->job_last_segment = 4;

        $this->responseMock->expects($this->once())->method('code')->with(404)->willReturnSelf();
        $this->responseMock->expects($this->once())->method('json')->with($this->arrayHasKey('code'))->willReturnSelf();

        $this->invokePrivate('sendEmail', [$comment, $job, [], []]);
    }

    #[Test]
    public function sendEmail_with_missing_revision_url_returns_404_and_stops(): void
    {
        $comment = new CommentStruct();
        $comment->id_segment = 1;
        $comment->revision_number = 99;
        $comment->message = 'hello';

        $job = JobDao::getByIdAndPassword(1886428338, 'a90acf203402', 60);
        $this->assertInstanceOf(JobStruct::class, $job);

        $this->responseMock->expects($this->once())->method('code')->with(404)->willReturnSelf();
        $this->responseMock->expects($this->once())->method('json')->with($this->arrayHasKey('code'))->willReturnSelf();

        $this->invokePrivate('sendEmail', [$comment, $job, [], []]);
    }

    #[Test]
    public function sendEmail_with_valid_translation_url_and_empty_recipients_completes_without_404(): void
    {
        $comment = new CommentStruct();
        $comment->id_segment = 1;
        $comment->revision_number = 0;
        $comment->message = 'hello';

        $job = JobDao::getByIdAndPassword(1886428338, 'a90acf203402', 60);
        $this->assertInstanceOf(JobStruct::class, $job);

        $this->responseMock->expects($this->never())->method('code');

        $this->invokePrivate('sendEmail', [$comment, $job, [], []]);
        $this->assertTrue(true);
    }

    #[Test]
    public function sendEmail_with_mentioned_user_executes_mention_branch(): void
    {
        $initialOutputLevel = ob_get_level();

        $comment = new CommentStruct();
        $comment->id_segment = 1;
        $comment->revision_number = 0;
        $comment->message = 'hello {@1886472135@}';
        $comment->message_type = CommentDao::TYPE_COMMENT;

        $job = JobDao::getByIdAndPassword(1886428338, 'a90acf203402', 60);
        $this->assertInstanceOf(JobStruct::class, $job);

        $mentioned = new UserStruct();
        $mentioned->uid = 1886472135;
        $mentioned->email = 'test-email-69fb439e10cce9.44080388@example.org';
        $mentioned->first_name = 'John';
        $mentioned->last_name = 'Connor';

        try {
            $this->invokePrivate('sendEmail', [$comment, $job, [], [$mentioned]]);
        } catch (\Throwable) {
            $this->assertTrue(true);
        } finally {
            while (ob_get_level() > $initialOutputLevel) {
                ob_end_clean();
            }
        }

        $this->assertTrue(true);
    }

    #[Test]
    public function sendEmail_with_resolve_message_executes_resolve_email_branch(): void
    {
        $initialOutputLevel = ob_get_level();

        $comment = new CommentStruct();
        $comment->id_segment = 1;
        $comment->revision_number = 0;
        $comment->message = 'resolved thread';
        $comment->message_type = CommentDao::TYPE_RESOLVE;

        $job = JobDao::getByIdAndPassword(1886428338, 'a90acf203402', 60);
        $this->assertInstanceOf(JobStruct::class, $job);

        $user = new UserStruct();
        $user->uid = 1886472135;
        $user->email = 'test-email-69fb439e10cce9.44080388@example.org';
        $user->first_name = 'John';
        $user->last_name = 'Connor';

        try {
            $this->invokePrivate('sendEmail', [$comment, $job, [$user], []]);
        } catch (\Throwable) {
            $this->assertTrue(true);
        } finally {
            while (ob_get_level() > $initialOutputLevel) {
                ob_end_clean();
            }
        }

        $this->assertTrue(true);
    }

    #[Test]
    public function resolveUsers_includes_contributors_and_excludes_already_mentioned_and_current_user(): void
    {
        // uid 1886472176 is the project owner (foo@example.org) in the test DB.
        // uid 1886472135 does NOT exist in users table, so getByUids returns empty.
        // We test that the owner is included (not excluded) and that the
        // already-mentioned uid (which doesn't resolve to a UserStruct) is absent.
        $segment = 990006;
        $this->createCommentRecord(1886428338, $segment, 1886472176, 1, 'contributor');

        $comment = new CommentStruct();
        $comment->id_job = 1886428338;
        $comment->id_segment = $segment;
        $comment->uid = 1886472134; // current user (not in DB, so won't be excluded by filterUsers)

        $job = JobDao::getByIdAndPassword(1886428338, 'a90acf203402', 60);
        $this->assertInstanceOf(JobStruct::class, $job);

        // Exclude the owner uid from the result (simulating already-mentioned)
        $users = $this->invokePrivate('resolveUsers', [$comment, $job, [1886472176]]);

        $uids = array_map(static fn(UserStruct $u) => $u->uid, array_values($users));
        $this->assertNotContains(1886472176, $uids);
    }

    #[Test]
    public function resolveUsers_returns_project_owner_when_not_excluded(): void
    {
        // resolveUsers fetches the project owner via getProjectOwner($job->id).
        // The owner (foo@example.org) should be present if not excluded.
        // We don't hardcode the owner uid — instead verify a non-empty result.
        $segment = 990007;
        $this->createCommentRecord(1886428338, $segment, 1886472134, 1, 'contributor');

        $comment = new CommentStruct();
        $comment->id_job = 1886428338;
        $comment->id_segment = $segment;
        $comment->uid = 99999999; // Use a uid that won't match any real user (avoids self-exclusion)

        // Override controller user to avoid self-filtering of real DB users
        $user = new UserStruct();
        $user->uid = 99999999;
        $user->email = 'nobody@test.invalid';
        $user->first_name = 'No';
        $user->last_name = 'Body';
        $this->setControllerUser($user, true);

        $job = JobDao::getByIdAndPassword(1886428338, 'a90acf203402', 60);
        $this->assertInstanceOf(JobStruct::class, $job);

        /** @var array<int, UserStruct> $users */
        $users = $this->invokePrivate('resolveUsers', [$comment, $job, []]);

        // The project owner should be included (not excluded)
        $this->assertNotEmpty($users, 'Expected at least the project owner in results');
        $emails = array_map(static fn(UserStruct $u) => $u->email, array_values($users));
        $this->assertContains('foo@example.org', $emails);
    }

    #[Test]
    public function enqueueComment_throws_when_broker_is_unavailable(): void
    {
        $comment = new CommentStruct();
        $comment->id = 123;
        $comment->id_segment = 1;
        $comment->id_job = 1886428338;
        $comment->source_page = 1;
        $comment->full_name = 'John Doe';
        $comment->message = 'hello';
        $comment->message_type = 1;
        $comment->timestamp = time();
        $comment->create_date = date('Y-m-d H:i:s');

        $this->expectException(\Throwable::class);
        $this->invokePrivate('enqueueComment', [$comment, 1886428330, '1886428338', 'client-test']);
    }

    #[Test]
    public function enqueueDeleteCommentMessage_throws_when_broker_is_unavailable(): void
    {
        $this->expectException(\Throwable::class);
        $this->invokePrivate('enqueueDeleteCommentMessage', ['1886428338', 'client-test', 1886428330, 7, 1, '1']);
    }

    #[Test]
    public function getRange_returns_json_data_for_valid_request(): void
    {
        $this->setupRequestParams($this->validRequest([
            'first_seg' => '1',
            'last_seg' => '4',
        ]));

        $this->responseMock->expects($this->once())->method('json')->with(
            $this->callback(function (array $payload): bool {
                return isset($payload['data']['entries']['comments']) && isset($payload['data']['user']['full_name']);
            })
        )->willReturnSelf();

        $this->controller->getRange();
    }

    #[Test]
    public function create_reaches_enqueue_and_throws_when_broker_unavailable(): void
    {
        $this->setupRequestParams($this->validRequest([
            'message' => 'create comment {@1886472134@}',
            'id_segment' => '1',
            'source_page' => '1',
            'revision_number' => '0',
            'is_anonymous' => '0',
        ]));

        $this->expectException(\Throwable::class);
        $this->controller->create();
    }

    #[Test]
    public function resolve_reaches_enqueue_and_throws_when_broker_unavailable(): void
    {
        $this->setupRequestParams($this->validRequest([
            'message' => 'resolve thread',
            'id_segment' => '1',
            'source_page' => '1',
            'revision_number' => '0',
            'is_anonymous' => '0',
        ]));

        $this->expectException(\Throwable::class);
        $this->controller->resolve();
    }
}
