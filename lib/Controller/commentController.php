<?

class commentController extends ajaxController {

    private $__postInput = null;

    protected $id_segment;

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

        $this->commentData = array(
            'id_segment' => $this->__postInput[ 'id_segment' ],
            'id_job'     => $this->__postInput[ 'id_job' ],
            'full_name'  => $this->__postInput[ 'username' ],
            'id_client'  => $this->__postInput[ 'id_client' ],
            'user_role'  => $this->__postInput[ 'role' ],
            'message'    => $this->__postInput[ 'message' ],
            'password'   => $this->__postInput[ 'password' ],
        );

        $this->validateInput();
    }

    public function doAction() {
      // TODO: optimize this, optionally check the segmnt exists
      $job_data = getJobData( $this->commentData[ 'id_job' ], $this->commentData[ 'password' ] );

      if ( !empty($job_data) ) {
          Log::doLog($job_data);
          $this->processComment();
      }

      // TODO: read job id and segment id
      // TODO: detect if user is logged in
      // TODO: validate the segment exists
      // TODO: insert the record in database
      // TODO: enqueue event to AMQ
      //

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
            // $db->commit();
        } catch (Exception $e) {
            Log::doLog($e->getMessage()) ;
            // $db->rollback();
        }
    }

    private function writeComment() {
        $this->commentData['uid'] = $this->getUid();
        $this->commentData['email'] = $this->getEmail();
        $this->commentData['resolve_date'] = $this->getResolveDate();
        $this->commentData['message_type'] = $this->getMessageType();

        // Log::doLog($this->commentData);
        $comment = insertComment($this->commentData);
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
        Log::doLog("----------- enqueue");

        $message = json_encode( array(
            '_type' => 'comment',
            'data' => array(
                'id_job' => $this->commentData['id_job'],
                'password' => $this->commentData['password'],
                'id_client' => $this->commentData['id_client'],
                'payload' => array(
                    '_type' => 'comment',
                    'message' => $this->commentData['message'],
                    'id_segment' => $this->commentData['id_segment']
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
