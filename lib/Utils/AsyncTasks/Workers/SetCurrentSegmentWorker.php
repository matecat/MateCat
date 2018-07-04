<?php
namespace AsyncTasks\Workers;

use ActivityLog\ActivityLogDao;
use ActivityLog\ActivityLogStruct;
use Database;
use PDOException;
use Segments_SegmentDao;
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
class SetCurrentSegmentWorker extends AbstractWorker {

    public function process( AbstractElement $queueElement ) {

        /**
         * @var $queueElement QueueElement
         */
        $this->_checkForReQueueEnd( $queueElement );

        //re initialize DB if socket is closed
        $this->_checkDatabaseConnection();

        $this->_writeCurrentSegment( $queueElement->params->toArray() );

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

            $msg = "\n\n Error Set Current Segment  \n\n " . var_export( $queueElement, true );
            \Utils::sendErrMailReport( $msg );
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") :  Frame Re-queue max value reached, acknowledge and skip." );
            throw new EndQueueException( "--- (Worker " . $this->_workerPid . ") :  Frame Re-queue max value reached, acknowledge and skip.", self::ERR_REQUEUE_END );

        } elseif ( isset( $queueElement->reQueueNum ) ) {
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") :  Frame re-queued {$queueElement->reQueueNum} times." );
        }

    }

    protected function _writeCurrentSegment( Array $currentSegmentEvent ){

        $segmentsDao = new Segments_SegmentDao();
        return $segmentsDao->updateCurrentSegment( $currentSegmentEvent[ 'id_segment' ], $currentSegmentEvent[ 'id_job' ], $currentSegmentEvent[ 'password' ] );

    }

}