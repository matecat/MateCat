<?php

namespace API\App;

use AMQHandler;
use API\Commons\KleinController;
use API\Commons\Validators\LoginValidator;
use Comments_CommentDao;
use Comments_CommentStruct;
use Database;
use Email\CommentEmail;
use Email\CommentMentionEmail;
use Email\CommentResolveEmail;
use Exception;
use INIT;
use InvalidArgumentException;
use Jobs_JobDao;
use Jobs_JobStruct;
use Klein\Response;
use Log;
use Projects_ProjectDao;
use RuntimeException;
use Stomp\Transport\Message;
use Teams\MembershipDao;
use Url\JobUrlBuilder;
use Users_UserDao;
use Users_UserStruct;
use Utils;

class CommentController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    /**
     * @return Response
     * @throws \ReflectionException
     */
    public function getRange(): Response
    {
        $data = [];
        $request = $this->validateTheRequest();

        $struct                = new Comments_CommentStruct();
        $struct->id_job        = $request[ 'id_job' ];
        $struct->first_segment = $request[ 'first_seg' ];
        $struct->last_segment  = $request[ 'last_seg' ];

        $commentDao = new Comments_CommentDao( Database::obtain() );

        $data[ 'entries' ] = [
            'comments' => $commentDao->getCommentsForChunk( $request['job'] )
        ];

        $data[ 'user' ] = [
            'full_name' => $this->user->fullName()
        ];

        return $this->response->json([
            "data" => $data
        ]);
    }

    /**
     * @return Response
     * @throws \ReflectionException
     * @throws \Stomp\Exception\ConnectionException
     */
    public function resolve(): Response
    {
        try {
            $request = $this->validateTheRequest();
            $prepareCommandData = $this->prepareCommentData($request);
            $comment_struct = $prepareCommandData['struct'];
            $users_mentioned_id = $prepareCommandData['users_mentioned_id'];
            $users_mentioned = $prepareCommandData['users_mentioned'];

            $commentDao       = new Comments_CommentDao( Database::obtain() );
            $new_record = $commentDao->resolveThread( $comment_struct );

            $this->enqueueComment($new_record, $request['job']->id_project, $request['id_job'], $request['id_client']);
            $users = $this->resolveUsers($comment_struct, $request['job'], $users_mentioned_id);
            $this->sendEmail($comment_struct, $request['job'], $users, $users_mentioned);

            return $this->response->json([
                "data" => [
                    'entries' => [
                        'comments' => [$new_record]
                    ],
                    'user' => [
                        'full_name' => $this->user->fullName()
                    ]
                ]
            ]);
        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }

    /**
     * @return Response
     * @throws \ReflectionException
     * @throws \Stomp\Exception\ConnectionException
     */
    public function create(): Response
    {
        try {
            $request = $this->validateTheRequest();
            $prepareCommandData = $this->prepareCommentData($request);
            $comment_struct = $prepareCommandData['struct'];
            $users_mentioned_id = $prepareCommandData['users_mentioned_id'];
            $users_mentioned = $prepareCommandData['users_mentioned'];

            $commentDao = new Comments_CommentDao( Database::obtain() );
            $new_record = $commentDao->saveComment( $comment_struct );

            foreach ( $users_mentioned as $user_mentioned ) {
                $mentioned_comment = $this->prepareMentionCommentData($request, $user_mentioned);
                $commentDao->saveComment( $mentioned_comment );
            }

            $this->enqueueComment($new_record, $request['job']->id_project, $request['id_job'], $request['id_client']);
            $users = $this->resolveUsers($comment_struct, $request['job'], $users_mentioned_id);
            $this->sendEmail($comment_struct, $request['job'], $users, $users_mentioned);

            return $this->response->json([
                "data" => [
                    'entries' => [
                        'comments' => [$new_record]
                    ],
                    'user' => [
                        'full_name' => $this->user->fullName()
                    ]
                ]
            ]);
        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }

    /**
     * @return \Klein\Response
     * @throws \ReflectionException
     */
    public function delete(): Response
    {
        try {
            $request = $this->validateTheRequest();

            if(!isset($request['id_comment'])){
                throw new InvalidArgumentException(  "Id comment not provided.", -200);
            }

            $user = $this->user;
            $idComment = $request['id_comment'];
            $commentDao = new Comments_CommentDao( Database::obtain() );
            $comment = $commentDao->getById($idComment);

            if(null === $comment){
                throw new InvalidArgumentException( "Comment not found.", -202);
            }

            if($comment->uid === null){
                throw new InvalidArgumentException( "You are not the author of the comment.", -203);
            }

            if((int)$comment->uid !== (int)$user->uid){
                throw new InvalidArgumentException( "You are not the author of the comment.", -203);
            }

            if((int)$comment->id_segment !== (int)$request['id_segment']){
                throw new InvalidArgumentException( "Not corresponding id segment.", -204);
            }

            $segments = $commentDao->getBySegmentId($comment->id_segment);
            $lastSegment = end($segments);

            if((int)$lastSegment->id !== (int)$request['id_comment']){
                throw new InvalidArgumentException("Only the last element comment can be deleted.", -205);
            }

            if((int)$comment->id_job !== (int)$request['id_job']){
                throw new InvalidArgumentException("Not corresponding id job.", -206);
            }

            // Fix for R2
            // The comments from R2 phase are wrongly saved with source_page = 2
            $sourcePage = Utils::getSourcePageFromReferer();

            $allowedSourcePages = [];
            $allowedSourcePages[] = (int)$request['source_page'];

            if($sourcePage == 3){
                $allowedSourcePages[] = 2;
            }

            if(!in_array((int)$comment->source_page, $allowedSourcePages)){
                throw new InvalidArgumentException("Not corresponding source_page.", -207);
            }

            if($commentDao->deleteComment($comment)){

                $this->enqueueDeleteCommentMessage(
                    $request['id_job'],
                    $request['id_client'],
                    $request['job']->id_project,
                    $comment->id,
                    $comment->id_segment,
                    $request['source_page']
                );

                return $this->response->json([
                    "data" => [
                        [
                            "id" => (int)$comment->id
                        ],
                        'user' => [
                            'full_name' => $this->user->fullName()
                        ]
                    ]
                ]);
            }

            throw new RuntimeException( "Error when deleting a comment.", -220);
            
        } catch (Exception $exception){
            return $this->returnException($exception);
        }
    }

    /**
     * @return array|\Klein\Response
     * @throws \ReflectionException
     */
    private function validateTheRequest(): array
    {
        $id_client = filter_var( $this->request->param( 'id_client' ), FILTER_SANITIZE_STRING );
        $username = filter_var( $this->request->param( 'username' ), FILTER_SANITIZE_STRING );
        $id_job = filter_var( $this->request->param( 'id_job' ), FILTER_SANITIZE_NUMBER_INT );
        $id_segment = filter_var( $this->request->param( 'id_segment' ), FILTER_SANITIZE_NUMBER_INT );
        $source_page = filter_var( $this->request->param( 'source_page' ), FILTER_SANITIZE_NUMBER_INT );
        $is_anonymous = filter_var( $this->request->param( 'is_anonymous' ), FILTER_VALIDATE_BOOLEAN );
        $revision_number = filter_var( $this->request->param( 'revision_number' ), FILTER_SANITIZE_NUMBER_INT );
        $first_seg = filter_var( $this->request->param( 'first_seg' ), FILTER_SANITIZE_NUMBER_INT );
        $last_seg = filter_var( $this->request->param( 'last_seg' ), FILTER_SANITIZE_NUMBER_INT );
        $id_comment = filter_var( $this->request->param( 'id_comment' ), FILTER_SANITIZE_NUMBER_INT );
        $password = filter_var( $this->request->param( 'password' ), FILTER_SANITIZE_STRING, [ 'flags' =>  FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ] );
        $message = filter_var( $this->request->param( 'message' ), FILTER_UNSAFE_RAW );
        $message = htmlspecialchars( $message );

        $job = Jobs_JobDao::getByIdAndPassword( $id_job, $password, 60 * 60 * 24 );

        if ( empty( $job ) ) {
            throw new InvalidArgumentException(-10, "wrong password");
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
     * @param $request
     * @return array
     * @throws \ReflectionException
     */
    private function prepareCommentData($request): array
    {
        $struct = new Comments_CommentStruct();

        $struct->id_segment      = $request[ 'id_segment' ];
        $struct->id_job          = $request[ 'id_job' ];
        $struct->full_name       = $request[ 'username' ];
        $struct->source_page     = $request[ 'source_page' ];
        $struct->message         = $request[ 'message' ];
        $struct->revision_number = $request[ 'revision_number' ];
        $struct->is_anonymous    = $request[ 'is_anonymous' ];
        $struct->email           = $this->user->getEmail();
        $struct->uid             = $this->user->getUid();

        $user_mentions           = $this->resolveUserMentions($struct->message);
        $user_team_mentions      = $this->resolveTeamMentions($request['job'], $struct->message);
        $userDao                 = new Users_UserDao( Database::obtain() );
        $users_mentioned_id      = array_unique( array_merge( $user_mentions, $user_team_mentions ) );
        $users_mentioned         = $this->filterUsers( $userDao->getByUids( $users_mentioned_id ) );

        return [
            'struct' => $struct,
            'users_mentioned_id' => $users_mentioned_id,
            'users_mentioned' => $users_mentioned,
        ];
    }

    /**
     * @param $request
     * @param Users_UserStruct $user
     * @return Comments_CommentStruct
     */
    private function prepareMentionCommentData( $request, Users_UserStruct $user ): Comments_CommentStruct
    {
        $struct = new Comments_CommentStruct();

        $struct->id_segment   = $request[ 'id_segment' ];
        $struct->id_job       = $request[ 'id_job' ];
        $struct->full_name    = $user->fullName();
        $struct->source_page  = $request[ 'source_page' ];
        $struct->message      = "";
        $struct->message_type = Comments_CommentDao::TYPE_MENTION;
        $struct->email        = $user->getEmail();
        $struct->uid          = $user->getUid();

        return $struct;
    }

    /**
     * @param $message
     * @return array|mixed
     */
    private function resolveUserMentions($message)
    {
        return Comments_CommentDao::getUsersIdFromContent( $message );
    }

    /**
     * @param Jobs_JobStruct $job
     * @param $message
     * @return array
     * @throws \ReflectionException
     */
    private function resolveTeamMentions(Jobs_JobStruct $job, $message): array
    {
        $users = [];

        if ( strstr( $message, "{@team@}" ) ) {
            $project     = $job->getProject();
            $memberships = ( new MembershipDao() )->setCacheTTL( 60 * 60 * 24 )->getMemberListByTeamId( $project->id_team, false );
            foreach ( $memberships as $membership ) {
                $users[] = $membership->uid;
            }
        }

        return $users;
    }

    /**
     * @param $users
     * @param array $uidSentList
     * @return array
     */
    private function filterUsers( $users, $uidSentList = [] ): array
    {
        $userIsLogged = $this->userIsLogged;
        $current_uid  = $this->user->uid;

        // find deep duplicates
        $users = array_filter( $users, function ( $item ) use ( $userIsLogged, $current_uid, &$uidSentList ) {
            if ( $userIsLogged && $current_uid == $item->uid ) {
                return false;
            }

            // find deep duplicates
            if ( array_search( $item->uid, $uidSentList ) !== false ) {
                return false;
            }
            $uidSentList[] = $item->uid;

            return true;

        } );

        return $users;
    }

    /**
     * @param Comments_CommentStruct $comment
     * @param Jobs_JobStruct $job
     * @param $users_mentioned_id
     * @return array
     * @throws \ReflectionException
     */
    private function resolveUsers(Comments_CommentStruct $comment, Jobs_JobStruct $job, $users_mentioned_id): array
    {
        $commentDao = new Comments_CommentDao( Database::obtain() );
        $result     = $commentDao->getThreadContributorUids( $comment );

        $userDao = new Users_UserDao( Database::obtain() );
        $users   = $userDao->getByUids( $result );
        $userDao->setCacheTTL( 60 * 60 * 24 );
        $owner = $userDao->getProjectOwner( $job->id );

        if ( !empty( $owner->uid ) and !empty( $owner->email ) ) {
            array_push( $users, $owner );
        }

        $userDao->setCacheTTL( 60 * 10 );
        $assignee = $userDao->getProjectAssignee( $job->id_project );
        if ( !empty( $assignee->uid ) && !empty( $assignee->email ) ) {
            array_push( $users, $assignee );
        }

        return $this->filterUsers( $users, $users_mentioned_id );

    }

    /**
     * @param Comments_CommentStruct $comment
     * @param $id_project
     * @param $id_job
     * @param $id_client
     * @return false|string
     * @throws \Stomp\Exception\ConnectionException
     * @throws \ReflectionException
     */
    private function enqueueComment(Comments_CommentStruct $comment, $id_project, $id_job, $id_client) {

        $message = json_encode( [
            '_type' => 'comment',
            'data'  => [
                'id_job'    => $id_job,
                'passwords' => $this->getProjectPasswords($id_project),
                'id_client' => $id_client,
                'payload'   => $comment
            ]
        ] );

        $queueHandler = new AMQHandler();
        $queueHandler->publishToNodeJsClients( INIT::$SOCKET_NOTIFICATIONS_QUEUE_NAME, new Message( $message ) );

        return $message;
    }

    /**
     * @param $id_project
     * @return array|\DataAccess\ShapelessConcreteStruct[]
     * @throws \ReflectionException
     */
    private function projectData($id_project) {
        return ( new Projects_ProjectDao() )->setCacheTTL( 60 * 60 )->getProjectData( $id_project );
    }

    /**
     * @param $id_project
     * @return array
     * @throws \ReflectionException
     */
    private function getProjectPasswords($id_project) {
        $pws = [];

        foreach ( $this->projectData($id_project) as $chunk ) {
            $pws[] = $chunk[ 'jpassword' ];
        }

        return $pws;
    }

    /**
     * @param $id_job
     * @param $id_client
     * @param $id_project
     * @param $id
     * @param $idSegment
     * @param $sourcePage
     * @throws \Stomp\Exception\ConnectionException
     * @throws \ReflectionException
     */
    private function enqueueDeleteCommentMessage(
        $id_job,
        $id_client,
        $id_project,
        $id,
        $idSegment,
        $sourcePage
    )
    {
        $message = json_encode( [
            '_type' => 'comment',
            'data'  => [
                'id_job'    => $id_job,
                'passwords' => $this->getProjectPasswords($id_project),
                'id_client' => $id_client,
                'payload'   => [
                    'message_type'   => "2",
                    'id'             => (int)$id,
                    'id_segment'     => $idSegment,
                    'source_page'    => $sourcePage,
                ]
            ]
        ] );

        $queueHandler = new AMQHandler();
        $queueHandler->publishToNodeJsClients( INIT::$SOCKET_NOTIFICATIONS_QUEUE_NAME, new Message( $message ) );

    }

    /**
     * @param Comments_CommentStruct $comment
     * @param Jobs_JobStruct $job
     * @param array $users
     * @param array $users_mentioned
     * @return \Klein\Response
     * @throws \ReflectionException
     */
    private function sendEmail(Comments_CommentStruct $comment, Jobs_JobStruct $job, array $users, array $users_mentioned) {

        $jobUrlStruct = JobUrlBuilder::createFromJobStruct($job, [
            'id_segment'         => $comment->id_segment,
            'skip_check_segment' => true
        ]);

        $url = $jobUrlStruct->getUrlByRevisionNumber($comment->revision_number);

        if(!$url){
            $this->response->code(404);

            return $this->response->json([
                "code" => -10,
                "message" => "No valid url was found for this project."
            ]);
        }

        Log::doJsonLog( $url );
        $project_data = $this->projectData($job->id_project);

        foreach ( $users_mentioned as $user_mentioned ) {
            $email = new CommentMentionEmail( $user_mentioned, $comment, $url, $project_data[ 0 ], $job );
            $email->send();
        }

        foreach ( $users as $user ) {
            if ( $comment->message_type == Comments_CommentDao::TYPE_RESOLVE ) {
                $email = new CommentResolveEmail( $user, $comment, $url, $project_data[ 0 ], $job );
            } else {
                $email = new CommentEmail( $user, $comment, $url, $project_data[ 0 ], $job );
            }

            $email->send();
        }
    }
}