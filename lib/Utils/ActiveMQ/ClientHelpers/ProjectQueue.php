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

    public const int RESULTS_TTL = 60 * 60 * 24 * 7;

    /**
     * Publishes a pending payload that carries the project password, then enqueues the creation.
     * The status endpoint answers only to the holder of that password, from the first poll on.
     *
     * @param ProjectStructure $projectStructure
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public static function sendProject(ProjectStructure $projectStructure): void
    {
        self::store($projectStructure->id_project, ['ppassword' => $projectStructure->ppassword, 'pending' => true]);

        try {
            WorkerClient::enqueue('PROJECT_QUEUE', ProjectCreationWorker::class, $projectStructure->toArray(), ['persistent' => WorkerClient::$_HANDLER->persistent]);
        } catch (Exception $e) {
            # Handle the error, logging, ...
            $output = "**** Project Enqueue failed. AMQ Connection Error. ****\n\t";
            $output .= "{$e->getMessage()}";
            $output .= var_export($projectStructure, true);
            LoggerFactory::getLogger('project_manager')->error($output);
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>|null
     * @throws ReflectionException
     * @throws Exception
     */
    public static function getPublishedResults(int $id_project): ?array
    {
        $redisHandler = (new RedisHandler())->getConnection();
        $response = json_decode($redisHandler->get(sprintf(ProjectStatus::PROJECT_QUEUE_HASH, $id_project)) ?? 'null', true);
        $redisHandler->disconnect();

        return $response;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public static function publishResults(ProjectStructure $projectStructure): ?Status
    {
        // A failed creation never reaches populateResult(): stamp the password so the error stays private too.
        $result              = $projectStructure->result;
        $result['ppassword'] = $projectStructure->ppassword;

        return self::store($projectStructure->id_project, $result);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws ReflectionException
     * @throws Exception
     */
    private static function store(?int $id_project, array $payload): ?Status
    {
        $redis    = (new RedisHandler())->getConnection();
        $response = $redis->set(sprintf(ProjectStatus::PROJECT_QUEUE_HASH, $id_project), json_encode($payload), 'EX', self::RESULTS_TTL);
        $redis->disconnect();

        return $response;
    }

}