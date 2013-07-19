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

		$stringDataInfo = "[$now ($ip)]";
		$stringDataInfo.= ( isset($trace[1]['class']) ? " " . $trace[1]['class'] ."->" : " " ) . $trace[1]['function']."(line:".$trace[0]['line'].")";
		$stringData = "$stringDataInfo : $string\n";

		fwrite($fh, $stringData);
		fclose($fh);
	}

        public static function hexDump($data, $newline = "\n") {
            
            static $from = '';
            static $to = '';

            static $width = 16; # number of bytes per line

            static $pad = '.'; # padding for non-visible characters

            if ($from === '') {
                for ($i = 0; $i <= 0xFF; $i++) {
                    $from .= chr($i);
                    $to .= ($i >= 0x20 && $i <= 0x7E) ? chr($i) : $pad;
                }
            }

            $hex = str_split(bin2hex($data), $width * 2);
            $chars = str_split(strtr($data, $from, $to), $width);

            $offset = 0;
            foreach ($hex as $i => $line) {
                //echo sprintf('%6X', $offset) . ' : ' . implode(' ', str_split($line, 2)) . ' [' . $chars[$i] . ']' . $newline;
                self::doLog( sprintf('%6X', $offset) . ' : ' . implode(' ', str_split($line, 2)) . ' [' . $chars[$i] . ']' . $newline );
                $offset += $width;
            }
            
            
         }

}
