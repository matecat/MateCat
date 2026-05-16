<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 12/12/16
 * Time: 19.16
 *
 */

namespace View\API\V2\Json;


use Exception;
use Model\ActivityLog\ActivityLogStruct;
use Model\FeaturesBase\FeatureSet;
use Model\FeaturesBase\Hook\Event\Filter\FilterActivityLogEntryEvent;
use Utils\Tools\Utils;

class Activity
{
    /**
     * @var ActivityLogStruct[]
     */
    private array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * @throws Exception
     */
    public function render(): array
    {
        $out = [];

        $featureSet = new FeatureSet();

        foreach ($this->data as $record) {
            if (!$record instanceof ActivityLogStruct) {
                continue;
            }

            if (empty($record->email)) {
                $record->first_name = "Anonymous";
                $record->last_name = "User";
                $record->email = "Unknown";
            }

            $filterActivityLogEntryEvent = new FilterActivityLogEntryEvent($record);
            $featureSet->dispatch($filterActivityLogEntryEvent);
            $filteredRecord = $filterActivityLogEntryEvent->getRecord();

            $formatted = [
                'id' => (int)$filteredRecord->ID,
                'action' => $filteredRecord->getAction($filteredRecord->action),
                'email' => $filteredRecord->email,
                'event_date' => Utils::api_timestamp($filteredRecord->event_date),
                'first_name' => $filteredRecord->first_name,
                'id_job' => (int)$filteredRecord->id_job,
                'id_project' => (int)$filteredRecord->id_project,
                'ip' => $filteredRecord->ip,
                'last_name' => $filteredRecord->last_name,
                'uid' => (int)$filteredRecord->uid
            ];

            $out[] = $formatted;
        }

        return $out;
    }

}
