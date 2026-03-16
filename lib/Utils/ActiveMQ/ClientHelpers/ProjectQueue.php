<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 03/03/17
 * Time: 15.15
 *
 */

namespace Utils\ActiveMQ\ClientHelpers;

use Exception;
use Model\ProjectCreation\ProjectManager;
use Model\ProjectCreation\ProjectStructure;
use Predis\Response\Status;
use ReflectionException;
use Utils\ActiveMQ\WorkerClient;
use Utils\AsyncTasks\Workers\ProjectCreationWorker;
use Utils\Constants\ProjectStatus;
use Utils\Logger\LoggerFactory;
use Utils\Redis\RedisHandler;

/**
 * Class Enqueue
 * @package ProjectQueue
 *
 */
class ProjectQueue
{

    /**
     * @param ProjectStructure $projectStructure
     *
     * @throws Exception
     */
    public static function sendProject(ProjectStructure $projectStructure): void
    {
        try {
            WorkerClient::enqueue('PROJECT_QUEUE', ProjectCreationWorker::class, $projectStructure->toArray(), ['persistent' => WorkerClient::$_HANDLER->persistent]);
        } catch (Exception $e) {
            # Handle the error, logging, ...
            $output = "**** Project Enqueue failed. AMQ Connection Error. ****\n\t";
            $output .= "{$e->getMessage()}";
            $output .= var_export($projectStructure, true);
            LoggerFactory::doJsonLog($output);
            throw $e;
        }
    }

    /**
     * @throws ReflectionException
     */
    public static function getPublishedResults($id_project)
    {
        $redisHandler = (new RedisHandler())->getConnection();
        $response = json_decode($redisHandler->get(sprintf(ProjectStatus::PROJECT_QUEUE_HASH, $id_project)) ?? 'null', true);
        $redisHandler->disconnect();

        return $response;
    }

    /**
     * @throws ReflectionException
     */
    public static function publishResults(ProjectStructure $projectStructure): Status
    {
        $hashKey = sprintf(ProjectStatus::PROJECT_QUEUE_HASH, $projectStructure->id_project);

        return (new RedisHandler())->getConnection()->set($hashKey, json_encode($projectStructure->result), 'EX', 60 * 60 * 24 * 7); //store for 7 days

    }

}