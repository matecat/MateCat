<?php
namespace AsyncTasks\Workers;

use ActivityLog\ActivityLogDao;
use ActivityLog\ActivityLogStruct;
use Database;
use PDOException;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Commons\QueueElement;

/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 13/06/16
 * Time: 11:49
 */
class ActivityLogWorker extends AbstractWorker {

    public function process( AbstractElement $queueElement ) {

        /**
         * @var $queueElement QueueElement
         */
        $this->_checkForReQueueEnd( $queueElement );

        $logEvent = new ActivityLogStruct( $queueElement->params->toArray() );

        //re initialize DB if socked is closed
        $this->_checkDatabaseConnection();

        $this->_writeLog( $logEvent );

    }

    protected function _writeLog( ActivityLogStruct $logEvent ){

        $logActivityDao = new ActivityLogDao();
        $logActivityDao->create( $logEvent );

    }

    /**
     * Check the connection.
     * MySql timeout close the socket and throws Exception in the nex read/write access
     *
     * <code>
     * By default, the server closes the connection after eight hours if nothing has happened.
     * You can change the time limit by setting thewait_timeout variable when you start mysqld.
     * @see http://dev.mysql.com/doc/refman/5.0/en/gone-away.html
     * </code>
     *
     */
    protected function _checkDatabaseConnection() {

        $db = Database::obtain();
        try {
            $db->ping();
        } catch ( PDOException $e ) {
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") : {$e->getMessage()} " );
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Database connection reloaded. " );
            $db->close();
            //reconnect
            $db->getConnection();
        }

    }

}