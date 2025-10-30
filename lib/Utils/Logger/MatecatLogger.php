<?php
/**
 * Created by PhpStorm.
 *
 * This class provides a logging utility that wraps around the Monolog Logger.
 * It allows logging of messages and objects, with a fallback mechanism to write
 * logs to a file in case of an exception.
 *
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * @date   28/08/25
 * @time   19:42
 */

namespace Utils\Logger;

use Exception;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Utils\Registry\AppConfig;

/**
 * Class MatecatLogger
 *
 * This class acts as a wrapper around the Monolog Logger, providing additional
 * functionality for logging structured data and handling exceptions during logging.
 *
 * @package Utils\Logger
 */
class MatecatLogger implements LoggerInterface {

    /**
     * @var Logger The Monolog Logger instance used for logging.
     */
    private Logger $logger;

    /**
     * Constructor for the MatecatLogger class.
     *
     * @param Logger $logger The Monolog Logger instance to be used.
     */
    public function __construct( Logger $logger ) {
        $this->logger = $logger;
    }

    /**
     * Logs a debug-level message with optional context.
     *
     * This method delegates the logging of a debug-level message to the `log` method,
     * passing the `Logger::DEBUG` level along with the provided message and context.
     *
     * @param mixed $message The debug message to log. Can be a string, array, or object.
     * @param array $context Additional context to include with the log. Defaults to an empty array.
     */
    public function debug( mixed $message, array $context = [] ): void {
        $this->log( Logger::DEBUG, $message, $context );
    }

    /**
     * Generates the full file path for the given log file name.
     *
     * @param string $fileName The name of the log file.
     *
     * @return string The full path to the log file.
     */
    protected static function getFileNamePath( string $fileName ): string {
        return AppConfig::$LOG_REPOSITORY . "/" . $fileName;
    }

    /**
     * Creates a new MatecatLogger instance with a specific logger name.
     *
     * @param string $name The name to be assigned to the logger.
     *
     * @return MatecatLogger A new MatecatLogger instance with the specified name.
     */
    public function withName( string $name ): MatecatLogger {
        return new MatecatLogger( $this->logger->withName( $name ) );
    }

    /**
     * @param mixed $message
     * @param array $context
     *
     * @return void
     */
    public function emergency( mixed $message, array $context = [] ): void {
        $this->log( Logger::EMERGENCY, $message, $context );
    }

    /**
     * @param mixed $message
     * @param array $context
     *
     * @return void
     */
    public function alert( mixed $message, array $context = [] ): void {
        $this->log( Logger::ALERT, $message, $context );
    }

    /**
     * @param mixed $message
     * @param array $context
     *
     * @return void
     */
    public function critical( mixed $message, array $context = [] ): void {
        $this->log( Logger::CRITICAL, $message, $context );
    }

    /**
     * @param mixed $message
     * @param array $context
     *
     * @return void
     */
    public function error( mixed $message, array $context = [] ): void {
        $this->log( Logger::ERROR, $message, $context );
    }

    /**
     * @param mixed $message
     * @param array $context
     *
     * @return void
     */
    public function warning( mixed $message, array $context = [] ): void {
        $this->log( Logger::WARNING, $message, $context );
    }

    /**
     * @param mixed $message
     * @param array $context
     *
     * @return void
     */
    public function notice( mixed $message, array $context = [] ): void {
        $this->log( Logger::NOTICE, $message, $context );
    }

    /**
     * @param mixed $message
     * @param array $context
     *
     * @return void
     */
    public function info( mixed $message, array $context = [] ): void {
        $this->log( Logger::INFO, $message, $context );
    }

    /**
     * Logs a message at a specific log level with optional context.
     *
     * This method formats the provided message and context into a structured array
     * using the `_formatMessage` method. It then attempts to log the formatted data
     * using the Monolog logger. If an exception occurs during the logging process,
     * the log data is written to a fallback file.
     *
     * @param mixed $level   The log level (e.g., DEBUG, INFO, ERROR).
     * @param mixed $message The message to log. Can be a string, array, or object.
     * @param array $context Additional context to include with the log. Defaults to an empty array.
     *
     */
    public function log( $level, $message, array $context = [] ): void {

        // Format the message and context into a structured array.
        $r = $this->_formatMessage( $message, $context );

        try {
            // Log the formatted message and context using the Monolog logger.
            $this->logger->log( $level, $r[ 'message' ], $r[ 'context' ] );
        } catch ( Exception ) {
            // If logging fails, write the log data to a fallback file.
            file_put_contents(
                    self::getFileNamePath( 'logging_configuration_exception.log' ),
                    json_encode( $r ) . PHP_EOL,
                    FILE_APPEND
            );
        }

    }

    /**
     * Formats a log message and its context into a structured array.
     *
     * This method checks if the provided message is structured (an array or object).
     * If the message is structured, it merges it with the provided context (if any)
     * and assigns a generic message "Log Entry:". Otherwise, the message is returned
     * as-is along with the context.
     *
     * @param mixed $message The log message, which can be a string, array, or object.
     * @param array $context Additional context to merge with the message. Defaults to an empty array.
     *
     * @return array An array containing the formatted message and context:
     *               - 'message': The formatted log message.
     *               - 'context': The merged or original context.
     */
    private function _formatMessage( mixed $message, array $context = [] ): array {

        // Determine if the message is structured (array or object).
        $isStructured = is_array( $message ) || is_object( $message );

        if ( $isStructured ) {
            // Merge the structured message with the context if the context is not empty.
            if ( !empty ( $context ) ) {
                $context = array_merge( (array)$message, $context );
            } else {
                $context = (array)$message;
            }
            // Assign a generic message for structured content.
            $message = 'Log Entry:';
        }

        // Return the formatted message and context as an array.
        return [ 'message' => $message, 'context' => $context ];

    }

}