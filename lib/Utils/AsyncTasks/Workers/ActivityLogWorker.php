<?php

namespace Utils\AsyncTasks\Workers;

use Model\ActivityLog\ActivityLogDao;
use Model\ActivityLog\ActivityLogStruct;
use PDOException;
use Utils\TaskRunner\Commons\AbstractElement;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;

/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 13/06/16
 * Time: 11:49
 */
class ActivityLogWorker extends AbstractWorker
{

    /**
     * @throws PDOException
     * @throws EndQueueException
     */
    public function process(AbstractElement $queueElement): void
    {
        if (!$queueElement instanceof QueueElement) {
            return;
        }

        $this->_checkForReQueueEnd($queueElement);

        $logEvent = new ActivityLogStruct($queueElement->params->toArray());

        //re initialize DB if socked is closed
        $this->_checkDatabaseConnection();

        $this->_writeLog($logEvent);
    }

    /**
     * @throws PDOException
     */
    protected function _writeLog(ActivityLogStruct $logEvent, ?ActivityLogDao $dao = null): void
    {
        ($dao ?? new ActivityLogDao())->create($logEvent);
    }

}