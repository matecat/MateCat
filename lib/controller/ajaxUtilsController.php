<?php
/**
 * Created by JetBrains PhpStorm.
 * User: domenico
 * Date: 31/07/13
 * Time: 18.54
 * To change this template use File | Settings | File Templates.
 */

class ajaxUtilsController extends ajaxcontroller {

    private $__postInput = null;
    private $__getInput = null;

    public function __construct() {
        parent::__construct();

//        $gets = $_GET;
//        foreach ( $gets as $key => &$value ) {
//            $value = filter_var( $value, FILTER_SANITIZE_STRING, array( 'flags' => FILTER_FLAG_STRIP_LOW ) );
//        }
//        $this->__getInput = $gets;

        $posts = $_POST;
        foreach ( $posts as $key => &$value ) {
            $value = filter_var( $value, FILTER_SANITIZE_STRING, array( 'flags' => FILTER_FLAG_STRIP_LOW ) );
        }

        $this->__postInput = $posts;

    }

    public function doAction() {

        switch ( $this->__postInput['exec'] ) {
            case 'stayAnonymous':
                unset( $_SESSION['_anonym_pid'] );
                unset( $_SESSION['incomingUrl'] );
                break;

        }

    }
}