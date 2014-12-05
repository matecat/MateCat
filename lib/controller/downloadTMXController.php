<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/12/14
 * Time: 16.10
 * 
 */

class downloadTMXController extends downloadController {

    /**
     * MyMemory key
     *
     * @var string
     */
    protected $tm_key;

    /**
     * For future implementations
     *
     * @var string
     */
    protected $source;

    /**
     * For future implementations
     *
     * @var string
     */
    protected $target;

    /**
     * Download Token
     *
     * @var string
     */
    protected $downloadToken;

    /**
     * @var TMSService
     */
    protected $tmxHandler;

    /**
     * @var
     */
    protected $streamFilePointer;

    /**
     * User id
     * @var int
     */
    protected $uid;

    /**
     * User email
     *
     * @var string
     */
    protected $userMail;

    public function __construct() {

        parent::sessionStart();

        parent::__construct();
        $filterArgs = array(

            'tm_key' =>  array(
                    'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            ),
            'downloadToken' =>  array(
                    'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            ),
            'source' =>  array(
                    'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            ),
            'target' =>  array(
                    'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            )

        );

        $__postInput = filter_var_array( $_REQUEST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI Test scripts
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->tm_key        = $__postInput[ 'tm_key' ];
        $this->source        = $__postInput[ 'source' ];
        $this->target        = $__postInput[ 'target' ];
        $this->downloadToken = $__postInput[ 'downloadToken' ];

        parent::disableSessions();

        $userIsLogged   = ( isset( $_SESSION[ 'cid' ] ) && !empty( $_SESSION[ 'cid' ] ) );

        if( !$userIsLogged ){

            $output = "<pre>\n";
            $output .=  " - REQUEST URI: " . print_r( @$_SERVER['REQUEST_URI'], true ) . "\n";
            $output .=  " - REQUEST Message: " . print_r( $_REQUEST, true ) . "\n";
            $output .=  "\n\t";
            $output .=  "Aborting...\n";
            $output .= "</pre>";

            Log::$fileName = 'php_errors.txt';
            Log::doLog( $output );

            Utils::sendErrMailReport( $output, "Download TMX Error: user Not Logged" );
            exit;
        }

        $this->uid      = ( isset( $_SESSION[ 'uid' ] ) && !empty( $_SESSION[ 'uid' ] ) ? $_SESSION[ 'uid' ] : null );
        $this->userMail = ( isset( $_SESSION[ 'cid' ] ) && !empty( $_SESSION[ 'cid' ] ) ? $_SESSION[ 'cid' ] : null );

        $this->tmxHandler = new TMSService();
        $this->tmxHandler->setTmKey( $this->tm_key );

    }

    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     */
    function doAction() {

        try {

            $this->streamFilePointer = $this->tmxHandler->downloadTMX();

        } catch( Exception $e ){

            $r = "<pre>";

            $r .= print_r( "User Email: " . $this->userMail , true );
            $r .= print_r( "User ID: " . $this->uid , true );
            $r .= print_r( $e->getMessage(), true );
            $r .= print_r( $e->getTraceAsString(), true );

            $r .= "\n\n";
            $r .=  " - REQUEST URI: " . print_r( @$_SERVER['REQUEST_URI'], true ) . "\n";
            $r .=  " - REQUEST Message: " . print_r( $_REQUEST, true ) . "\n";
            $r .= "\n\n\n";
            $r .= "</pre>";

            Log::$fileName = 'php_errors.txt';
            Log::doLog( $r );

            Utils::sendErrMailReport( $r, "Download TMX Error: " . $e->getMessage() );


            $this->unlockToken();
            header("HTTP/1.0 403 Forbidden");

            exit;

        }

    }

    public function finalize() {

        $this->unlockToken();

        list( $file_name, $mx_domain) = explode( "@", $this->userMail );

        $file_name .= "_" . uniqid() . ".zip";

        $buffer = ob_get_contents();
        ob_get_clean();
        ob_start("ob_gzhandler");  // compress page before sending
        $this->nocache();
        header("Content-Type: application/force-download");
        header("Content-Type: application/octet-stream");
        header("Content-Type: application/download");
        header("Content-Disposition: attachment; filename=\"$file_name\""); // enclose file name in double quotes in order to avoid duplicate header error. Reference https://github.com/prior/prawnto/pull/16
        header("Expires: 0");
        header("Connection: close");
        while ( !feof( $this->streamFilePointer ) ) {
            echo fgets( $this->streamFilePointer, 2048 );
        }
        fclose( $this->streamFilePointer );
        exit;

    }

} 