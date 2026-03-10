<?php
/**
 * LogProcessor class
 *
 * This class extends the Monolog IntrospectionProcessor to add additional
 * contextual information to log records, such as IP address, a unique token hash,
 * and timestamp details.
 *
 * @package Utils\Logger
 * @author  Domenico Lupinetti (hashashiyyin) <domenico@translated.net>
 * @created 28/08/25
 * @time    17:23
 */

namespace Utils\Logger;

use Monolog\LogRecord;
use Monolog\Processor\IntrospectionProcessor;
use Utils\Tools\Utils;

/**
 * Class LogProcessor
 *
 * This class customizes the log processing by adding extra fields to the log record.
 */
class LogProcessor extends IntrospectionProcessor
{

    /**
     * Invokes the processor to modify the log record.
     *
     * @param LogRecord $record The log record to be processed.
     *
     * @return LogRecord The modified log record with additional contextual information.
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        // Call the parent processor to handle the base introspection logic.
        $record = parent::__invoke($record);

        // Add custom fields to the 'extra' section of the log record.
        $record['extra'] = [
            'ip' => Utils::getRealIpAddr() ?? gethostbyname(gethostname()), // Retrieve the real IP address or fallback to the hostname.
            "token_hash" => LoggerFactory::$uniqID, // Add a unique token hash for the log context.
            "context" => $record['extra'], // Include the original 'extra' context.
            "time" => time(), // Add the current timestamp.
            "date" => date(DATE_W3C), // Add the current date in W3C format.
        ];

        return $record; // Return the modified log record.
    }
}