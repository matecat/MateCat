<?php
namespace AsyncTasks\Workers;

use ActivityLog\ActivityLogDao;
use ActivityLog\ActivityLogStruct;
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

        $this->_writeLog( $logEvent );

    }

    /**
     * Check how much times the element was re-queued and raise an Exception when the limit is reached ( 100 times )
     *
     * @param QueueElement $queueElement
     *
     * @throws EndQueueException
     */
    protected function _checkForReQueueEnd( QueueElement $queueElement ){

        /**
         *
         * check for loop re-queuing
         */
        if ( isset( $queueElement->reQueueNum ) && $queueElement->reQueueNum >= 100 ) {

            $msg = "\n\n Error Set Contribution  \n\n " . var_export( $queueElement, true );
            \Utils::sendErrMailReport( $msg );
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") :  Frame Re-queue max value reached, acknowledge and skip." );
            throw new EndQueueException( "--- (Worker " . $this->_workerPid . ") :  Frame Re-queue max value reached, acknowledge and skip.", self::ERR_REQUEUE_END );

        } elseif ( isset( $queueElement->reQueueNum ) ) {
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") :  Frame re-queued {$queueElement->reQueueNum} times." );
        }

    }

    protected function _writeLog( ActivityLogStruct $logEvent ){

        $logActivityDao = new ActivityLogDao();
        $logActivityDao->create( $logEvent );

    }

}