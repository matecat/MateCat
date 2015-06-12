<?php

if (PHP_SAPI != 'cli' || isset ( $_SERVER ['HTTP_HOST'] )) {
    die ( "This script can be run only in CLI Mode.\n\n" );
}

declare ( ticks = 1 );

ini_set("display_errors", E_ALL);
set_time_limit( 0 );

include '/var/www/cattool/inc/config.inc.php';
@Bootstrap::start();
include '/var/www/cattool/lib/Utils/Utils.php';
include '/var/www/cattool/lib/Utils/Log.php';

SpellCheckerServer::getInstance()->main();

class SpellCheckerServer {

    protected static $__INSTANCE = null;
    protected $logger;

    protected $server;
    protected $errNo;
    protected $errMsg;


    private static $cycles = 0;
    private static $MAX_CYCLES_NUM = 100;

    /**
     * @var mixed[ Process ]
     */
    protected $processList = array();

    // changed to FALSE by Signal Handler if kill SIGINT (2) / SIGTERM (15)received
    public static $RUNNING = true;
    public static $tHandlerPID;

    protected static $PID_PATH;
    protected static $SPELLCHECK_ROOT_PATH;
    protected static $SPELLCHECK_DICT_PATH;



    private static function __TimeStampMsg($msg) {
        echo "[" . date( DATE_RFC822 ) . "] " . $msg;
    }

    public static function sigSwitch($signo) {

        switch ($signo) {
            case SIGTERM :
            case SIGINT :
                self::$RUNNING = false;
                break;
            default :
                break;
        }

        Log::doLog( __METHOD__ . " Handled Signal $signo " );

    }

    protected function __construct( $logger = null ) {

        realpath(dirname(__FILE__) . '/../');

        self::$SPELLCHECK_ROOT_PATH = realpath(dirname(__FILE__) . '/../') . DIRECTORY_SEPARATOR;
        self::$SPELLCHECK_DICT_PATH = self::$SPELLCHECK_ROOT_PATH . "dictionaries/";
        self:: $PID_PATH = self::$SPELLCHECK_ROOT_PATH . 'service/.pid/';

        include( self::$SPELLCHECK_ROOT_PATH . 'service/Process.php' );

        //TODO More intelligent logger ( not only static )
        $this->logger = $logger;

        if ( self::$PID_PATH == null || touch( self::$PID_PATH . 'writeTest' ) === false ) {
            Log::doLog( "Attenzione, il path di backup dei messaggi falliti non è definito o non è permessa la scrittura.\n" . self::$PID_PATH . 'writeTest' );
            throw new Exception( "Attenzione, il path di backup dei messaggi falliti non è definito o non è permessa la scrittura." );
        } else {
            unlink( self::$PID_PATH . 'writeTest' );
        }

        self::$tHandlerPID = posix_getpid();

        $this->server = stream_socket_server("tcp://127.0.0.1:1337", $this->errNo, $this->errMsg);
        if ($this->server === false) {
            throw new UnexpectedValueException("Could not bind to socket: $this->errMsg");
        }

    }

    /**
     * Reset Database Connection every $MAX_CYCLES_NUM cycles
     *
     */
    private function __checkCycles(){
        self::$cycles ++;
        if( self::$cycles >= self::$MAX_CYCLES_NUM ){
            try{

                Log::doLog( "*** " . self::$MAX_CYCLES_NUM . " Cycles Achieved, destroying References and Re-create ***" );

                self::$cycles = 0;
            } catch( Exception $e ){
                Log::doLog( $e->getMessage() . "\n" . $e->getTraceAsString() );

            }
        }
    }

    /**
     * Singleton Pattern, Unique Instance of This
     *
     * @param bool $pidToFile
     *
     * @return SpellCheckerServer
     */
    public static function getInstance( $pidToFile = true ) {

        if ( self::$__INSTANCE === null ) {

            if( !extension_loaded("pcntl") && (bool)ini_get( "enable_dl" ) ){
                dl("pcntl.so");
            }

            if (! function_exists ( 'pcntl_signal' )) {
                $msg = "****** PCNTL EXTENSION NOT LOADED. KILLING THIS PROCESS COULD CAUSE UNPREDICTABLE ERRORS ******";
                Log::doLog( $msg );
                self::__TimeStampMsg( $msg."\n" );
            } else {

                pcntl_signal( SIGTERM, array ( 'SpellCheckerServer', 'sigSwitch' ) );
                pcntl_signal( SIGINT,  array ( 'SpellCheckerServer', 'sigSwitch' ) );
                pcntl_signal( SIGHUP,  array ( 'SpellCheckerServer', 'sigSwitch' ) );

                $msg = "-------------- Signal Handler Installed  --------------";
                Log::doLog( $msg );
                self::__TimeStampMsg( "$msg\n" );

            }

            self::$__INSTANCE = new SpellCheckerServer();

            if ( $pidToFile ) {
                self::$__INSTANCE->_myPidToFile();
            }

        }
        return self::$__INSTANCE;
    }

