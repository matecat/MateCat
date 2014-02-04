<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 27/01/14
 * Time: 18.57
 * 
 */

require_once INIT::$ROOT . '/inc/errors.inc.php';
require_once INIT::$MODEL_ROOT . '/queries.php';
require_once INIT::$UTILS_ROOT .'/AuthCookie.php';

abstract class controller {

    public static function sanitizeString( &$value, $item ){
        $value = filter_var( $value, FILTER_SANITIZE_STRING, array( 'flags' => /* FILTER_FLAG_STRIP_HIGH | */ FILTER_FLAG_STRIP_LOW ) );
    }

    /**
     * TODO refactoring
     * @return mixed
     */
    public static function getInstance() {

        if( isset( $_REQUEST['api'] ) && filter_input( INPUT_GET, 'api', FILTER_VALIDATE_BOOLEAN ) ){

            array_walk_recursive( $_REQUEST , 'controller::sanitizeString' );

            if( !isset( $_REQUEST['action'] ) || empty( $_REQUEST['action'] ) ){
                header( "HTTP/1.1 200 OK" );
                echo "OK";
                die();
            }

            $_REQUEST['action'] = ucfirst( $_REQUEST['action'] );
            $_POST['action']    = $_REQUEST['action'];

            @ob_get_clean();
            header('Content-Type: application/json; charset=utf-8');

        } else {
            require_once INIT::$ROOT . '/inc/PHPTAL/PHPTAL.php';
        }

        //Default :  cat
        $action = ( isset( $_POST[ 'action' ] ) ) ? $_POST[ 'action' ] : ( isset( $_GET[ 'action' ] ) ? $_GET[ 'action' ] : 'cat' );
        $className = $action . "Controller";
        return new $className();

    }

    protected $errors;

    abstract function doAction();

    abstract function finalize();

    protected function nocache() {
        header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
    }

    protected function __construct() {

        try {
            //$this->errors = ERRORS::obtain();
        } catch (Exception $e) {
            echo "<pre>";
            print_r($e);
            echo "\n\n\n";
            echo "</pre>";
            exit;
        }
    }

    protected function get_from_get_post($varname) {
        $ret = null;
        $ret = isset($_GET[$varname]) ? $_GET[$varname] : (isset($_POST[$varname]) ? $_POST[$varname] : null);
        return $ret;
    }

}