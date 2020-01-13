<?php

namespace ConnectedServices\Google;

use Monolog\Formatter\FormatterInterface;

class GoogleClientLogsFormatter implements FormatterInterface {

    /**
     * Formats a log record.
     *
     * @param array $record A record to format
     *
     * @return mixed The formatted record
     */
    public function format( array $record ) {
        return json_encode($record) . PHP_EOL;
    }

    /**
     * Formats a set of log records.
     *
     * @param array $records A set of records to format
     *
     * @return mixed The formatted set of records
     */
    public function formatBatch(array $records)
    {
        foreach ($records as $key => $record) {
            $records[$key] = $this->format($record);
        }
        return $records;
    }
}
