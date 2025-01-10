<?php

use DataAccess\ShapelessConcreteStruct;
use Email\CommentEmail;
use Email\CommentMentionEmail;
use Email\CommentResolveEmail;
use Stomp\Transport\Message;
use Teams\MembershipDao;
use Url\JobUrlBuilder;

class commentController extends ajaxController {

    protected array $payload;
    protected array $users_mentioned;
    protected array $users_mentioned_id;
    protected array $users;

    private array $__postInput;

    private Jobs_JobStruct $job;

    /**
     * @var Comments_CommentStruct
     */
    private Comments_CommentStruct $comment_struct;
    private Comments_CommentStruct $new_record;
    /**
     * @var ShapelessConcreteStruct[]
     */
    private array $project_data = [];

    public function __destruct() {
    }

    public function __construct() {
        parent::__construct();

        $filterArgs = [
                '_sub'            => [ 'filter' => FILTER_SANITIZE_STRING ],
                'id_client'       => [ 'filter' => FILTER_SANITIZE_STRING ],
                'id_job'          => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'id_segment'      => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'username'        => [ 'filter' => FILTER_SANITIZE_STRING ],
                'source_page'     => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'is_anonymous'    => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'revision_number' => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'message'         => [ 'filter' => FILTER_UNSAFE_RAW ],
                'id_comment'      => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password'        => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
        ];

        $this->__postInput              = filter_input_array( INPUT_POST, $filterArgs );
        $this->__postInput[ 'message' ] = htmlspecialchars( $this->__postInput[ 'message' ] );

        $this->__postInput[ 'id_comment' ]      = (int)$this->__postInput[ 'id_comment' ];
        $this->__postInput[ 'id_segment' ]      = (int)$this->__postInput[ 'id_segment' ];
        $this->__postInput[ 'id_job' ]          = (int)$this->__postInput[ 'id_job' ];
        $this->__postInput[ 'source_page' ]     = (int)$this->__postInput[ 'source_page' ];
        $this->__postInput[ 'revision_number' ] = (int)$this->__postInput[ 'revision_number' ];
        $this->__postInput[ 'source_page' ]     = (int)$this->__postInput[ 'source_page' ];

    }

    /**
     * @throws Exception
     */
    public function doAction() {

        $this->job = Jobs_JobDao::getByIdAndPassword( $this->__postInput[ 'id_job' ], $this->__postInput[ 'password' ], 60 * 60 * 24 );

        if ( empty( $this->job ) ) {
            $this->result[ 'errors' ][] = [ "code" => -10, "message" => "wrong password" ];

            return;
        }

        $this->route();

    }

    /**
     * @throws ReflectionException
     */
    private function route(): void {
        switch ( $this->__postInput[ '_sub' ] ) {
            case 'getRange':
                $this->getRange();
                break;
            case 'resolve':
                $this->resolve();
                break;
            case 'create':
                $this->create();
                break;
            case 'delete':
                $this->delete();
                break;
            default:
                $this->result[ 'errors' ][] = [
                        "code" => -127, "message" => "Unable to route action."
                ];
        }
    }

