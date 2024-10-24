<?php
/**
 * Created by PhpStorm.
 * Date: 27/01/14
 * Time: 18.57
 *
 */

use AbstractControllers\IController;
use AbstractControllers\TimeLogger;
use API\Commons\Authentication\AuthenticationTrait;

/**
 * Abstract Class controller
 */
abstract class controller implements IController {

    use TimeLogger;
    use AuthenticationTrait;

    protected        $model;
    protected string $userRole = TmKeyManagement_Filter::ROLE_TRANSLATOR;

    /**
     * @var FeatureSet|null
     */
    protected ?FeatureSet $featureSet = null;

    /**
     * @return FeatureSet
     * @throws Exception
     */
    public function getFeatureSet(): FeatureSet {
        return ( $this->featureSet !== null ) ? $this->featureSet : new FeatureSet();
    }

    /**
     * @param FeatureSet $featureSet
     *
     * @return void
     */
    public function setFeatureSet( FeatureSet $featureSet ) {
        $this->featureSet = $featureSet;
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

            if ( empty( $_REQUEST[ 'action' ] ) ) {
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
        $action     = ( isset( $_POST[ 'action' ] ) ) ? $_POST[ 'action' ] : ( $_GET[ 'action' ] ?? 'cat' );
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

    /**
     * @return mixed
     */
    public function getModel() {
        return $this->model;
    }

}
