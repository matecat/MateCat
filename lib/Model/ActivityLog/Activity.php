<?php

namespace Model\ActivityLog;

use Utils\ActiveMQ\WorkerClient;
use Utils\AsyncTasks\Workers\ActivityLogWorker;

/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 13/06/16
 * Time: 12:04
 */
class Activity {

    public static function save( ActivityLogStruct $activityLog ): void
    {
        WorkerClient::enqueue( 'ACTIVITYLOG', ActivityLogWorker::class, $activityLog->getArrayCopy(), [ 'persistent' => WorkerClient::$_HANDLER->persistent ] );
    }

}