    private function getRange(): void {
        $this->comment_struct         = new Comments_CommentStruct();
        $this->comment_struct->id_job = $this->__postInput[ 'id_job' ];

        $commentDao = new Comments_CommentDao( Database::obtain() );

        $this->result[ 'data' ][ 'entries' ] = [
                'comments' => $commentDao->getCommentsForChunk( $this->job )
        ];
        $this->appendUser();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    private function resolve(): void {
        $this->prepareCommentData();

        $commentDao       = new Comments_CommentDao( Database::obtain() );
        $this->new_record = $commentDao->resolveThread( $this->comment_struct );

        $this->enqueueComment();
        $this->users = $this->resolveUsers();
        $this->sendEmail();
        $this->result[ 'data' ][ 'entries' ][ 'comments' ][] = $this->payload;
    }

    private function appendUser() {
        if ( $this->userIsLogged ) {
            $this->result[ 'data' ][ 'user' ] = [
                    'full_name' => $this->user->fullName()
            ];
        }
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    private function create(): void {
        $this->prepareCommentData();

        $commentDao       = new Comments_CommentDao( Database::obtain() );
        $this->new_record = $commentDao->saveComment( $this->comment_struct );

        foreach ( $this->users_mentioned as $user_mentioned ) {
            $mentioned_comment = $this->prepareMentionCommentData( $user_mentioned );
            $commentDao->saveComment( $mentioned_comment );
        }

        $this->enqueueComment();
        $this->users = $this->resolveUsers();
        $this->sendEmail();
        $this->result[ 'data' ][ 'entries' ][ 'comments' ][] = $this->new_record;
        $this->appendUser();
    }

    /**
     * @throws ReflectionException
     */
    private function prepareCommentData(): void {
        $this->comment_struct = new Comments_CommentStruct();

        $this->comment_struct->id_segment      = $this->__postInput[ 'id_segment' ];
        $this->comment_struct->id_job          = $this->__postInput[ 'id_job' ];
        $this->comment_struct->full_name       = $this->__postInput[ 'username' ];
        $this->comment_struct->source_page     = $this->__postInput[ 'source_page' ];
        $this->comment_struct->message         = $this->__postInput[ 'message' ];
        $this->comment_struct->revision_number = $this->__postInput[ 'revision_number' ];
        $this->comment_struct->is_anonymous    = $this->__postInput[ 'is_anonymous' ];
        $this->comment_struct->email           = $this->getEmail();
        $this->comment_struct->uid             = $this->getUid();

        $user_mentions            = $this->resolveUserMentions();
        $user_team_mentions       = $this->resolveTeamMentions();
        $userDao                  = new Users_UserDao( Database::obtain() );
        $this->users_mentioned_id = array_unique( array_merge( $user_mentions, $user_team_mentions ) );

        $this->users_mentioned = $this->filterUsers( $userDao->getByUids( $this->users_mentioned_id ) );
    }

    private function prepareMentionCommentData( Users_UserStruct $user ): Comments_CommentStruct {
        $struct = new Comments_CommentStruct();

        $struct->id_segment   = $this->__postInput[ 'id_segment' ];
        $struct->id_job       = $this->__postInput[ 'id_job' ];
        $struct->full_name    = $user->fullName();
        $struct->source_page  = $this->__postInput[ 'source_page' ];
        $struct->message      = "";
        $struct->message_type = Comments_CommentDao::TYPE_MENTION;
        $struct->email        = $user->getEmail();
        $struct->uid          = $user->getUid();

        return $struct;
    }

    /**
     * Permanently delete a comment
     * @throws ReflectionException
     */
    private function delete(): void {

        if ( !$this->isLoggedIn() ) {
            $this->result[ 'errors' ][] = [
                    "code"    => -201,
                    "message" => "You MUST log in to delete a comment."
            ];

            return;
        }

        if ( !isset( $this->__postInput[ 'id_comment' ] ) ) {
            $this->result[ 'errors' ][] = [
                    "code"    => -200,
                    "message" => "Id comment not provided."
            ];

            return;
        }

        $idComment  = $this->__postInput[ 'id_comment' ];
        $commentDao = new Comments_CommentDao( Database::obtain() );
        $comment    = $commentDao->getById( $idComment );

        if ( null === $comment ) {
            $this->result[ 'errors' ][] = [
                    "code"    => -202,
                    "message" => "Comment not found."
            ];

            return;
        }

        if ( $comment->uid === null ) {
            $this->result[ 'errors' ][] = [
                    "code"    => -203,
                    "message" => "Anonymous comments cannot be deleted."
            ];

            return;
        }

        if ( $comment->uid !== $this->user->uid ) {
            $this->result[ 'errors' ][] = [
                    "code"    => -203,
                    "message" => "You are not the author of the comment."
            ];

            return;
        }

        if ( $comment->id_segment !== $this->__postInput[ 'id_segment' ] ) {
            $this->result[ 'errors' ][] = [
                    "code"    => -204,
                    "message" => "Not corresponding id segment."
            ];

            return;
        }

        $segments    = $commentDao->getBySegmentId( $comment->id_segment );
        $lastSegment = end( $segments );

        if ( $lastSegment->id !== $this->__postInput[ 'id_comment' ] ) {
            $this->result[ 'errors' ][] = [
                    "code"    => -205,
                    "message" => "Only the last element comment can be deleted."
            ];

            return;
        }

        if ( $comment->id_job !== $this->__postInput[ 'id_job' ] ) {
            $this->result[ 'errors' ][] = [
                    "code"    => -206,
                    "message" => "Not corresponding id job."
            ];

            return;
        }

        // Fix for R2
        // The comments from R2 phase are wrongly saved with source_page = 2
        $sourcePage = Utils::getSourcePageFromReferer();

        $allowedSourcePages   = [];
        $allowedSourcePages[] = $this->__postInput[ 'source_page' ];

        if ( $sourcePage == 3 ) {
            $allowedSourcePages[] = 2;
        }

        if ( !in_array( $comment->source_page, $allowedSourcePages ) ) {
            $this->result[ 'errors' ][] = [
                    "code"    => -207,
                    "message" => "Not corresponding source_page."
            ];

            return;
        }

        if ( $commentDao->deleteComment( $comment->toCommentStruct() ) ) {

            $this->enqueueDeleteCommentMessage( $comment->id, $comment->id_segment, $this->__postInput[ 'source_page' ] );

            $this->result[ 'data' ][] = [
                    "id" => $comment->id
            ];
            $this->appendUser();

            return;
        }

        $this->result[ 'errors' ][] = [
                "code"    => -220,
                "message" => "Error when deleting a comment."
        ];
    }

    /**
     * @throws Exception
     */
    private function sendEmail() {

        $jobUrlStruct = JobUrlBuilder::createFromJobStruct( $this->job, [
                'id_segment'         => $this->comment_struct->id_segment,
                'skip_check_segment' => true
        ] );

        $url = $jobUrlStruct->getUrlByRevisionNumber( $this->comment_struct->revision_number );

        if ( !$url ) {
            $this->result[ 'errors' ][] = [ "code" => -10, "message" => "No valid url was found for this project." ];

            return;
        }

        Log::doJsonLog( $url );
        $project_data = $this->projectData();

        foreach ( $this->users_mentioned as $user_mentioned ) {
            $email = new CommentMentionEmail( $user_mentioned, $this->comment_struct, $url, $project_data[ 0 ], $this->job );
            $email->send();
        }

        foreach ( $this->users as $user ) {
            if ( $this->comment_struct->message_type == Comments_CommentDao::TYPE_RESOLVE ) {
                $email = new CommentResolveEmail( $user, $this->comment_struct, $url, $project_data[ 0 ], $this->job );
            } else {
                $email = new CommentEmail( $user, $this->comment_struct, $url, $project_data[ 0 ], $this->job );
            }

            $email->send();
        }
    }

    /**
     * @throws ReflectionException
     */
    private function projectData(): array {
        if ( $this->project_data == null ) {

            // FIXME: this is not optimal, should return just one record, not an array of records.
            /**
             * @var $projectData ShapelessConcreteStruct[]
             */
            $this->project_data = ( new Projects_ProjectDao() )->setCacheTTL( 60 * 60 )->getProjectData( $this->job[ 'id_project' ] );

        }

        return $this->project_data;
    }

    /**
     * @throws ReflectionException
     */
    private function resolveUsers(): array {
        $commentDao = new Comments_CommentDao( Database::obtain() );
        $result     = $commentDao->getThreadContributorUids( $this->comment_struct );

        $userDao = new Users_UserDao( Database::obtain() );
        $users   = $userDao->getByUids( $result );
        $userDao->setCacheTTL( 60 * 60 * 24 );
        $owner = $userDao->getProjectOwner( $this->job[ 'id' ] );

        if ( !empty( $owner->uid ) && !empty( $owner->email ) ) {
            $users[] = $owner;
        }

        $userDao->setCacheTTL( 60 * 10 );
        $assignee = $userDao->getProjectAssignee( $this->job[ 'id_project' ] );
        if ( !empty( $assignee->uid ) && !empty( $assignee->email ) ) {
            $users[] = $assignee;
        }

        return $this->filterUsers( $users, $this->users_mentioned_id );

    }

    /**
     * @return int[]
     */
    private function resolveUserMentions(): array {
        return Comments_CommentDao::getUsersIdFromContent( $this->comment_struct->message );
    }

    /**
     * @return int[]
     * @throws ReflectionException
     */
    private function resolveTeamMentions(): array {
        $users = [];

        if ( strstr( $this->comment_struct->message, "{@team@}" ) ) {
            $project     = $this->job->getProject();
            $memberships = ( new MembershipDao() )->setCacheTTL( 60 * 60 * 24 )->getMemberListByTeamId( $project->id_team, false );
            foreach ( $memberships as $membership ) {
                $users[] = $membership->uid;
            }
        }

        return $users;
    }

    /**
     * @param Users_UserStruct[] $users
     * @param array              $uidSentList
     *
     * @return array
     */
    private function filterUsers( array $users, array $uidSentList = [] ): array {
        $userIsLogged = $this->userIsLogged;
        $current_uid  = $this->user ? $this->user->uid : 0;

        // find deep duplicates
        return array_filter( $users, function ( $item ) use ( $userIsLogged, $current_uid, &$uidSentList ) {
            if ( $userIsLogged && $current_uid == $item->uid ) {
                return false;
            }

            // find deep duplicates
            if ( in_array( $item->uid, $uidSentList ) ) {
                return false;
            }
            $uidSentList[] = $item->uid;

            return true;

        } );
    }

    private function getEmail(): ?string {
        if ( $this->userIsLogged ) {
            return $this->user->email;
        } else {
            return null;
        }
    }

    private function getUid(): ?int {
        if ( $this->userIsLogged ) {
            return $this->user->uid;
        } else {
            return null;
        }
    }

    /**
     * @param int $id
     * @param int $idSegment
     * @param int $sourcePage
     *
     * @throws ReflectionException
     */
    private function enqueueDeleteCommentMessage( int $id, int $idSegment, int $sourcePage ) {
        $message = json_encode( [
                '_type' => 'comment',
                'data'  => [
                        'id_job'    => (int)$this->__postInput[ 'id_job' ],
                        'passwords' => $this->getProjectPasswords(),
                        'id_client' => $this->__postInput[ 'id_client' ],
                        'payload'   => [
                                'message_type' => 2,
                                'id'           => $id,
                                'id_segment'   => $idSegment,
                                'source_page'  => $sourcePage,
                        ]
                ]
        ] );

        $queueHandler = new AMQHandler();
        $queueHandler->publishToNodeJsClients( INIT::$SOCKET_NOTIFICATIONS_QUEUE_NAME, new Message( $message ) );

    }

    /**
     * @throws ReflectionException
     */
    private function enqueueComment() {

        $message = json_encode( [
                '_type' => 'comment',
                'data'  => [
                        'id_job'    => $this->__postInput[ 'id_job' ],
                        'passwords' => $this->getProjectPasswords(),
                        'id_client' => $this->__postInput[ 'id_client' ],
                        'payload'   => $this->new_record
                ]
        ] );

        $queueHandler = new AMQHandler();
        $queueHandler->publishToNodeJsClients( INIT::$SOCKET_NOTIFICATIONS_QUEUE_NAME, new Message( $message ) );

    }

    /**
     * @return string[]
     * @throws ReflectionException
     */
    private function getProjectPasswords(): array {
        $pws = [];
        foreach ( $this->projectData() as $chunk ) {
            $pws[] = $chunk[ 'jpassword' ];
        }

        return $pws;
    }

}
