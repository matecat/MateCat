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
            'role'        => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'message'     => array( 'filter' => FILTER_SANITIZE_STRING ),
            'password'    => array(
                'filter' => FILTER_SANITIZE_STRING,
                'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            ),
        );

        $this->__postInput = filter_input_array( INPUT_POST, $filterArgs );

    }

    public function doAction() {
        if ($this->validateLogin()) {
            switch( $this->__postInput['_sub'] ) {
            case 'getlist':
                $this->getList();
            case 'resolve':
                $this->resolveThread();
            case 'create':
                $this->create();
                break;
            default:
                $this->result[ 'errors' ][ ] = array( "code" => -127, "message" => "No valid action provided." );
            }
        }
        else {
            // TODO: handle login failure

        }
    }

    private function getList() {
        // TODO: find segments

    }

    private function resolveThread() {
        $job_data = getJobData(
            $this->__postInput[ 'id_job' ],
            $this->__postInput[ 'password' ]
        );

        return !empty($job_data);
    }

    private function validateLogin() {
        if ( !empty($job_data) ) {
            Log::doLog($job_data);
            $this->processComment();

        } else {
            $this->result[ 'errors' ][ ] = array( "code" => -127, "message" => "Job not found." ) ;
        }

    }

    private function create() {
        $this->commentData = array(
            'id_segment' => $this->__postInput[ 'id_segment' ],
            'id_job'     => $this->__postInput[ 'id_job' ],
            'full_name'  => $this->__postInput[ 'username' ],
            'id_client'  => $this->__postInput[ 'id_client' ],
            'user_role'  => $this->__postInput[ 'role' ],
            'message'    => $this->__postInput[ 'message' ],
            'password'   => $this->__postInput[ 'password' ],
            'parsed_date' => gmdate('Y-m-d H:i:s'), // TODO: check this
        );

        $this->validateInput();
        if ( $this->processComment() ) {
            array_push( $this->result[ 'data' ], $this->commentData );
        } else {
            // TODO: handle error
            $this->result[ 'errors' ][ ] = array( "code" => -127, "message" => "Error on comment create." ) ;
        }

        // TODO: detect if user is logged in
        // TODO: validate the segment exists
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
        $dataForDb['resolve_date'] = $this->getResolveDate();
        $dataForDb['message_type'] = $this->getMessageType();

        $comment = insertComment($dataForDb);
        Log::doLog("comment insert done " . $comment);
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
                    '_type'       => 'comment',
                    'message'     => $this->commentData['message'],
                    'id_segment'  => $this->commentData['id_segment'],
                    'username'    => $this->commentData['full_name'],
                    'role'        => 'translator',
                    'parsed_date' => $this->commentData['parsed_date']
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
