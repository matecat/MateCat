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

    public function __destruct() {
    }

    public function __construct() {
        Log::$fileName = 'comments.log';

        parent::__construct();

        $filterArgs = array(
            '_sub'        => array( 'filter' => FILTER_SANITIZE_STRING ),
            'id_client'   => array( 'filter' => FILTER_SANITIZE_STRING ),
            'id_job'      => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'id_segment'  => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'username'    => array( 'filter' => FILTER_SANITIZE_STRING ),
            'user_role'   => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'message'     => array( 'filter' => FILTER_SANITIZE_STRING ),
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

        $pCheck = new AjaxPasswordCheck();
        //check for Password correctness
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
                "code" => -127, "message" => "No valid action provided." );
        }
    }

    private function getRange() {
        $openComments = getOpenCommentsInJob(
            $this->__postInput[ 'id_job' ] ,
            $this->__postInput[ 'first_seg' ],
            $this->__postInput[ 'last_seg' ]
        );

        $comments = getCommentsBySegmentsRange(
            $this->__postInput[ 'id_job' ],
            $this->__postInput[ 'first_seg' ],
            $this->__postInput[ 'last_seg' ]
        );

        $this->result[ 'data' ]['open_comments'] = $openComments ;
        $this->result[ 'data' ]['current_comments'] = $comments ;
    }

    private function resolve() {
        $this->prepareCommentData();
        $this->validateInput();

        $this->commentData['message_type'] = '2' ; // resolve

        $this->writeResolve();

        $this->enqueueComment();
        if ($this->loggedIn()) {
            $this->sendEmail();
        }

        $this->result[ 'data' ][ ] = $this->commentData ;
    }

    private function create() {
        $this->prepareCommentData();
        $this->validateInput();

        $this->commentData['message_type'] = '1' ; // comment
        $this->commentData['formatted_date'] = strftime('%l:%M %p %e %b %Y');

        if ( $this->processComment() ) {
            array_push( $this->result[ 'data' ], $this->commentData );
        } else {
            // TODO: handle error
            $this->result[ 'errors' ][ ] = array(
                "code" => -127, "message" => "Error on comment create."
            ) ;
        }
    }

    private function prepareCommentData() {
        $this->commentData = array(
            'id_segment' => $this->__postInput[ 'id_segment' ],
            'id_job'     => $this->__postInput[ 'id_job' ],
            'full_name'  => $this->__postInput[ 'username' ],
            'id_client'  => $this->__postInput[ 'id_client' ],
            'user_role'  => $this->__postInput[ 'user_role' ],
            'message'    => $this->__postInput[ 'message' ],
            'password'   => $this->__postInput[ 'password' ],
        );
    }

    private function processComment() {
        // TODO: move this in a separate class
        $db = Database::obtain();

        try {
            // $db->begin();
            $this->writeComment();
            $this->enqueueComment();
            if ($this->loggedIn()) {
                $this->sendEmail();
            }
            return true;
            // $db->commit();
        } catch (Exception $e) {
            Log::doLog($e->getMessage()) ;
            // $db->rollback();
            return false;
        }
    }

    private function writeComment() {
        $dataForDb = $this->commentData ;

        $dataForDb['uid'] = $this->getUid();
        $dataForDb['email'] = $this->getEmail();

        insertCommentRecord($dataForDb);
    }

    private function writeResolve() {

        $dataForDb = $this->commentData ;
        $dataForDb['uid'] = $this->getUid();
        $dataForDb['email'] = $this->getEmail();

        Log::doLog( $dataForDb ) ;
        $this->commentData['thread_id'] = resolveCommentThread($dataForDb);
    }

    private function sendEmail() {

    }

    private function getMessageType() {
        return null;
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

    private function enqueueComment() {
        $message = json_encode( array(
            '_type' => 'comment',
            'data' => array(
                'id_job'    => $this->commentData['id_job'],
                'password'  => $this->commentData['password'],
                'id_client' => $this->commentData['id_client'],
                'payload' => array(
                    'message_type'   => $this->commentData['message_type'],
                    'message'        => $this->commentData['message'],
                    'id_segment'     => $this->commentData['id_segment'],
                    'full_name'      => $this->commentData['full_name'],
                    'user_role'      => $this->commentData['user_role'],
                    'formatted_date' => $this->commentData['formatted_date'],
                    'thread_id'      => $this->commentData['thread_id'],
                )
            )
        ) );

        $stomp = new Stomp( INIT::$QUEUE_BROKER_ADDRESS );

        $connect = $stomp->connect();

        $end = $stomp->send( INIT::$SSE_COMMENTS_QUEUE_NAME,
            $message,
            array( 'persistent' => 'true' )
        );

        Log::doLog( "sent message: ", $end );
    }

    private function loggedIn() {
        // TODO:
        return false ;
    }

    private function validateInput() {
        // TODO: validate
        // - presence of required attributes
        // - role code to be correct
    }
}

?>
