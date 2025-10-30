<?php

namespace Utils\Logger;

use Exception;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Utils\Registry\AppConfig;

class LoggerFactory {

    const string LOG_FILENAME = 'general_log.txt';

    public static ?string $uniqID = null;

    public static ?string $requestID = null;

    /**
     * @var MatecatLogger[] Array of Logger instances indexed by filename
     */
    protected static array $loggersMap = [];

    /**
     * Writes data to the provided logger. If an exception occurs, writes the data to a fallback file.
     *
     * @param MatecatLogger $logger     The logger instance to write to.
     * @param mixed         $stringData The data to log.
     */
    protected static function _writeTo( MatecatLogger $logger, $stringData ): void {
        try {
            $logger->debug( $stringData );
        } catch ( Exception $e ) {
            file_put_contents( self::getFileNamePath( 'logging_configuration_exception.log' ), $stringData, FILE_APPEND );
        }
    }

    /**
     * Initializes a Monolog logger with a specific name and file.
     *
     * @param string $name     The name of the logger.
     * @param string $fileName The file where logs will be written.
     *
     * @return MatecatLogger The initialized logger instance.
     */
    protected static function initMonolog( string $name, string $fileName ): MatecatLogger {
        if ( !isset( self::$loggersMap[ $name ] ) ) {
            $streamHandler = new StreamHandler( self::getFileNamePath( $fileName ) );
            $fileFormatter = new JsonFormatter();
            $streamHandler->setFormatter( $fileFormatter );
            self::$loggersMap[ $name ] = new MatecatLogger( new Logger( $name, [ $streamHandler ], [ new LogProcessor( Logger::DEBUG, [ __NAMESPACE__ ] ) ] ) );
        }

        return self::$loggersMap[ $name ];
    }

    /**
     * Constructs the full path for a given log file name.
     *
     * @param string $fileName The name of the log file.
     *
     * @return string The full path to the log file.
     */
    protected static function getFileNamePath( string $fileName ): string {
        return AppConfig::$LOG_REPOSITORY . "/" . $fileName;
    }

    /**
     * Logs content in JSON format to a specified file.
     *
     * @param mixed       $content  The content to log.
     * @param string      $filename The name of the log file. Defaults to 'log.txt'.
     * @param string|null $logName  The name of the logger. Defaults to the filename.
     */
    public static function doJsonLog( mixed $content, string $filename = self::LOG_FILENAME, ?string $logName = null ): void {
        $logger = self::initMonolog( $logName ?? $filename, $filename );
        self::_writeTo( $logger, $content );
    }

    /**
     * Retrieves a logger instance by name and file.
     *
     * @param string|null $name     The name of the logger. Defaults to the filename.
     * @param string      $fileName The name of the log file. Defaults to 'log.txt'.
     *
     * @return MatecatLogger The logger instance.
     */
    public static function getLogger( ?string $name = null, string $fileName = self::LOG_FILENAME ): MatecatLogger {
        return self::initMonolog( $name ?? $fileName, $fileName );
    }

    /**
     * Sets aliases for a logger instance.
     *
     * @param array         $names  The aliases to set.
     * @param MatecatLogger $logger The logger instance to associate with the aliases.
     *
     * @throws Exception If an error occurs while setting aliases.
     */
    public static function setAliases( array $names, MatecatLogger $logger ): void {
        foreach ( $names as $name ) {
            self::$loggersMap[ $name ] = $logger->withName( $name );
        }
    }

    /**
     * Generates a hexdump of the provided data and optionally logs it.
     *
     * @param mixed $data       The data to generate a hexdump for.
     * @param bool  $htmloutput Whether to format the output as HTML. Defaults to false.
     * @param bool  $uppercase  Whether to use uppercase hex characters. Defaults to true.
     * @param bool  $return     Whether to return the hexdump as a string. Defaults to false.
     *
     * @return string|null The hexdump string if $return is true, otherwise null.
     * @codeCoverageIgnore
     */
    public static function hexDump( $data, $htmloutput = false, $uppercase = true, $return = false ): ?string {
        if ( is_array( $data ) ) {
            $data = print_r( $data, true );
        }

        $hexi   = '';
        $ascii  = '';
        $dump   = ( $htmloutput === true ) ? '<pre>' : '';
        $offset = 0;
        $len    = strlen( $data );

        $x = ( $uppercase === false ) ? 'x' : 'X';

        for ( $i = $j = 0; $i < $len; $i++ ) {
            $hexi .= sprintf( "%02$x ", ord( $data[ $i ] ) );

            // Replace non-viewable bytes with '.'
            if ( ord( $data[ $i ] ) >= 32 ) {
                $ascii .= ( $htmloutput === true ) ? htmlentities( $data[ $i ] ) : $data[ $i ];
            } else {
                $ascii .= '.';
            }

            if ( $j === 7 ) {
                $hexi  .= ' ';
                $ascii .= ' ';
            }

            if ( ++$j === 16 || $i === $len - 1 ) {
                $dump .= sprintf( "%04$x  %-49s  %s", $offset, $hexi, $ascii );

                // Reset vars
                $hexi   = $ascii = '';
                $offset += 16;
                $j      = 0;

                // Add newline
                if ( $i !== $len - 1 ) {
                    $dump .= "\n";
                }
            }
        }

        $dump .= $htmloutput === true ? '</pre>' : '';
        $dump .= "\n";

        // Output method
        if ( $return === false ) {
            $logger = self::initMonolog( 'hex_dump', 'hex_dump.log' );
            self::_writeTo( $logger, $dump . "\n" );

            return null;
        } else {
            return $dump;
        }
    }

    /**
     * Retrieves or generates a unique request ID.
     *
     * @return string The unique request ID.
     */
    public static function getRequestID(): string {
        if ( self::$requestID == null ) {
            self::$requestID = uniqid();
        }

        return self::$requestID;
    }
}