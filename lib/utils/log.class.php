<?php
error_reporting(E_ALL);

define('DEBUG', 1);

if (!defined('LOG_REPOSITORY')) {
    define('LOG_REPOSITORY', INIT::$LOG_REPOSITORY);
}

if (!defined('LOG_FILENAME')) {
    define('LOG_FILENAME', 'log.txt');
}

class Log {

    private static $filename;

    public static function doLog() { 

        $trace=debug_backtrace();


       
        
        if (!file_exists(LOG_REPOSITORY) || !is_dir(LOG_REPOSITORY)) {
            mkdir(LOG_REPOSITORY);
        }

        if (empty(Log::$filename)) {
            Log::$filename = LOG_REPOSITORY . "/" . LOG_FILENAME;
            $fh=@fopen(self::$filename, a);
            if (!$fh){
                unlink(self::$filename);      
            }else{
                fclose($fh);
            }
        }
      
        $string="";
        $ct = func_num_args(); // number of argument passed  
        for ($i=0; $i<$ct; $i++) {
            $curr_arg=func_get_arg($i); // get each argument passed  
            if (is_array($curr_arg)){
                $string.=print_r($curr_arg,true)." - ";
            }else{
                $string.="$curr_arg - ";
            }
        }  
        
        $string=rtrim($string, " -");//elimina l'ultimo -
        
        $fh = @fopen(self::$filename, 'a') or die("can't open file");
        $now = date('Y-m-d H:i:s');
        //$ip = gethostname(); // only for PHP 5.3
        $ip=php_uname('n');
        if (array_key_exists('REMOTE_ADDR',$_SERVER)) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

/*
if ($ip != "192.168.1.53"){
	fclose ($fh);
	return 0;
}
*/
        $stringDataInfo = "[$now ($ip)]";
        $stringDataInfo.=$trace[0]['class']."->".$trace[0]['function']."(line:".$trace[0]['line'].")";
        $stringData = "$stringDataInfo : $string\n";//. print_r ($trace,true);
        
       
      //  $stringData.=print_r($trace,true);
        fwrite($fh, $stringData);
        fclose($fh);
    }

}
