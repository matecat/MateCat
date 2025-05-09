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
     * Initialize the Controller Instance
     *
     * @return IController
     */
    public static function getInstance(): IController {

        //Default :  catController
        $action     = $_REQUEST[ 'action' ] ?? 'cat';
        $actionList = explode( '\\', $action ); // do not accept namespaces (Security issue: directory traversal)
        $action     = end( $actionList );
        $actionList = explode( '/', $action ); // do not accept directory separator (Security issue: directory traversal)
        $action     = end( $actionList );
        $className  = ( trim( $action ) ?: 'cat' ) . "Controller";

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

}
