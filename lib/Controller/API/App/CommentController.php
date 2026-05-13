<?php

namespace Controller\API\App;

use Controller\Abstracts\KleinController;
use Controller\API\Commons\Validators\LoginValidator;
use DomainException;
use Exception;
use InvalidArgumentException;
use Model\Comments\BaseCommentStruct;
use Model\Comments\CommentDao;
use Model\Comments\CommentStruct;
use Model\DataAccess\Database;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectDao;
use Model\Teams\MembershipDao;
use Model\Teams\MembershipStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PDOException;
use ReflectionException;
use RuntimeException;
use Stomp\Transport\Message;
use TypeError;
use Utils\ActiveMQ\AMQHandler;
use Utils\Email\CommentEmail;
use Utils\Email\CommentMentionEmail;
use Utils\Email\CommentResolveEmail;
use Utils\Registry\AppConfig;
use Utils\Tools\Utils;
use Utils\Url\JobUrlBuilder;

class CommentController extends KleinController
{

    protected function afterConstruct(): void
    {
        $this->appendValidator(new LoginValidator($this));
    }

    /**
     * @return void
     * @throws ReflectionException
     * @throws TypeError
     * @throws PDOException
     * @throws Exception
     */
    public function getRange(): void
    {
        $data = [];
        $request = $this->validateTheRequest();

        $struct = new CommentStruct();
        $struct->id_job = $request['id_job'];
        $struct->first_segment = $request['first_seg'];
        $struct->last_segment = $request['last_seg'];

        $commentDao = new CommentDao(Database::obtain());

        $data['entries'] = [
            'comments' => $commentDao->getCommentsForChunk($request['job'])
        ];

        $data['user'] = [
            'full_name' => $this->user->fullName()
        ];

        $this->response->json([
            "data" => $data
        ]);
    }

    /**
     * @return void
     * @throws ReflectionException
     * @throws TypeError
     * @throws Exception
     */
    public function resolve(): void
    {
        $request = $this->validateTheRequest();
        $prepareCommandData = $this->prepareCommentData($request);
        $comment_struct = $prepareCommandData['struct'];
        $users_mentioned_id = $prepareCommandData['users_mentioned_id'];
        $users_mentioned = $prepareCommandData['users_mentioned'];

        $commentDao = new CommentDao(Database::obtain());
        $new_record = $commentDao->resolveThread($comment_struct);

        $this->enqueueComment($new_record, $request['job']->id_project, $request['id_job'], $request['id_client']);
        $users = $this->resolveUsers($comment_struct, $request['job'], $users_mentioned_id);
        $this->sendEmail($comment_struct, $request['job'], $users, $users_mentioned);

        $this->response->json([
            "data" => [
                'entries' => [
                    'comments' => [$new_record]
                ],
                'user' => [
                    'full_name' => $this->user->fullName()
                ]
            ]
        ]);
    }