    public function main() {

        $this->_loadProcesses();

        $msg = "-------------- Starting Main Control --------------";
        Log::doLog( $msg );
        self::__TimeStampMsg( "$msg\n" );

        do {

            $this->__checkCycles();

            $client = @stream_socket_accept($this->server);

            if ($client) {
                $this->_handleClient( $client );
            }


        } while ( self::$RUNNING );


        $this->_destroyProcesses();

        stream_socket_shutdown($this->server, STREAM_SHUT_RDWR );

        $msg = "-------------- EXITING GRACEFULLY --------------";
        Log::doLog( $msg );
        self::__TimeStampMsg( "$msg\n" );

        $this->_delPidFile();

        $msg = "-------------- MAIN CONTROL HALTED. --------------";
        Log::doLog( $msg );
        self::__TimeStampMsg( "$msg\n" );

    }

    protected function _loadProcesses(){

        /**
         * @var $fileInfo DirectoryIterator
         */
        foreach ( new DirectoryIterator( self::$SPELLCHECK_DICT_PATH ) as $fileInfo ) {
            if( $fileInfo->isDot()) continue;

            $exec = 'hunspell -d ' . self::$SPELLCHECK_DICT_PATH . $fileInfo->getFilename() . DIRECTORY_SEPARATOR . $fileInfo->getFilename() . ' -a -p personal-' . $fileInfo->getFilename() . '.dic';
            $cwd  = self::$SPELLCHECK_DICT_PATH . $fileInfo->getFilename() . DIRECTORY_SEPARATOR;

            $this->processList[ $fileInfo->getFilename() ] = new Process( $exec, $cwd );

            Log::doLog( $exec );

            usleep(200000); //sleep 0.2 sec, avoid machine overload

        }

    }

    protected function _destroyProcesses(){

        /* @var $process Process */
        foreach ( $this->processList as $lang => $process ) {
            $process->close();
            Log::doLog( "Process unload " . $lang . " - Is still Running: " . var_export( $process->isRunning(), true ) );
            unset($process);
            unset($this->processList[$lang]);
        }

        Log::doLog( "Processes unload complete" );

    }

    /**
     *
     * @param $langCode
     *
     * @return Process
     */
    protected function _getProcess( $langCode ){
        $langCode = trim($langCode);
        return $this->processList[ $langCode ];
    }

    protected function _handleClient( $client ){
        $rawClientRequest = stream_get_contents( $client );
        $request = $this->_parseRequest( $rawClientRequest );
        $response = $this->_exec( $request, $client );
        stream_socket_sendto( $client, $response );
        stream_socket_shutdown($client, STREAM_SHUT_RDWR);
    }

    protected function _parseRequest( $clientRequest ){

        $clientRequest = trim($clientRequest);

        $request = array();
        list( $request['command'], $request['language'], $request['string'] ) = explode( "#@#", $clientRequest );

        return $request;

    }

    protected function _exec( $request, $client ){

        switch( $request['command'] ){
            case 'sug':
                break;
            case 'lint':
                break;
            case 'add':
                var_dump($request);
                $process = $this->_getProcess( $request['language'] );
                $clientRequest = "*{$request['string']}\n#\n";
                var_dump($clientRequest);
                Log::doLog($request['string']);
                fwrite( $process->pipes[0], $clientRequest );
                $response = 'OK';
                break;
            default:
                break;

        }

        return $response;
    }

    /**
     * return SingleTon Process ID
     */
    public function getPid() {

        return self::$tHandlerPID;
    }

    /**
     * Delete Process ID File
     *
     */
    protected function _delPidFile(){
        unlink( self::$PID_PATH . DIRECTORY_SEPARATOR . "MainControl_" . self::$tHandlerPID . ".pid" );
    }


    /**
     *
     *
     * Write Process ID To File
     *
     * @throws ; Exception
     */
    protected function _myPidToFile() {

        $fileName = self::$PID_PATH . DIRECTORY_SEPARATOR . "MainControl_" . self::$tHandlerPID . ".pid";
        $_fileHandle = @fopen( $fileName, 'wb' );
        if ( $_fileHandle === false ) {
            throw new Exception( "Can Not Open File '" . $fileName . "' . Error on: " . __METHOD__ . " in line: " . __LINE__ );
        }
        if ( fwrite( $_fileHandle, $this->getPid() ) === false ) {
            throw new Exception( "Can Not Write to File '" . $fileName . "' . Error on: " . __METHOD__ . " in line: " . __LINE__ );
        }
        fclose( $_fileHandle );
        return true;
    }

}
