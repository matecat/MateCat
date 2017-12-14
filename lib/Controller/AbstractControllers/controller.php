<?php
/**
 * Created by PhpStorm.
 * Date: 27/01/14
 * Time: 18.57
 *
 */

/**
 * Abstract Class controller
 */
abstract class controller {

    protected $model;
    protected $userRole = TmKeyManagement_Filter::ROLE_TRANSLATOR;

    /**
     * @var Users_UserStruct
     * @deprecated Use getUser method instead
     */
    protected $logged_user;

    protected $uid;
    protected $userIsLogged = false;
    protected $userMail;


    /**
     * @return Users_UserStruct
     */
    public function getLoggedUser() {
        return $this->logged_user;
    }

    /**
     * Controllers Factory
     *
     * Initialize the Controller Instance and route the
     * API Calls to the right Controller
     *
     * @return mixed
     */
    public static function getInstance() {

        if( isset( $_REQUEST['api'] ) && filter_input( INPUT_GET, 'api', FILTER_VALIDATE_BOOLEAN ) ){

            if( !isset( $_REQUEST['action'] ) || empty( $_REQUEST['action'] ) ){
                header( "Location: " . INIT::$HTTPHOST . INIT::$BASEURL . "api/docs", true, 303 ); //Redirect 303 See Other
                die();
            }

            $_REQUEST[ 'action' ][0] = strtoupper( $_REQUEST[ 'action' ][ 0 ] );

            //PHP 5.2 compatibility, don't use a lambda function
            $func                 = create_function( '$c', 'return strtoupper($c[1]);' );

            $_REQUEST[ 'action' ] = preg_replace_callback( '/_([a-z])/', $func, $_REQUEST[ 'action' ] );
            $_POST[ 'action' ]    = $_REQUEST[ 'action' ];

            //set the log to the API Log
            Log::$fileName = 'API.log';

        }

        //Default :  catController
        $action = ( isset( $_POST[ 'action' ] ) ) ? $_POST[ 'action' ] : ( isset( $_GET[ 'action' ] ) ? $_GET[ 'action' ] : 'cat' );
        $className = $action . "Controller";

        //Put here all actions we want to be performed by ALL controllers
        require_once INIT::$MODEL_ROOT . '/queries.php';

        return new $className();

    }

    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     */
    abstract function doAction();

    /**
     * Called to get the result values in the right format
     *
     * @return mixed
     */
    abstract function finalize();

    /**
     * Set No Cache headers
     *
     */
    protected function nocache() {
        header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
    }

    public function sessionStart(){
        Bootstrap::sessionStart();
    }

    /**
     * Explicitly disable sessions for ajax call
     *
     * Sessions enabled on INIT Class
     *
     */
    public function disableSessions(){
        Bootstrap::sessionClose();
    }

    /**
     * @return mixed
     */
    public function getModel()
    {
        return $this->model;
    }

    public function setUserCredentials() {

        $this->logged_user        = new Users_UserStruct();
        $this->logged_user->uid   = ( isset( $_SESSION[ 'uid' ] ) && !empty( $_SESSION[ 'uid' ] ) ? $_SESSION[ 'uid' ] : null );
        $this->logged_user->email = ( isset( $_SESSION[ 'cid' ] ) && !empty( $_SESSION[ 'cid' ] ) ? $_SESSION[ 'cid' ] : null );

        try {
            $userDao           = new Users_UserDao( Database::obtain() );
            $loggedUser = $userDao->setCacheTTL( 3600 )->read( $this->logged_user )[ 0 ]; // one hour cache
            $this->logged_user = ( !empty( $loggedUser ) ? $loggedUser : $this->logged_user );
        } catch ( Exception $e ) {
            Log::doLog( 'User not logged.' );
        }

        $this->userIsLogged = ( !empty( $this->logged_user->email ) );
        $this->uid          = $this->logged_user->getUid();
        $this->userMail     = $this->logged_user->getEmail();

    }

    /**
     *  Try to get user name from cookie if it is not present and put it in session.
     *
     */
    protected function _setUserFromAuthCookie() {
        if ( empty( $_SESSION[ 'cid' ] ) ) {
            $username_from_cookie = AuthCookie::getCredentials();
            if ( $username_from_cookie ) {
                $_SESSION[ 'cid' ] = $username_from_cookie['username'];
                $_SESSION[ 'uid' ] = $username_from_cookie['uid'];
            }
        }
    }

}
