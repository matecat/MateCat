<?php
namespace AsyncTasks\Workers;

use ActivityLog\ActivityLogDao;
use ActivityLog\ActivityLogStruct;
use Database;
use PDOException;
use TaskRunner\Commons\AbstractElement,
        TaskRunner\Commons\AbstractWorker,
        TaskRunner\Commons\QueueElement,
        TaskRunner\Exceptions\EndQueueException;

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

        //re inizialize DB if socked is closed
        $this->_checkDatabaseConnection();

        $this->_writeLog( $logEvent );

    }

    protected function _writeLog( ActivityLogStruct $logEvent ){

        $logActivityDao = new ActivityLogDao();
        $logActivityDao->create( $logEvent );

    }

}