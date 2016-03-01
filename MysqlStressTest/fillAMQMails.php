<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 26/02/16
 * Time: 11.24
 *
 */

include_once realpath( dirname( __FILE__ ) . '/../' ) . "/inc/Bootstrap.php";
Bootstrap::start();

use \TaskRunner\Commons\QueueElement, \TaskRunner\Commons\ContextList;
use \AMQHandler;


function _updateConfiguration() {

    $config = @parse_ini_file( INIT::$UTILS_ROOT . '/Analysis/task_manager_config.ini', true );

    if(  !isset( $config[ 'context_definitions' ] ) || empty( $config[ 'context_definitions' ] ) ){
        throw new Exception( 'Wrong configuration file provided.' );
    }

    //First Execution, load build object
    return ContextList::get( $config[ 'context_definitions' ] );

}


$contextList = _updateConfiguration();
$handler     = new AMQHandler( "tcp://localhost:61613" );

$mailConf = @parse_ini_file( INIT::$ROOT . '/inc/Error_Mail_List.ini', true );

//$queue_element = array();
$queue_element = array_merge( array(), $mailConf );
$queue_element['subject'] = "monit alert -- Status succeeded mysql_slave_monitor: " . php_uname('n');
$queue_element['body'] = <<<XXX
Status succeeded Service mysql_slave_monitor

        Date:        Fri, 26 Feb 2016 17:55:09
        Action:      alert
        Host:        hawking.translated.net
        Description: status succeeded

Your faithful employee,
Monit
XXX;

$element = new QueueElement();
$element->params = $queue_element;
$element->classLoad = '\AsyncTasks\Workers\ErrMailWorker';

$handler->send( $contextList->list['MAIL']->queue_name, $element, array( 'persistent' => true ) );