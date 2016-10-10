<?php
/**
 * Created by PhpStorm.
 */


/**
 * Abstract class for all html views
 *
 * Date: 27/01/14
 * Time: 18.56
 *
 */
abstract class viewController extends controller {

    /**
     * Template Engine Instance
     *
     * @var PHPTAL
     */
    protected $template = null;

    /**
     * Flag to get info about browser support
     *
     * @var bool
     */
    protected $supportedBrowser = false;
    /*
     * The user os
     * @var string
     */
    protected $userPlatform;
    /**
     * The os platform
     *
     * @var string
     */
    protected $browser_platform;
    /**
     * @var Google_Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $authURL;

    /**
     * The logged user's object
     *
     * @var Users_UserStruct
     */
    protected $logged_user;

    /**
     * Try to identify the browser of users
     *
     * @return array
     */
    private function getBrowser() {
        $u_agent  = $_SERVER[ 'HTTP_USER_AGENT' ];

        //First get the platform?
        if ( preg_match( '/linux/i', $u_agent ) ) {
            $platform = 'linux';
        } elseif ( preg_match( '/macintosh|mac os x/i', $u_agent ) ) {
            $platform = 'mac';
        } elseif ( preg_match( '/windows|win32/i', $u_agent ) ) {
            $platform = 'windows';
        } else {
            $platform = 'Unknown';
        }

        // Next get the name of the useragent yes seperately and for good reason
        if ( preg_match( '/MSIE|Trident|Edge/i', $u_agent ) && !preg_match( '/Opera/i', $u_agent ) ) {
            $bname = 'Internet Explorer';
            $ub    = "MSIE";
        } elseif ( preg_match( '/Firefox/i', $u_agent ) ) {
            $bname = 'Mozilla Firefox';
            $ub    = "Firefox";
        } elseif ( preg_match( '/Chrome/i', $u_agent ) and !preg_match( '/OPR/i', $u_agent ) ) {
            $bname = 'Google Chrome';
            $ub    = "Chrome";
        } elseif ( preg_match( '/Opera|OPR/i', $u_agent ) ) {
            $bname = 'Opera';
            $ub    = "Opera";
        } elseif ( preg_match( '/Safari/i', $u_agent ) ) {
            $bname = 'Apple Safari';
            $ub    = "Safari";
        } elseif ( preg_match( '/AppleWebKit/i', $u_agent ) ) {
            $bname = 'Apple Safari';
            $ub    = "Safari";
        } elseif ( preg_match( '/Netscape/i', $u_agent ) ) {
            $bname = 'Netscape';
            $ub    = "Netscape";
        } elseif ( preg_match( '/Mozilla/i', $u_agent ) ) {
            $bname = 'Mozilla Generic';
            $ub    = "Mozillageneric";
        } else {
            $bname = 'Unknown';
            $ub    = "Unknown";
        }
        // finally get the correct version number
        $known   = array( 'Version', $ub, 'other' );
        $pattern = '#(?<browser>' . join( '|', $known ) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
        if ( !preg_match_all( $pattern, $u_agent, $matches ) ) {
            // we have no matching number just continue
        }

        // see how many we have
        $i = count( $matches[ 'browser' ] );
        if ( $i != 1 ) {
            //we will have two since we are not using 'other' argument yet
            //see if version is before or after the name
            if ( strripos( $u_agent, "Version" ) < strripos( $u_agent, $ub ) ) {
                $version = $matches[ 'version' ][ 0 ];
            } else {
                $version = @$matches[ 'version' ][ 1 ];
            }
        } else {
            $version = $matches[ 'version' ][ 0 ];
        }

        // check if we have a number
        if ( $version == null || $version == "" ) {
            $version = "?";
        }

        return array(
                'userAgent' => $u_agent,
                'name'      => $bname,
                'version'   => $version,
                'platform'  => $platform,
                'pattern'   => $pattern
        );

    }

