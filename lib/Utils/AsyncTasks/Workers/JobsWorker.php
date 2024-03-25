<?php
namespace AsyncTasks\Workers;

use Jobs_JobDao;
use Jobs_JobStruct;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Commons\QueueElement;
use TaskRunner\Exceptions\EndQueueException;
use Utils;

//include_once INIT::$UTILS_ROOT . "/MyMemory.copyrighted.php";

/**
 * Created by PhpStorm.
 * User: Ostico
 * Date: 13/06/16
 * Time: 11:49
 */
class JobsWorker extends AbstractWorker {

    /**
     * @throws EndQueueException
     */
    public function process( AbstractElement $queueElement ) {

        /**
         * @var $queueElement QueueElement
         */
        $this->_checkForReQueueEnd( $queueElement );

        $jobStruct = new Jobs_JobStruct( $queueElement->params->toArray() );

        //re-initialize DB if socked is closed
        $this->_checkDatabaseConnection();

        $this->_recountAvgPee( $jobStruct );

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
            Utils::sendErrMailReport( $msg );
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") :  Frame Re-queue max value reached, acknowledge and skip." );
            throw new EndQueueException( "--- (Worker " . $this->_workerPid . ") :  Frame Re-queue max value reached, acknowledge and skip.", self::ERR_REQUEUE_END );

        } elseif ( isset( $queueElement->reQueueNum ) ) {
//            $this->_doLog( "--- (Worker " . $this->_workerPid . ") :  Frame re-queued {$queueElement->reQueueNum} times." );
        }

    }

    protected function _recountAvgPee( Jobs_JobStruct $jobStruct ){

        $jDao = new Jobs_JobDao();

        $segments = $jDao->getAllModifiedSegmentsForPee( $jobStruct );

        $Pee_weighted = 0;
        $total_time_to_edit = 0;
        foreach( $segments as $segment ){
            $segment->job_target = $jobStruct->target; //Add language to tell to TMS_MATCH if this is a CJK
            $Pee_weighted += $segment->getPEE() * $segment->raw_word_count;
            $total_time_to_edit += $segment->time_to_edit;
        }

        $jobStruct->avg_post_editing_effort = $Pee_weighted;
        $jobStruct->total_time_to_edit = $total_time_to_edit;

        $this->_doLog( "***** Job Split " . $jobStruct->id . "-" . $jobStruct->password . " AvgPee: ". $Pee_weighted . " ***** ");

        $jDao->updateJobWeightedPeeAndTTE( $jobStruct );

    }

}