    /**
     * @return void
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function create(): void
    {
        $request = $this->validateTheRequest();
        $prepareCommandData = $this->prepareCommentData($request);
        $comment_struct = $prepareCommandData['struct'];
        $users_mentioned_id = $prepareCommandData['users_mentioned_id'];
        $users_mentioned = $prepareCommandData['users_mentioned'];

        $commentDao = new CommentDao(Database::obtain());
        $new_record = $commentDao->saveComment($comment_struct);

        foreach ($users_mentioned as $user_mentioned) {
            $mentioned_comment = $this->prepareMentionCommentData($request, $user_mentioned);
            $commentDao->saveComment($mentioned_comment);
        }

        $this->enqueueComment($new_record, $request['job']->id_project, $request['id_job'], $request['id_client']);
        $users = $this->resolveUsers($comment_struct, $request['job'], $users_mentioned_id);
        $this->sendEmail($comment_struct, $request['job'], $users, $users_mentioned);

        $this->response->json([
            "data" => [
                'entries' => [
                    'comments' => [$new_record]
                ],
                'user' => [
                    'full_name' => $this->user->fullName()
                ]
            ]
        ]);
    }

    /**
     * @return void
     * @throws ReflectionException
     * @throws InvalidArgumentException
     * @throws Exception
     * @throws PDOException
     * @throws RuntimeException
     */
    public function delete(): void
    {
        $request = $this->validateTheRequest();

        if (!isset($request['id_comment'])) {
            throw new InvalidArgumentException("Id comment not provided.", -200);
        }

        $user = $this->user;
        $idComment = $request['id_comment'];
        $commentDao = new CommentDao(Database::obtain());
        $comment = $commentDao->fetchById($idComment, BaseCommentStruct::class, 86400);

        if (null === $comment) {
            throw new InvalidArgumentException("Comment not found.", -202);
        }

        if ($comment->uid === null) {
            throw new InvalidArgumentException("You are not the author of the comment.", -203);
        }

        if ((int)$comment->uid !== (int)$user->uid) {
            throw new InvalidArgumentException("You are not the author of the comment.", -203);
        }

        if ($comment->id_segment !== (int)$request['id_segment']) {
            throw new InvalidArgumentException("Not corresponding id segment.", -204);
        }

        $segments = $commentDao->getBySegmentId($comment->id_segment);
        $lastSegment = end($segments);

        if ($lastSegment === false) {
            throw new InvalidArgumentException("No comments found for this segment.", -205);
        }

        if ((int)$lastSegment->id !== (int)$request['id_comment']) {
            throw new InvalidArgumentException("Only the last element comment can be deleted.", -205);
        }

        if ($comment->id_job !== (int)$request['id_job']) {
            throw new InvalidArgumentException("Not corresponding id job.", -206);
        }

        // Fix for R2
        // The comments from R2 phase are wrongly saved with source_page = 2
        $sourcePage = Utils::getSourcePageFromReferer();

        $allowedSourcePages = [];
        $allowedSourcePages[] = (int)$request['source_page'];

        if ($sourcePage == 3) {
            $allowedSourcePages[] = 2;
        }

        if (!in_array($comment->source_page, $allowedSourcePages)) {
            throw new InvalidArgumentException("Not corresponding source_page.", -207);
        }

        if (!$commentDao->deleteComment($comment)) {
            throw new RuntimeException("Error when deleting a comment.", -220);
        }

        $this->enqueueDeleteCommentMessage(
            $request['id_job'],
            $request['id_client'],
            $request['job']->id_project,
            $comment->id,
            $comment->id_segment,
            $request['source_page']
        );

        $this->response->json([
            "data" => [
                [
                    "id" => $comment->id
                ],
                'user' => [
                    'full_name' => $this->user->fullName()
                ]
            ]
        ]);
    }