    /**
     * Class constructor
     *
     * @param bool $isAuthRequired
     */
    public function __construct( $isAuthRequired = false ) {

	    if( !Bootstrap::areMandatoryKeysPresent() ) {
		    header("Location: " . INIT::$HTTPHOST . INIT::$BASEURL . "badConfiguration" , true, 303);
		    exit;
	    }

        //SESSION ENABLED
        parent::sessionStart();

        //load Template Engine
        require_once INIT::$ROOT . '/inc/PHPTAL/PHPTAL.php';

        $this->setBrowserSupport();

        //try to get user name from cookie if it is not present and put it in session
        if ( empty( $_SESSION[ 'cid' ] ) ) {

            //log::doLog(get_class($this)." requires check for login");
            $username_from_cookie = AuthCookie::getCredentials();
            if ( $username_from_cookie ) {
                $_SESSION[ 'cid' ] = $username_from_cookie['username'];
                $_SESSION[ 'uid' ] = $username_from_cookie['uid'];
            }

        }

        //even if no login in required, if user data is present, pull it out
        $this->logged_user = new Users_UserStruct();
        if ( !empty( $_SESSION[ 'cid' ] ) ){
            $this->logged_user->uid = $_SESSION[ 'uid' ];
            $this->logged_user->email = $_SESSION[ 'cid' ];

            $userDao = new Users_UserDao(Database::obtain());
            $userObject = $userDao->setCacheTTL( 3600 )->read( $this->logged_user ); // one hour cache

            /**
             * @var $userObject Users_UserStruct
             */
            $this->logged_user = $userObject[0];
        }

        if( $isAuthRequired  ) {
            //if auth is required, stat procedure
            $this->doAuth();
        }

    }

    /**
     * Perform Authentication Requests and set incoming url
     *
     * @return bool
     */
    private function doAuth() {
        //prepare redirect flag
        $mustRedirectToLogin = false;

        //if no login set and login is required
        if ( !$this->isLoggedIn() ) {
            //take note of url we wanted to go after
            $_SESSION[ 'incomingUrl' ] = $_SERVER[ 'REQUEST_URI' ];
            parse_str( $_SERVER[ 'QUERY_STRING' ], $queryStringArray );
            if ( isset( $queryStringArray[ 'new' ] ) ) {
                $_SESSION[ '_newProject' ] = (bool)$queryStringArray[ 'new' ];
            }

            //signal redirection
            $mustRedirectToLogin = true;
        }

        if ( $mustRedirectToLogin ) {
            //redirect to login page
            header( 'Location: /login' );
            exit;
        }

        return true;
    }



    /**
     * Check user logged
     *
     * @return bool
     */
    public function isLoggedIn() {        
        if( isset( $_SESSION[ 'cid' ] ) && !empty( $_SESSION[ 'cid' ] ) ) {
            AuthCookie::tryToRefreshToken( $_SESSION[ 'cid' ] );
        }
        
        return (
                ( isset( $_SESSION[ 'cid' ] ) && !empty( $_SESSION[ 'cid' ] ) ) &&
                ( isset( $_SESSION[ 'uid' ] ) && !empty( $_SESSION[ 'uid' ] ) )
        );
    }

    /**
     * GatUser Login Info
     *
     * @return bool
     */
    public function getLoginUserParams() {
        if ( $this->isLoggedIn() ){
            return array( $this->logged_user->getUid(), $this->logged_user->getEmail() );
        }
        return array( null, null );
    }

    /**
     * Check for browser support
     *
     * @return bool
     */
    private function isSupportedWebBrowser($browser_info) {

        $browser_name = strtolower( $browser_info[ 'name' ] );
        $browser_platform = strtolower( $browser_info[ 'platform' ] );
        $return_value = 0;
 //	    log::doLog ("bname $browser_name");

/*        if (  ($browser_name=="internet explorer" or $browser_name=="mozilla firefox")  and  $_SERVER[ 'REQUEST_URI' ]=="/" ) {
                return -2;
         }
*/
        foreach ( INIT::$ENABLED_BROWSERS as $enabled_browser ) {
            if ( stripos( $browser_name, $enabled_browser ) !== false ) {
                // Safari supported only on Mac
                if (stripos( "apple safari", $browser_name ) === false ||
                    (stripos( "apple safari", $browser_name ) !== false && stripos("mac", $browser_platform) !== false) )
                    return 1;
            }
        }

        foreach ( INIT::$UNTESTED_BROWSERS as $untested_browser ) {
            if ( stripos( $browser_name, $untested_browser ) !== false ) {
                return -1;
            }
        }

	//unsupported browsers: hack for home page
        if ($_SERVER[ 'REQUEST_URI' ]=="/") return -2;

        return 0;
    }

