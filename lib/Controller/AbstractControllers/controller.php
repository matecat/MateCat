<?php
/**
 * Created by PhpStorm.
 * Date: 27/01/14
 * Time: 18.57
 *
 */

use AbstractControllers\IController;
use AbstractControllers\TimeLogger;

/**
 * Abstract Class controller
 */
abstract class controller implements IController {

    use TimeLogger;

    protected $model;
    protected $userRole = TmKeyManagement_Filter::ROLE_TRANSLATOR;

    /**
     * @var Users_UserStruct
     */
    protected $user;

    protected $uid;
    protected $userIsLogged = false;

    /**
     * @var FeatureSet
     */
    protected $featureSet;

    /**
     * @return FeatureSet
     * @throws Exception
     */
    public function getFeatureSet() {
        return ( $this->featureSet !== null ) ? $this->featureSet : new \FeatureSet();
    }

    /**
     * @param FeatureSet $featuresSet
     *
     * @return $this
     */
    public function setFeatureSet( FeatureSet $featuresSet ) {
        $this->featureSet = $featuresSet;

        return $this;
    }

    /**
     * @return Users_UserStruct
     */
    public function getUser() {
        return $this->user;
    }

    public function userIsLogged() {
        return $this->userIsLogged;
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

        if ( isset( $_REQUEST[ 'api' ] ) && filter_input( INPUT_GET, 'api', FILTER_VALIDATE_BOOLEAN ) ) {

            if ( !isset( $_REQUEST[ 'action' ] ) || empty( $_REQUEST[ 'action' ] ) ) {
                header( "Location: " . INIT::$HTTPHOST . INIT::$BASEURL . "api/docs", true, 303 ); //Redirect 303 See Other
                die();
            }

            $_REQUEST[ 'action' ][ 0 ] = strtoupper( $_REQUEST[ 'action' ][ 0 ] );
            $_REQUEST[ 'action' ]      = preg_replace_callback( '/_([a-z])/', function ( $c ) {
                return strtoupper( $c[ 1 ] );
            }, $_REQUEST[ 'action' ] );

            $_POST[ 'action' ] = $_REQUEST[ 'action' ];

            //set the log to the API Log
            Log::$fileName = 'API.log';

        }

        //Default :  catController
        $action     = ( isset( $_POST[ 'action' ] ) ) ? $_POST[ 'action' ] : ( isset( $_GET[ 'action' ] ) ? $_GET[ 'action' ] : 'cat' );
        $actionList = explode( '\\', $action ); // do not accept namespaces ( Security issue: directory traversal )
        $action     = end( $actionList ); // do not accept namespaces ( Security issue: directory traversal )
        $className  = $action . "Controller";

        //Put here all actions we want to be performed by ALL controllers

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
        header( "Expires: Tue, 03 Jul 2001 06:00:00 GMT" );
        header( "Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . " GMT" );
        header( "Cache-Control: no-store, no-cache, must-revalidate, max-age=0" );
        header( "Cache-Control: post-check=0, pre-check=0", false );
        header( "Pragma: no-cache" );
    }

    public function sessionStart() {
        Bootstrap::sessionStart();
    }

    /**
     * Explicitly disable sessions for ajax call
     *
     * Sessions enabled on INIT Class
     *
     */
    public function disableSessions() {
        Bootstrap::sessionClose();
    }

    /**
     * @return mixed
     */
    public function getModel() {
        return $this->model;
    }

    public function setUserCredentials() {

        $this->user        = new Users_UserStruct();
        $this->user->uid   = ( isset( $_SESSION[ 'uid' ] ) && !empty( $_SESSION[ 'uid' ] ) ? $_SESSION[ 'uid' ] : null );
        $this->user->email = ( isset( $_SESSION[ 'cid' ] ) && !empty( $_SESSION[ 'cid' ] ) ? $_SESSION[ 'cid' ] : null );

        try {

            $userDao            = new Users_UserDao( Database::obtain() );
            $loggedUser         = $userDao->setCacheTTL( 3600 )->read( $this->user )[ 0 ]; // one hour cache
            $this->userIsLogged = (
                    !empty( $loggedUser->uid ) &&
                    !empty( $loggedUser->email ) &&
                    !empty( $loggedUser->first_name ) &&
                    !empty( $loggedUser->last_name )
            );

        } catch ( Exception $e ) {
            Log::doJsonLog( 'User not logged.' );
        }
        $this->user = ( $this->userIsLogged ? $loggedUser : $this->user );

    }

    /**
     *  Try to get user name from cookie if it is not present and put it in session.
     *
     */
    protected function _setUserFromAuthCookie() {
        if ( empty( $_SESSION[ 'cid' ] ) ) {
            $username_from_cookie = AuthCookie::getCredentials();
            if ( $username_from_cookie ) {
                $_SESSION[ 'cid' ] = $username_from_cookie[ 'username' ];
                $_SESSION[ 'uid' ] = $username_from_cookie[ 'uid' ];
            }
        }
    }

    public function readLoginInfo( $close = true ) {
        //Warning, sessions enabled, disable them after check, $_SESSION is in read only mode after disable
        self::sessionStart();
        $this->_setUserFromAuthCookie();
        $this->setUserCredentials();

        if ( $close ) {
            self::disableSessions();
        }

    }

    /**
     * isLoggedIn
     *
     * @return bool
     */
    public function isLoggedIn() {
        return $this->userIsLogged;
    }

}
