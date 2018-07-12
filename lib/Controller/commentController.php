<?php

use DataAccess\ShapelessConcreteStruct;

class commentController extends ajaxController {

    protected $id_segment;

    private $__postInput = null;

    private $job ;

    private $struct ;
    private $new_record ;
    private $current_user ;
    private $project_data ;

    public function __destruct() {
    }

    public function __construct() {
        parent::__construct();

        $filterArgs = array(
            '_sub'        => array( 'filter' => FILTER_SANITIZE_STRING ),
            'id_client'   => array( 'filter' => FILTER_SANITIZE_STRING ),
            'id_job'      => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'id_segment'  => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'username'    => array( 'filter' => FILTER_SANITIZE_STRING ),
            'source_page'   => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'message'     => array( 'filter' => FILTER_UNSAFE_RAW ),
            'first_seg'   => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'last_seg'    => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'password'    => array(
                'filter' => FILTER_SANITIZE_STRING,
                'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            ),
        );

        $this->__postInput = filter_input_array( INPUT_POST, $filterArgs );
        $this->__postInput['message'] = htmlspecialchars( $this->__postInput['message'] );

    }

    public function doAction() {

        $this->job = Jobs_JobDao::getByIdAndPassword( $this->__postInput[ 'id_job' ], $this->__postInput['password'], 60 * 60 * 24 );

        if( empty( $this->job ) ){
            $this->result['errors'][] = array("code" => -10, "message" => "wrong password");
            return;
        }

        $this->readLoginInfo() ;
        if ( $this->userIsLogged ) {
            $this->loadUser();
        }

        $this->route();

    }

    private function route() {
        switch( $this->__postInput['_sub'] ) {
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
            $this->result[ 'errors' ][ ] = array(
                "code" => -127, "message" => "Unable to route action." );
        }
    }

    private function getRange() {
        $this->struct = new Comments_CommentStruct() ;
        $this->struct->id_job        = $this->__postInput[ 'id_job' ] ;
        $this->struct->first_segment = $this->__postInput[ 'first_seg' ] ;
        $this->struct->last_segment  = $this->__postInput[ 'last_seg' ] ;

        $commentDao = new Comments_CommentDao( Database::obtain() );

        $this->result[ 'data' ][ 'entries' ] = array(
            'comments'    => $commentDao->getCommentsInJob( $this->struct )
        );
        $this->appendUser();
    }

    private function resolve() {
        $this->prepareCommentData();

        $commentDao = new Comments_CommentDao( Database::obtain() );
        $this->new_record = $commentDao->resolveThread( $this->struct );

        $this->enqueueComment();
        $this->sendEmail();
        $this->result[ 'data' ][ 'entries' ] = array( $this->payload );
    }

    private function appendUser() {
        if ( $this->userIsLogged ) {
            $this->result[ 'data' ][ 'user' ] = array(
                'full_name' => $this->current_user->fullName()
            );
        }
    }

    private function create() {
        $this->prepareCommentData();

        $commentDao = new Comments_CommentDao( Database::obtain() );
        $this->new_record = $commentDao->saveComment( $this->struct );

        $this->enqueueComment();
        $this->sendEmail();
        $this->result[ 'data' ][ 'entries' ] = array( $this->payload ) ;
        $this->appendUser();
    }

    private function prepareCommentData() {
        $this->struct = new Comments_CommentStruct() ;

        $this->struct->id_segment = $this->__postInput[ 'id_segment' ];
        $this->struct->id_job     = $this->__postInput[ 'id_job' ];
        $this->struct->full_name  = $this->__postInput[ 'username' ];
        $this->struct->source_page  = $this->__postInput[ 'source_page' ];
        $this->struct->message    = $this->__postInput[ 'message' ];
        $this->struct->email      = $this->getEmail();
        $this->struct->uid        = $this->getUid();
    }

    private function sendEmail() {
        // TODO: fix this, replace the need for referer with a server side
        // function to build translate or revise paths based on job and
        // segmnt ids.

        if (empty($_SERVER['HTTP_REFERER'])) {
            Log::doLog('Skipping email due to missing referrer link');
            return;
        }
        @list($url, $anchor) = explode('#', $_SERVER['HTTP_REFERER']);
        $url .= '#' . $this->struct->id_segment ;
        Log::doLog($url);

        $users = $this->resolveUsers();
        $project_data = $this->projectData(); 

        foreach($users as $user) {
            $email = new Comments_CommentEmail($user, $this->struct, $url,
                $project_data[0]['name']
            );
            $email->deliver();
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

        return $this->project_data ;
    }

    private function resolveUsers() {
        $commentDao = new Comments_CommentDao( Database::obtain() );
        $result = $commentDao->getThreadContributorUids( $this->struct );

        $userDao = new Users_UserDao( Database::obtain() );
        $users = $userDao->getByUids( $result );
        $owner = $userDao->getProjectOwner( $this->job['id'] );

        if ( !empty( $owner->uid ) && !empty( $owner->email ) ) {
            array_push( $users, $owner );
        }

        $assignee = $userDao->getProjectAssignee( $this->job[ 'id_project' ] );
        if ( !empty( $assignee->uid ) && !empty( $assignee->email ) ) {
            array_push( $users, $assignee );
        }

        $userIsLogged = $this->userIsLogged ;
        $current_uid = $this->current_user->uid ;

        // find deep duplicates
        $uidSentList = array();
        $users = array_filter($users, function($item) use ( $userIsLogged, $current_uid, &$uidSentList ) {
            if ( $userIsLogged && $current_uid == $item->uid ) {
                return false;
            }

            // find deep duplicates
            if ( array_search( $item->uid, $uidSentList ) !== false ) {
                return false;
            }
            $uidSentList[] = $item->uid;
            return true ;

        });

        return $users;
    }

    private function getEmail() {
        if ( $this->userIsLogged ) {
            return $this->current_user->email ;
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
            $this->current_user->email == $this->job['owner'] ;
    }

    private function loadUser() {
        $userStruct = new Users_UserStruct();
        $userStruct->uid = $this->user->uid;

        $userDao = new Users_UserDao( Database::obtain() ) ;
        $result = $userDao->read( $userStruct );

        if ( empty($result) ) {
            throw new Exception( "User not found by UID." );
        }

        $this->current_user = $result[0] ;
    }

    private function enqueueComment() {
        $this->payload = array(
            'message_type'   => $this->new_record->message_type,
            'message'        => $this->new_record->message,
            'id_segment'     => $this->new_record->id_segment,
            'full_name'      => $this->new_record->full_name,
            'email'          => $this->new_record->email,
            'source_page'    => $this->new_record->source_page,
            'formatted_date' => $this->new_record->getFormattedDate(),
            'thread_id'      => $this->new_record->thread_id,
            'timestamp'      => (int) $this->new_record->timestamp,
        ) ;

        $message = json_encode( array(
            '_type' => 'comment',
            'data' => array(
                'id_job'    => $this->__postInput['id_job'],
                'passwords'  => $this->getProjectPasswords(),
                'id_client' => $this->__postInput['id_client'],
                'payload'   => $this->payload
            )
        ));

        $stomp = new Stomp( INIT::$QUEUE_BROKER_ADDRESS );
        $stomp->connect();
        $stomp->send( INIT::$SSE_COMMENTS_QUEUE_NAME,
            $message,
            array( 'persistent' => 'true' )
        );
    }

    private function getProjectPasswords() {
        $pws = array();
        foreach($this->projectData() as $chunk) {
            array_push( $pws, $chunk['jpassword'] );
        }
        return $pws;
    }

}

?>
