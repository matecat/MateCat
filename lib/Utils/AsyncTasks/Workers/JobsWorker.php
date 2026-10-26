<?php

namespace Utils\AsyncTasks\Workers;

use Exception;
use Model\DataAccess\IDatabase;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use PDOException;
use ReflectionException;
use TypeError;
use Utils\ActiveMQ\AMQHandler;
use Utils\Logger\MatecatLogger;
use Utils\TaskRunner\Commons\AbstractElement;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\Tools\Utils;

class JobsWorker extends AbstractWorker
{
    private JobDao $jobDao;

    /**
     * @param AMQHandler $queueHandler
     * @param IDatabase $database
     * @param JobDao|null $jobDao
     */
    public function __construct(AMQHandler $queueHandler, IDatabase $database, ?JobDao $jobDao = null, ?MatecatLogger $logger = null)
    {
        parent::__construct($queueHandler, $database, $logger);
        $this->jobDao = $jobDao ?? new JobDao($this->database);
    }

    /**
     * @throws EndQueueException|ReflectionException
     * @throws PDOException
     * @throws Exception
     * @throws TypeError
     */
    public function process(AbstractElement $queueElement): void
    {
        if (!$queueElement instanceof QueueElement) {
            return;
        }

        $this->_checkForReQueueEnd($queueElement);

        $jobStruct = new JobStruct($queueElement->params->toArray());

        $this->_checkDatabaseConnection();

        $this->_recountAvgPee($jobStruct);
    }

    /**
     * @param QueueElement $queueElement
     *
     * @throws EndQueueException
     * @throws Exception
     */
    protected function _checkForReQueueEnd(QueueElement $queueElement): void
    {
        if ($queueElement->reQueueNum >= 100) {
            $msg = "\n\n Error Set Contribution  \n\n " . var_export($queueElement, true);
            Utils::sendErrMailReport($msg);
            $this->logger->debug("--- (Worker " . $this->_workerPid . ") :  Frame Re-queue max value reached, acknowledge and skip.");
            throw new EndQueueException("--- (Worker " . $this->_workerPid . ") :  Frame Re-queue max value reached, acknowledge and skip.", self::ERR_REQUEUE_END);
        }
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws PDOException
     * @throws TypeError
     */
    protected function _recountAvgPee(JobStruct $jobStruct): void
    {
        $segments = $this->jobDao->getAllModifiedSegmentsForPee($jobStruct);

        $Pee_weighted = 0;
        $total_time_to_edit = 0;
        foreach ($segments as $segment) {
            $segment->target_language = $jobStruct->target;
            $Pee_weighted += $segment->getPEE() * $segment->raw_word_count;
            $total_time_to_edit += $segment->time_to_edit;
        }

        $jobStruct->avg_post_editing_effort = (float)$Pee_weighted;
        $jobStruct->total_time_to_edit = $total_time_to_edit;

        $keyLength = strlen($jobStruct->password ?? '');
        $last_digits = substr($jobStruct->password ?? '', -4);
        $hidedPassword = str_repeat("*", max(0, $keyLength - 4)) . $last_digits;
        $this->logger->debug("***** Job Split " . $jobStruct->id . "-" . $hidedPassword . " AvgPee: " . $Pee_weighted . " ***** ");

        // rowCount() counts changed rows, so 0 also covers totals identical to the stored ones.
        if ($this->jobDao->updateJobWeightedPeeAndTTE($jobStruct) === 0) {
            $this->logger->debug("***** Job Split " . $jobStruct->id . "-" . $hidedPassword . " no row changed: the chunk was merged or split again since the recount was queued, or its totals were already current ***** ");
        }
    }

}
