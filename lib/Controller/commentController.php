<?php

use DataAccess\ShapelessConcreteStruct;
use Url\JobUrlBuilder;

class commentController extends ajaxController {

    protected $id_segment;
    protected $payload;
    protected $users_mentioned;
    protected $users_mentioned_id;
    protected $users;

    private $__postInput = null;

    private $job;

    /**
     * @var Comments_CommentStruct
     */
    private $struct;
    private $new_record;
    private $current_user;
    private $project_data;

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
                'revision_number' => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'message'         => [ 'filter' => FILTER_UNSAFE_RAW ],
                'first_seg'       => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'last_seg'        => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password'        => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
        ];

        $this->__postInput              = filter_input_array( INPUT_POST, $filterArgs );
        $this->__postInput[ 'message' ] = htmlspecialchars( $this->__postInput[ 'message' ] );

    }

    public function doAction() {

        $this->job = Jobs_JobDao::getByIdAndPassword( $this->__postInput[ 'id_job' ], $this->__postInput[ 'password' ], 60 * 60 * 24 );

        if ( empty( $this->job ) ) {
            $this->result[ 'errors' ][] = [ "code" => -10, "message" => "wrong password" ];

            return;
        }

        $this->readLoginInfo();
        if ( $this->userIsLogged ) {
            $this->loadUser();
        }

        $this->route();

    }

    private function route() {
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
            default:
                $this->result[ 'errors' ][] = [
                        "code" => -127, "message" => "Unable to route action."
                ];
        }
    }

    private function getRange() {
        $this->struct                = new Comments_CommentStruct();
        $this->struct->id_job        = $this->__postInput[ 'id_job' ];
        $this->struct->first_segment = $this->__postInput[ 'first_seg' ];
        $this->struct->last_segment  = $this->__postInput[ 'last_seg' ];

        $commentDao = new Comments_CommentDao( Database::obtain() );

        $this->result[ 'data' ][ 'entries' ] = [
                'comments' => $commentDao->getCommentsInJob( $this->struct )
        ];
        $this->appendUser();
    }

    private function resolve() {
        $this->prepareCommentData();

        $commentDao       = new Comments_CommentDao( Database::obtain() );
        $this->new_record = $commentDao->resolveThread( $this->struct );

        $this->enqueueComment();
        $this->users = $this->resolveUsers();
        $this->sendEmail();
        $this->result[ 'data' ][ 'entries' ] = [ $this->payload ];
    }

    private function appendUser() {
        if ( $this->userIsLogged ) {
            $this->result[ 'data' ][ 'user' ] = [
                    'full_name' => $this->current_user->fullName()
            ];
        }
    }

    private function create() {
        $this->prepareCommentData();

        $commentDao       = new Comments_CommentDao( Database::obtain() );
        $this->new_record = $commentDao->saveComment( $this->struct );

        foreach ( $this->users_mentioned as $user_mentioned ) {
            $mentioned_comment = $this->prepareMentionCommentData( $user_mentioned );
            $commentDao->saveComment( $mentioned_comment );
        }

        $this->enqueueComment();
        $this->users = $this->resolveUsers();
        $this->sendEmail();
        $this->result[ 'data' ][ 'entries' ] = [ $this->payload ];
        $this->appendUser();
    }

    private function prepareCommentData() {
        $this->struct = new Comments_CommentStruct();

        $this->struct->id_segment      = $this->__postInput[ 'id_segment' ];
        $this->struct->id_job          = $this->__postInput[ 'id_job' ];
        $this->struct->full_name       = $this->__postInput[ 'username' ];
        $this->struct->source_page     = $this->__postInput[ 'source_page' ];
        $this->struct->message         = $this->__postInput[ 'message' ];
        $this->struct->revision_number = $this->__postInput[ 'revision_number' ];
        $this->struct->email           = $this->getEmail();
        $this->struct->uid             = $this->getUid();

        $user_mentions            = $this->resolveUserMentions();
        $user_team_mentions       = $this->resolveTeamMentions();
        $userDao                  = new Users_UserDao( Database::obtain() );
        $this->users_mentioned_id = array_unique( array_merge( $user_mentions, $user_team_mentions ) );


        $this->users_mentioned = $this->filterUsers( $userDao->getByUids( $this->users_mentioned_id ) );
    }

    private function prepareMentionCommentData( Users_UserStruct $user ) {
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

    private function sendEmail() {

        $url = JobUrlBuilder::create( $this->job->id, $this->job->password, [
            'id_segment'      => $this->struct->id_segment,
            'revision_number' => $this->struct->revision_number
        ] );

        Log::doJsonLog( $url );
        $project_data = $this->projectData();

        foreach ( $this->users_mentioned as $user_mentioned ) {
            $email = new \Email\CommentMentionEmail( $user_mentioned, $this->struct, $url, $project_data[ 0 ], $this->job );
            $email->send();
        }

        foreach ( $this->users as $user ) {
            if ( $this->struct->message_type == Comments_CommentDao::TYPE_RESOLVE ) {
                $email = new \Email\CommentResolveEmail( $user, $this->struct, $url, $project_data[ 0 ], $this->job );
            } else {
                $email = new \Email\CommentEmail( $user, $this->struct, $url, $project_data[ 0 ], $this->job );
            }

            $email->send();
        }
    }

    private function projectData() {
        if ( $this->project_data == null ) {

            // FIXME: this is not optimal, should return just one record, not an array of records.
            /**
             * @var $projectData ShapelessConcreteStruct[]
             */
            $this->project_data = ( new \Projects_ProjectDao() )->setCacheTTL( 60 * 60 )->getProjectData( $this->job[ 'id_project' ] );

        }

        return $this->project_data;
    }

    private function resolveUsers() {
        $commentDao = new Comments_CommentDao( Database::obtain() );
        $result     = $commentDao->getThreadContributorUids( $this->struct );

        $userDao = new Users_UserDao( Database::obtain() );
        $users   = $userDao->getByUids( $result );
        $userDao->setCacheTTL( 60 * 60 * 24 );
        $owner = $userDao->getProjectOwner( $this->job[ 'id' ] );

        if ( !empty( $owner->uid ) && !empty( $owner->email ) ) {
            array_push( $users, $owner );
        }

        $userDao->setCacheTTL( 60 * 10 );
        $assignee = $userDao->getProjectAssignee( $this->job[ 'id_project' ] );
        if ( !empty( $assignee->uid ) && !empty( $assignee->email ) ) {
            array_push( $users, $assignee );
        }

        return $this->filterUsers( $users, $this->users_mentioned_id );

    }


    private function resolveUserMentions() {
        return Comments_CommentDao::getUsersIdFromContent( $this->struct->message );
    }

    private function resolveTeamMentions() {
        $users = [];

        if ( strstr( $this->struct->message, "{@team@}" ) ) {
            $project     = $this->job->getProject();
            $memberships = ( new \Teams\MembershipDao() )->setCacheTTL( 60 * 60 * 24 )->getMemberListByTeamId( $project->id_team, false );
            foreach ( $memberships as $membership ) {
                $users[] = $membership->uid;
            }
        }

        return $users;
    }

    private function filterUsers( $users, $uidSentList = [] ) {
        $userIsLogged = $this->userIsLogged;
        $current_uid  = $this->current_user->uid;

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

    private function getEmail() {
        if ( $this->userIsLogged ) {
            return $this->current_user->email;
        } else {
            return null;
        }
    }

    private function getUid() {
        if ( $this->userIsLogged ) {
            return $this->current_user->uid;
        } else {
            return null;
        }
    }

    private function isOwner() {
        return $this->userIsLogged &&
                $this->current_user->email == $this->job[ 'owner' ];
    }

    /**
     * @throws Exception
     */
    private function loadUser() {
        $userStruct      = new Users_UserStruct();
        $userStruct->uid = $this->user->uid;

        $userDao = new Users_UserDao( Database::obtain() );
        $result  = $userDao->read( $userStruct );

        if ( empty( $result ) ) {
            throw new Exception( "User not found by UID." );
        }

        $this->current_user = $result[ 0 ];
    }

    /**
     * @throws StompException
     */
    private function enqueueComment() {
        $this->payload = [
                'message_type'   => $this->new_record->message_type,
                'message'        => $this->new_record->message,
                'id_segment'     => $this->new_record->id_segment,
                'full_name'      => $this->new_record->full_name,
                'email'          => $this->new_record->email,
                'source_page'    => $this->new_record->source_page,
                'formatted_date' => $this->new_record->getFormattedDate(),
                'thread_id'      => $this->new_record->thread_id,
                'timestamp'      => (int)$this->new_record->timestamp,
        ];

        $message = json_encode( [
                '_type' => 'comment',
                'data'  => [
                        'id_job'    => $this->__postInput[ 'id_job' ],
                        'passwords' => $this->getProjectPasswords(),
                        'id_client' => $this->__postInput[ 'id_client' ],
                        'payload'   => $this->payload
                ]
        ] );

        $stomp = new Stomp( INIT::$QUEUE_BROKER_ADDRESS );
        $stomp->connect();
        $stomp->send( INIT::$SSE_NOTIFICATIONS_QUEUE_NAME,
                $message,
                [ 'persistent' => 'true' ]
        );
    }

    private function getProjectPasswords() {
        $pws = [];
        foreach ( $this->projectData() as $chunk ) {
            array_push( $pws, $chunk[ 'jpassword' ] );
        }

        return $pws;
    }

}

?>