    /**
     * @return array<string, mixed>
     * @throws ReflectionException
     * @throws Exception
     * @throws InvalidArgumentException
     */
    private function validateTheRequest(): array
    {
        $id_client = filter_var($this->request->param('id_client'), FILTER_SANITIZE_SPECIAL_CHARS);
        $username = filter_var($this->request->param('username'), FILTER_SANITIZE_SPECIAL_CHARS);
        $id_job = filter_var($this->request->param('id_job'), FILTER_SANITIZE_NUMBER_INT);
        $id_segment = filter_var($this->request->param('id_segment'), FILTER_SANITIZE_NUMBER_INT);
        $source_page = filter_var($this->request->param('source_page'), FILTER_SANITIZE_NUMBER_INT);
        $is_anonymous = filter_var($this->request->param('is_anonymous'), FILTER_VALIDATE_BOOLEAN);
        $revision_number = filter_var($this->request->param('revision_number'), FILTER_SANITIZE_NUMBER_INT);
        $first_seg = filter_var($this->request->param('first_seg'), FILTER_SANITIZE_NUMBER_INT);
        $last_seg = filter_var($this->request->param('last_seg'), FILTER_SANITIZE_NUMBER_INT);
        $id_comment = filter_var($this->request->param('id_comment'), FILTER_SANITIZE_NUMBER_INT);
        $password = filter_var($this->request->param('password'), FILTER_SANITIZE_SPECIAL_CHARS, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $message = filter_var($this->request->param('message'), FILTER_UNSAFE_RAW);
        $message = htmlspecialchars((string)$message);

        $job = JobDao::getByIdAndPassword((int)$id_job, (string)$password, 60 * 60 * 24);

        if (empty($job)) {
            throw new InvalidArgumentException("wrong password", -10);
        }

        return [
            'first_seg' => $first_seg,
            'id_client' => $id_client,
            'id_comment' => $id_comment,
            'id_job' => $id_job,
            'id_segment' => $id_segment,
            'is_anonymous' => $is_anonymous,
            'job' => $job,
            'last_seg' => $last_seg,
            'message' => $message,
            'password' => $password,
            'revision_number' => (int)$revision_number,
            'source_page' => $source_page,
            'username' => $username,
        ];
    }

    /**
     * @param array<string, mixed> $request
     *
     * @return array{struct: CommentStruct, users_mentioned_id: list<int|null>, users_mentioned: UserStruct[]}
     * @throws ReflectionException
     * @throws TypeError
     * @throws Exception
     */
    private function prepareCommentData(array $request): array
    {
        $struct = new CommentStruct();

        $struct->id_segment = $request['id_segment'];
        $struct->id_job = $request['id_job'];
        $struct->full_name = $request['username'];
        $struct->source_page = $request['source_page'];
        $struct->message = $request['message'];
        $struct->revision_number = $request['revision_number'];
        $struct->is_anonymous = $request['is_anonymous'];
        $struct->email = $this->user->getEmail();
        $struct->uid = $this->user->getUid();

        $message = (string)$struct->message;
        $user_mentions = $this->resolveUserMentions($message);
        $user_team_mentions = $this->resolveTeamMentions($request['job'], $message);
        $userDao = new UserDao(Database::obtain());
        $users_mentioned_id = array_values(array_unique(array_merge($user_mentions, $user_team_mentions)));
        $users_mentioned = $this->filterUsers($userDao->getByUids($users_mentioned_id));

        return [
            'struct' => $struct,
            'users_mentioned_id' => $users_mentioned_id,
            'users_mentioned' => $users_mentioned,
        ];
    }

    /**
     * @param array<string, mixed> $request
     * @param UserStruct $user
     *
     * @return CommentStruct
     * @throws TypeError
     */
    private function prepareMentionCommentData(array $request, UserStruct $user): CommentStruct
    {
        $struct = new CommentStruct();

        $struct->id_segment = $request['id_segment'];
        $struct->id_job = $request['id_job'];
        $struct->full_name = $user->fullName();
        $struct->source_page = $request['source_page'];
        $struct->message = "";
        $struct->message_type = CommentDao::TYPE_MENTION;
        $struct->email = $user->getEmail();
        $struct->uid = $user->getUid();

        return $struct;
    }

    /**
     * @param string $message
     *
     * @return list<int>
     */
    private function resolveUserMentions(string $message): array
    {
        return array_values(array_map('intval', CommentDao::getUsersIdFromContent($message)));
    }

    /**
     * @param JobStruct $job
     * @param string $message
     *
     * @return list<int>
     * @throws ReflectionException
     * @throws Exception
     */
    private function resolveTeamMentions(JobStruct $job, string $message): array
    {
        $users = [];

        if (str_contains($message, "{@team@}")) {
            $project = $job->getProject();

            if ($project->id_team === null) {
                return [];
            }

            $memberships = (new MembershipDao())->setCacheTTL(60 * 60 * 24)->getMemberListByTeamId($project->id_team, false);

            foreach ($memberships as $membership) {
                if ($membership instanceof MembershipStruct && $membership->uid !== null) {
                    $users[] = $membership->uid;
                }
            }
        }

        return $users;
    }

    /**
     * @param UserStruct[] $users
     * @param list<int|null> $uidSentList
     *
     * @return UserStruct[]
     */
    private function filterUsers(array $users, array $uidSentList = []): array
    {
        $userIsLogged = $this->userIsLogged;
        $current_uid = $this->user->uid;

        // find deep duplicates
        return array_filter($users, function ($item) use ($userIsLogged, $current_uid, &$uidSentList) {
            if ($userIsLogged && $current_uid == $item->uid) {
                return false;
            }

            // find deep duplicates
            if (in_array($item->uid, $uidSentList)) {
                return false;
            }
            $uidSentList[] = $item->uid;

            return true;
        });
    }

    /**
     * @param CommentStruct $comment
     * @param JobStruct $job
     * @param list<int|null> $users_mentioned_id
     *
     * @return UserStruct[]
     * @throws ReflectionException
     * @throws PDOException
     * @throws Exception
     */
    private function resolveUsers(CommentStruct $comment, JobStruct $job, array $users_mentioned_id): array
    {
        $commentDao = new CommentDao(Database::obtain());
        $result = $commentDao->getThreadContributorUids($comment);

        $userDao = new UserDao(Database::obtain());
        $users = $userDao->getByUids($result);

        if ($job->id !== null) {
            $userDao->setCacheTTL(60 * 60 * 24);
            $owner = $userDao->getProjectOwner($job->id);

            if (!empty($owner->uid) and !empty($owner->email)) {
                $users[] = $owner;
            }
        }

        $userDao->setCacheTTL(60 * 10);
        $assignee = $userDao->getProjectAssignee($job->id_project);
        if (!empty($assignee->uid) && !empty($assignee->email)) {
            $users[] = $assignee;
        }

        return $this->filterUsers($users, $users_mentioned_id);
    }

    /**
     * @param CommentStruct $comment
     * @param int $id_project
     * @param string $id_job
     * @param string $id_client
     *
     * @return void
     * @throws ReflectionException
     * @throws DomainException
     * @throws Exception
     * @throws \Stomp\Exception\ConnectionException
     */
    private function enqueueComment(CommentStruct $comment, int $id_project, string $id_job, string $id_client): void
    {
        $message = (string)json_encode([
            '_type' => 'comment',
            'data' => [
                'id_job' => $id_job,
                'passwords' => $this->getProjectPasswords($id_project),
                'id_client' => $id_client,
                'payload' => $comment
            ]
        ]);

        $queueHandler = new AMQHandler();
        $queueHandler->publishToNodeJsClients(AppConfig::$SOCKET_NOTIFICATIONS_QUEUE_NAME, new Message($message));
    }

    /**
     * @param int $id_project
     *
     * @return ShapelessConcreteStruct[]
     * @throws ReflectionException
     * @throws Exception
     */
    private function projectData(int $id_project): array
    {
        return (new ProjectDao())->setCacheTTL(60 * 60)->getProjectData($id_project);
    }

    /**
     * @param int $id_project
     *
     * @return list<string>
     * @throws ReflectionException
     * @throws DomainException
     * @throws Exception
     */
    private function getProjectPasswords(int $id_project): array
    {
        $pws = [];

        foreach ($this->projectData($id_project) as $chunk) {
            if (!isset($chunk['jpassword'])) {
                throw new DomainException('Project password not found for project chunk.');
            }

            $pws[] = $chunk['jpassword'];
        }

        return $pws;
    }

    /**
     * @param string $id_job
     * @param string $id_client
     * @param int $id_project
     * @param int $id
     * @param int $idSegment
     * @param string $sourcePage
     *
     * @throws ReflectionException
     * @throws DomainException
     * @throws Exception
     * @throws \Stomp\Exception\ConnectionException
     */
    private function enqueueDeleteCommentMessage(
        string $id_job,
        string $id_client,
        int $id_project,
        int $id,
        int $idSegment,
        string $sourcePage
    ): void {
        $message = (string)json_encode([
            '_type' => 'comment',
            'data' => [
                'id_job' => $id_job,
                'passwords' => $this->getProjectPasswords($id_project),
                'id_client' => $id_client,
                'payload' => [
                    'message_type' => "2",
                    'id' => (int)$id,
                    'id_segment' => $idSegment,
                    'source_page' => $sourcePage,
                ]
            ]
        ]);

        $queueHandler = new AMQHandler();
        $queueHandler->publishToNodeJsClients(AppConfig::$SOCKET_NOTIFICATIONS_QUEUE_NAME, new Message($message));
    }

    /**
     * @param CommentStruct $comment
     * @param JobStruct $job
     * @param UserStruct[] $users
     * @param UserStruct[] $users_mentioned
     *
     * @return void
     * @throws ReflectionException
     * @throws Exception
     */
    private function sendEmail(CommentStruct $comment, JobStruct $job, array $users, array $users_mentioned): void
    {
        $jobUrlStruct = JobUrlBuilder::createFromJobStruct($job, [
            'id_segment' => $comment->id_segment,
            'skip_check_segment' => true
        ]);

        if ($jobUrlStruct === null) {
            $this->response->code(404);

            $this->response->json([
                "code" => -10,
                "message" => "No valid url was found for this project."
            ]);

            return;
        }

        $url = $jobUrlStruct->getUrlByRevisionNumber($comment->revision_number);

        if (!$url) {
            $this->response->code(404);

            $this->response->json([
                "code" => -10,
                "message" => "No valid url was found for this project."
            ]);

            return;
        }

        $project_data = $this->projectData($job->id_project);

        foreach ($users_mentioned as $user_mentioned) {
            $email = new CommentMentionEmail($user_mentioned, $comment, $url, $project_data[0], $job);
            $email->send();
        }

        foreach ($users as $user) {
            if ($comment->message_type == CommentDao::TYPE_RESOLVE) {
                $email = new CommentResolveEmail($user, $comment, $url, $project_data[0], $job);
            } else {
                $email = new CommentEmail($user, $comment, $url, $project_data[0], $job);
            }

            $email->send();
        }
    }
}
