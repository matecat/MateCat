<?php

/**
 * Class Process
 *
 * Raise a process to Hunspell and read/write to pipes
 *
 */
class Process {

    public $resource;
    public $pid;
    public $pipes;
    protected $_data;
    protected $start_time;

    public function __construct( $executable, $cwd  ) {

        $descriptorSpec = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w')
        );

        $this->resource = proc_open( $executable, $descriptorSpec, $this->pipes, $cwd );

        $status = proc_get_status($this->resource);
        if($status === FALSE) {
            throw new Exception (sprintf(
                'Failed to obtain resource information '
            ));
        }
        $this->pid = $status['pid'];

        stream_set_blocking($this->pipes[0], 0);
        stream_set_blocking($this->pipes[1], 0);
        stream_set_blocking($this->pipes[2], 0);

        $this->start_time = time();

    }

    // is still running?
    public function isRunning() {
        $status = @proc_get_status( $this->resource );
        return ( empty( $status["running"] ) ? false : true );
    }

    public function getProcessInfo(){
        return proc_get_status($this->resource);
    }

    //execution time
    public function getExecutionTime() {
        return time() - $this->start_time;
    }

    public function write( $string ){
        Log::doLog(trim($string) . "\n");
        fwrite( $this->pipes[0], trim($string) . "\n" );
    }

    public function read(){

        $this->_data = '';
        $RUNNING = true;

        //poll for childs termination
        while( $RUNNING ) {

            if( !$this->isRunning() ) {
                $status = $this->getProcessInfo();
                $this->pid = -1;
                Log::doLog( "Child exited with code: {$status['exitcode']} ");
                return $status['exitcode'];
            }

            // read from childs stdout and stderr
            // avoid *forever* blocking through using a time out (50000usec)
            foreach( array(1, 2) as $desc) {

                // check stdout and stderr for data
                $read = array( $this->pipes[$desc] );
                $write = NULL;
                $except = NULL;
                $tv_sec = 0;
                $utv_sec = 50000;

                /*
                 * http://php.net/manual/en/function.stream-select.php
                 */
                $n = stream_select($read, $write, $except, $tv_sec, $utv_sec);

                if($n > 0) {

                    do {

                        $data = fread( $this->pipes[$desc], 8092 );

                        //log Errors
                        if( $desc == 2 ){
                            Log::doLog($data);
                        } else {
                            //append to data
                            $this->_data .= $data;
                        }

                        //hunspell does not kill connections on pipes, so check for last two chars
                        //if they are \n\n , close client connection
                        if( substr( $this->_data, -2 ) == "\n\n" ){
                            $RUNNING = false;
                        }

                    } while (strlen($data) > 0);

                }

            }

        }

        Log::doLog($this->_data);

        return $this->_data;

    }

    public function close(){
        fclose($this->pipes[0]);
        fclose($this->pipes[1]);
        fclose($this->pipes[2]);
        proc_close($this->resource);
    }

}