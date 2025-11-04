<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/04/16
 * Time: 17.49
 *
 */

namespace Utils\Contribution;

use Exception;
use Utils\ActiveMQ\WorkerClient;
use Utils\AsyncTasks\Workers\GetContributionWorker;
use Utils\Logger\LoggerFactory;

/**
 * Class Set
 * @package Contribution
 *
 */
class Get
{

    /**
     * @param GetContributionRequest $contribution
     *
     * @throws Exception
     */
    public static function contribution(GetContributionRequest $contribution): void
    {
        try {
            WorkerClient::enqueue('CONTRIBUTION_GET', GetContributionWorker::class, $contribution->getArrayCopy(), ['persistent' => false]);
        } catch (Exception $e) {
            # Handle the error, logging, ...
            $output = "**** GetContribution failed. AMQ Connection Error. ****\n\t";
            $output .= "{$e->getMessage()}";
            $output .= var_export($contribution, true);
            LoggerFactory::doJsonLog($output);
            throw $e;
        }
    }

}