    /**
     * Return the content in the right format, it tell to the child class to execute template vars inflating
     *
     * @see controller::finalize
     *
     * @return mixed|void
     */
    public function finalize() {

        $this->setInitialTemplateVars();

        /**
         * Call child for template vars fill
         *
         */
        $this->setTemplateVars();

        $featureSet = new FeatureSet();
        $featureSet->run('appendDecorators', $this, $this->template);

        $this->setTemplateFinalVars();

        try {

            $buffer = ob_get_contents();
            ob_get_clean();
            ob_start( "ob_gzhandler" ); // compress page before sending
            $this->nocache();

            header( 'Content-Type: text/html; charset=utf-8' );

            /**
             * Execute Template Rendering
             */
            echo $this->template->execute();

        } catch ( Exception $e ) {
            echo "<pre>";
            print_r( $e );
            echo "\n\n\n";
            echo "</pre>";
            exit;
        }

    }


    /**
     * setInitialTemplateVars
     *
     * Initialize template variables that must be initialized to avoid templte errors.
     * These variables are expected to be overridden.
     */
    private function setInitialTemplateVars() {
        $this->template->footer_js = array();
        $this->template->config_js = array() ;
        $this->template->css_resources = array();
    }

    /**
     * setTemplateFinalVars
     *
     * Here you have the possiblity to set additional template variables that you always want available in the
     * template. This is the pleace where to set variables like user_id, email address and so on.
     */
    private function setTemplateFinalVars() {
        $this->template->logged_user   = $this->logged_user->shortName() ;
        $this->template->extended_user = $this->logged_user->fullName() ;

        $this->template->isLoggedIn    = $this->isLoggedIn();
        $this->template->userMail      = $this->logged_user->getEmail() ;
    }

    /**
     *
     * Set the variables for the browser support
     *
     */
    private function setBrowserSupport() {
        $browser_info = $this->getBrowser();
        $this->supportedBrowser = $this->isSupportedWebBrowser($browser_info);
        $this->userPlatform = strtolower( $browser_info[ 'platform' ] );
    }

    /**
     * tell the children to set the template vars
     *
     * @return mixed
     */
    abstract function setTemplateVars();

    /**
     * @return Users_UserStruct
     */
    public function getLoggedUser(){
        return $this->logged_user;
    }

    /**
     * @return string
     */
    public function getAuthUrl(){
        return $this->authURL;
    }    public static function isRevision(){
        //TODO: IMPROVE
        $_from_url   = parse_url( $_SERVER[ 'REQUEST_URI' ] );
        $is_revision_url = strpos( $_from_url[ 'path' ], "/revise" ) === 0;
        return $is_revision_url;
    }

    /**
     * Get Client Instance and retrieve authentication url
     *
     */
    protected function generateAuthURL() {

        $this->client  = OauthClient::getInstance()->getClient();
        $this->authURL = $this->client->createAuthUrl();

    }

    /**
     * Create an instance of skeleton PHPTAL template
     *
     * @param $skeleton_file
     *
     */
    protected function makeTemplate( $skeleton_file ) {
        try {
            $this->template                       = new PHPTALWithAppend( INIT::$TEMPLATE_ROOT . "/$skeleton_file" ); // create a new template object
            $this->template->basepath             = INIT::$BASEURL;
            $this->template->hostpath             = INIT::$HTTPHOST;
            $this->template->supportedBrowser     = $this->supportedBrowser;
            $this->template->platform             = $this->userPlatform;
            $this->template->enabledBrowsers      = INIT::$ENABLED_BROWSERS;
            $this->template->build_number         = INIT::$BUILD_NUMBER;
            $this->template->use_compiled_assets  = INIT::$USE_COMPILED_ASSETS;

            $this->template->maxFileSize          = INIT::$MAX_UPLOAD_FILE_SIZE;
            $this->template->maxTMXFileSize       = INIT::$MAX_UPLOAD_TMX_FILE_SIZE;

            ( INIT::$VOLUME_ANALYSIS_ENABLED ? $this->template->analysis_enabled = true : null );
            $this->template->setOutputMode( PHPTAL::HTML5 );
        } catch ( Exception $e ) {
            echo "<pre>";
            print_r( $e );
            echo "\n\n\n";
            print_r( $this->template );
            echo "</pre>";
            exit;
        }
    }
}
