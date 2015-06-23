<?

class commentController extends ajaxController {

    protected $id_segment;

    private $__postInput = null;
    private $id_job;
    private $password = false;
    private $username ;
    private $id_client ;
    private $role ;
    private $message ;

    private $struct ;
    private $new_record ;
    private $current_user ;

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
            'user_role'   => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'message'     => array( 'filter' => FILTER_SANITIZE_SPECIAL_CHARS ),
            'first_seg'   => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'last_seg'    => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'password'    => array(
                'filter' => FILTER_SANITIZE_STRING,
                'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            ),
        );

        $this->__postInput = filter_input_array( INPUT_POST, $filterArgs );

    }

    public function doAction() {
        $job_data = getJobData( $this->__postInput[ 'id_job' ] );

        $this->checkLogin() ;
        if ( $this->userIsLogged ) {
            $this->loadUser();
        }

        $pCheck = new AjaxPasswordCheck();
        if( !$pCheck->grantJobAccessByJobData( $job_data, $this->__postInput[ 'password' ] ) ){
            $this->result['errors'][] = array("code" => -10, "message" => "wrong password");
            return;
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
            'open_comments'    => $commentDao->getOpenCommentsInJob( $this->struct ),
            'current_comments' => $commentDao->getCommentsBySegmentsRange( $this->struct )
        );
        $this->appendUser();
    }

    private function resolve() {
        $this->prepareCommentData();

        $commentDao = new Comments_CommentDao( Database::obtain() );

        $this->new_record = $commentDao->resolveThread( $this->struct );

        $this->enqueueComment();

        if ( $this->userIsLogged ) {
            $this->sendEmail();
        }

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

        if ( $this->userIsLogged ) {
            $this->sendEmail();
        }

        $this->result[ 'data' ][ 'entries' ] = array( $this->payload ) ;

        $this->appendUser();
    }

    private function prepareCommentData() {
        $this->struct = new Comments_CommentStruct() ;

        $this->struct->id_segment = $this->__postInput[ 'id_segment' ];
        $this->struct->id_job     = $this->__postInput[ 'id_job' ];
        $this->struct->full_name  = $this->__postInput[ 'username' ];
        $this->struct->user_role  = $this->__postInput[ 'user_role' ];
        $this->struct->message    = $this->__postInput[ 'message' ];
        $this->struct->email      = $this->getEmail();
        $this->struct->uid        = $this->getUid();
    }

    private function sendEmail() {

    }

    private function getEmail() {
        return null;
    }

    private function getUid() {
        return null;
    }

    private function getResolveDate() {
        return null;
    }

    private function loadUser() {
        $userStruct = new Users_UserStruct();
        $userStruct->uid = $this->uid;

        $userDao = new Users_UserDao( Database::obtain() ) ;
        $result = $userDao->read( $userStruct );

        if ( empty($result) ) {
            throw new Exception( "User not found by UID." );
        }

        $this->current_user = $result[0] ;
        error_log( '@@ current_user ' . $this->current_user->first_name );
    }

    private function enqueueComment() {
        $this->payload = array(
            'message_type'   => $this->new_record->message_type,
            'message'        => $this->new_record->message,
            'id_segment'     => $this->new_record->id_segment,
            'full_name'      => $this->new_record->full_name,
            'user_role'      => $this->new_record->user_role,
            'formatted_date' => $this->new_record->getFormattedDate(),
            'thread_id'      => $this->new_record->thread_id,
        ) ;

        $message = json_encode( array(
            '_type' => 'comment',
            'data' => array(
                'id_job'    => $this->__postInput['id_job'],
                'password'  => $this->__postInput['password'],
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

}

?>
