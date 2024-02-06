<?php

use TMS\TMSService;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/12/14
 * Time: 16.10
 *
 */
class downloadTMXController extends ajaxController {

    /**
     * @var int
     */
    protected $id_job;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var bool
     */
    protected $strip_tags;

    /**
     * Tell to MyMemory to send the download link to this email.
     *
     * @var string
     */
    protected $download_to_email;

    /**
     * MyMemory key
     *
     * @var string
     */
    protected $tm_key;

    /**
     * MyMemory name/description
     *
     * @var string
     */
    protected $tm_name;

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
     * @var TMSService
     */
    protected $tmxHandler;

    /**
     * User
     *
     * @var Users_UserStruct
     */
    protected $user;

    public function __construct() {

        parent::__construct();

        /**
         * Retrieve user information
         */
        $this->readLoginInfo();

        $filterArgs = array(
                'id_job'        => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'password'      => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'tm_key'        => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'tm_name'       => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'downloadToken' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'email'         => array(
                        'filter' => FILTER_VALIDATE_EMAIL
                ),
                'strip_tags'         => array(
                        'filter' => FILTER_VALIDATE_BOOLEAN
                ),
                'source'        => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'target'        => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                )

        );

        $__postInput = filter_var_array( $_REQUEST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI Test scripts
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->tm_key            = $__postInput[ 'tm_key' ];
        $this->tm_name           = $__postInput[ 'tm_name' ];
        $this->source            = $__postInput[ 'source' ];
        $this->target            = $__postInput[ 'target' ];
        $this->download_to_email = $__postInput[ 'email' ];
        $this->id_job            = $__postInput[ 'id_job' ];
        $this->password          = $__postInput[ 'password' ];
        $this->strip_tags        = $__postInput[ 'strip_tags' ];

        if ( !$this->userIsLogged ) {

            $output = "<pre>\n";
            $output .= " - REQUEST URI: " . print_r( @$_SERVER[ 'REQUEST_URI' ], true ) . "\n";
            $output .= " - REQUEST Message: " . print_r( $_REQUEST, true ) . "\n";
            $output .= "\n\t";
            $output .= "Aborting...\n";
            $output .= "</pre>";

            Log::$fileName = 'php_errors.txt';
            Log::doJsonLog( $output );

            Utils::sendErrMailReport( $output, "Download TMX Error: user Not Logged" );
            exit;
        }

        $this->tmxHandler = new TMSService();
        $this->tmxHandler->setName( $this->tm_name );

    }

    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     */
    function doAction() {

        try {

            if ( $this->download_to_email === false ) {
                $this->result[ 'errors' ][] = array(
                        "code"    => -1,
                        "message" => "Invalid email provided for download."
                );

                return;
            }

            $this->result[ 'data' ] = $this->tmxHandler->requestTMXEmailDownload(
                    ( $this->download_to_email != false ? $this->download_to_email : $this->user->email ),
                    $this->user->first_name,
                    $this->user->last_name,
                    $this->tm_key,
                    $this->strip_tags
            );

            // TODO: Not used at moment, will be enabled when will be built the Log Activity Keys
            /*
                $activity             = new ActivityLogStruct();
                $activity->id_job     = $this->id_job;
                $activity->action     = ActivityLogStruct::DOWNLOAD_KEY_TMX;
                $activity->ip         = Utils::getRealIpAddr();
                $activity->uid        = $this->user->uid;
                $activity->event_date = date( 'Y-m-d H:i:s' );
                Activity::save( $activity );
            */

        } catch ( Exception $e ) {

            $r = "<pre>";

            $r .= print_r( "User Email: " . $this->download_to_email, true ) . "\n";
            $r .= print_r( "User ID: " . $this->user->uid, true ) . "\n";
            $r .= print_r( $e->getMessage(), true ) . "\n";
            $r .= print_r( $e->getTraceAsString(), true ) . "\n";

            $r .= "\n\n";
            $r .= " - REQUEST URI: " . print_r( @$_SERVER[ 'REQUEST_URI' ], true ) . "\n";
            $r .= " - REQUEST Message: " . print_r( $_REQUEST, true ) . "\n";
            $r .= "\n\n\n";
            $r .= "</pre>";

            Log::$fileName = 'php_errors.txt';
            Log::doJsonLog( $r );

            Utils::sendErrMailReport( $r, "Download TMX Error: " . $e->getMessage() );

            $this->result[ 'errors' ][] = array( "code" => -2, "message" => "Download TMX Error: " . $e->getMessage() );

            return;

        }
    }

} 