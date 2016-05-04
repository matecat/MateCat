<?php
error_reporting( E_ALL );
define( 'DEBUG', 1 );

if ( !defined( 'LOG_REPOSITORY' ) ) {
    define( 'LOG_REPOSITORY', INIT::$LOG_REPOSITORY );
}

if ( !defined( 'LOG_FILENAME' ) ) {
    define( 'LOG_FILENAME', 'log.txt' );
}

// Be sure Monolog is installed via composer
if ( @include 'vendor/autoload.php' ) {
    Log::$useMonolog = true;
}

use Monolog\Logger;
use Monolog\Handler\RedisHandler;
use Monolog\Formatter\LogstashFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;

class Log {

    protected static $fileNamePath;

    /**
     * @var Monolog\Logger
     */
    protected static $logger;

    /**
     * @var bool
     */
    public static $useMonolog = false;

    public static $fileName;

    public static $uniqID;

    protected static function _writeTo( $stringData ) {

        if ( !file_exists( INIT::$LOG_REPOSITORY ) || !is_dir( INIT::$LOG_REPOSITORY ) ) {
            mkdir( INIT::$LOG_REPOSITORY );
        }

        if ( !empty( self::$fileName ) ) {
            self::$fileNamePath = LOG_REPOSITORY . "/" . self::$fileName;
        } else {
            self::$fileNamePath = LOG_REPOSITORY . "/" . LOG_FILENAME;
        }

        if ( self::$useMonolog ) {

            try {

//                if ( empty( self::$logger ) ) {
//
////                    $matecatRedisHandler = new \RedisHandler();
//
//                    // Init a RedisHandler with a LogstashFormatter.
//                    // The parameters may differ depending on your configuration of Redis.
//                    // Important: The parameter 'logs' must be equal to the key you defined
//                    // in your logstash configuration.
////                    $redisHandler      = new RedisHandler( $matecatRedisHandler->getConnection(), 'phplogs' );
////                    $logStashFormatter = new LogstashFormatter( 'MateCat', gethostname() );
////                    $redisHandler->setFormatter( $logStashFormatter );
//
//                    //Log on file
//                    $fileHandler   = new StreamHandler( self::$fileNamePath );
//                    $fileFormatter = new LineFormatter( null, null, true, true );
//                    $fileHandler->setFormatter( $fileFormatter );
//
//                    // Create a Logger instance with the RedisHandler
////                    self::$logger = new Logger( 'MateCat', array( $redisHandler, $fileHandler ) );
//                    self::$logger = new Logger( 'MateCat', array( $fileHandler ) );
//
//                }

                $fileHandler   = new StreamHandler( self::$fileNamePath );
                $fileFormatter = new LineFormatter( null, null, true, true );
                $fileHandler->setFormatter( $fileFormatter );
                self::$logger = new Logger( 'MateCat', array( $fileHandler ) );

                self::$logger->debug( rtrim( $stringData ) );

            } catch ( Exception $e ) {
                file_put_contents( self::$fileNamePath, $stringData, FILE_APPEND );
            }

        } else {
            file_put_contents( self::$fileNamePath, $stringData, FILE_APPEND );
        }

    }

    protected static function _getHeader() {

        $trace = debug_backtrace( 2 );

        $now = date( 'Y-m-d H:i:s' );

        $ip = Utils::getRealIpAddr();

        $stringDataInfo = "[$now ($ip)] " . self::$uniqID . " ";

        if ( isset( $trace[ 2 ][ 'class' ] ) ) {
            $stringDataInfo .= " " . $trace[ 2 ][ 'class' ] . "-> ";
        }

        if ( isset( $trace[ 2 ][ 'function' ] ) ) {
            $stringDataInfo .= $trace[ 2 ][ 'function' ] . " ";
        }

        $stringDataInfo .= "(line:" . $trace[ 1 ][ 'line' ] . ") : ";

        return $stringDataInfo;

    }

    public static function doLog() {

        if ( !INIT::$DEBUG ) {
            return;
        }

        $head = self::_getHeader();

        $string = "";
        $ct     = func_num_args(); // number of argument passed
        for ( $i = 0; $i < $ct; $i++ ) {
            $curr_arg = func_get_arg( $i ); // get each argument passed

            $tmp = explode( "\n", print_r( $curr_arg, true ) );
            foreach ( $tmp as $row ) {
                $string .= $head . $row . "\n";
            }

        }

        self::_writeTo( $string );

    }

    /**
     * Based on http://aidanlister.com/2004/04/viewing-binary-data-as-a-hexdump-in-php/
     * @author      Aidan Lister <aidan@php.net>
     * @author      Peter Waller <iridum@php.net>
     *
     * View any string as a hexdump.
     *
     * This is most commonly used to view binary data from streams
     * or sockets while debugging, but can be used to view any string
     * with non-viewable characters.
     *
     */
    public static function hexDump( $data, $htmloutput = false, $uppercase = true, $return = false ) {

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
                $ascii .= ( $htmloutput === true ) ?
                        htmlentities( $data[ $i ] ) :
                        $data[ $i ];
            } else {
                $ascii .= '.';
            }

            if ( $j === 7 ) {
                $hexi .= ' ';
                $ascii .= ' ';
            }


            if ( ++$j === 16 || $i === $len - 1 ) {
                //echo sprintf('%6X', $offset) . ' : ' . implode(' ', str_split($line, 2)) . ' [' . $chars[$i] . ']' . $newline;
                $dump .= sprintf( "%04$x  %-49s  %s", $offset, $hexi, $ascii );

                // Reset vars
                $hexi = $ascii = '';
                $offset += 16;
                $j = 0;

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
            self::_writeTo( self::_getHeader() . "\n" . $dump . "\n" );
        } else {
            return $dump;
        }

    }

    /**
     * Ugly workaround to reset the logger, so the method _writeTo re-initialize the logger configuration
     *
     */
    public static function resetLogger(){
        self::$logger = null;
    }

}
