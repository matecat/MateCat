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
use Utils\Registry\AppConfig;

/**
 * Class MatecatLogger
 *
 * This class acts as a wrapper around the Monolog Logger, providing additional
 * functionality for logging structured data and handling exceptions during logging.
 *
 * @package Utils\Logger
 */
class MatecatLogger {

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
     * Logs the provided content. If the message is an array or object, it is
     * merged with the context (if provided) and converted to JSON before logging.
     * In case of an exception, the log is written to a fallback file.
     *
     * This method first determines if the message is structured (array or object).
     * If structured message is provided along with a context array, the two are merged.
     * The message is then converted to JSON if structured, or left as-is otherwise.
     * The logging operation is attempted using the Monolog logger. If an exception
     * occurs during logging, the message is written to a fallback log file.
     *
     * @param mixed $message The content to be logged. Can be a string, array, or object.
     * @param array $context Additional context to merge with the content. Defaults to an empty array.
     */
    public function log( $message, array $context = [] ) {
        $isStructured = is_array( $message ) || is_object( $message );

        if ( $isStructured ) {
            if ( !empty ( $context ) ) {
                $context = array_merge( (array)$message, $context );
            } else {
                $context = (array)$message;
            }
            $message = 'Log Entry:'; // Generic message when structured content is logged
        }

        try {
            // Log structured data with a generic message, or log string directly
            $this->logger->debug( $message, $context );
        } catch ( Exception $e ) {
            // On failure, write the log data to a fallback file
            file_put_contents(
                    self::getFileNamePath( 'logging_configuration_exception.log' ),
                    json_encode( [ 'message' => $message, 'context' => $context ] ) . PHP_EOL,
                    FILE_APPEND
            );
        }
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
     * Retrieves the underlying Monolog Logger instance.
     *
     * @return Logger The Monolog Logger instance.
     */
    public function getLogger(): Logger {
        return $this->logger;
    }

}