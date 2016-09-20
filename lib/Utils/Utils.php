<?php

use TaskRunner\Commons\ContextList;
use TaskRunner\Commons\QueueElement;

class Utils {

	public static function api_timestamp( $date_string ) {
		$datetime = new \DateTime( $date_string );
		return $datetime->format( 'c' );
	}

    public static function underscoreToCamelCase($string) {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }

	/**
	 * @param $params
	 * @param $required_keys
	 *
	 * @return mixed
	 * @throws Exception
	 */
    public static function ensure_keys($params, $required_keys) {
        $missing = array();

        foreach( $required_keys as $key ) {
            if ( !array_key_exists($key, $params) ) {
                $missing[] = $key;
            }
        }

        if ( count($missing) > 0 ) {
            throw new Exception( "Missing keys: " . implode(', ', $missing) );
        }

        return $params ;
    }

	public static function is_assoc($array) {
		return is_array($array) AND (bool) count(array_filter(array_keys($array), 'is_string'));
	}

	public static function curlFile($filePath)
	{
		$curlFile =  "@$filePath";		
		// CURLfile is available with PHP 5.5 and higher versions
		if( version_compare(PHP_VERSION, '5.5.0') >= 0 ){ 
		    $curlFile = new CURLFile($filePath);
		}
		return $curlFile;
	}

	public static function curl_post($url, &$d, $opt = array()) {
		if (!self::is_assoc($d)) {
			throw new Exception("The input data to " . __FUNCTION__ . "must be an associative array", -1);
		}
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_USERAGENT, INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $d);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

		if (self::is_assoc($opt) and !empty($opt)) {
			foreach ($opt as $k => $v) {

				if (stripos($k, "curlopt_") === false or stripos($k, "curlopt_") !== 0) {
					$k = "curlopt_$k";
				}
				$const_name = strtoupper($k);
				if (defined($const_name)) {
					curl_setopt($ch, constant($const_name), $v);
				}
			}
		}

		$output = curl_exec($ch);

		$info = curl_getinfo($ch);

		//Log::doLog($d);
		//Log::doLog($output);

		curl_close($ch);

		return $output;
	}

	public static function getRealIpAddr() {

		foreach ( array(
					'HTTP_CLIENT_IP',
					'HTTP_X_FORWARDED_FOR',
					'HTTP_X_FORWARDED',
					'HTTP_X_CLUSTER_CLIENT_IP',
					'HTTP_FORWARDED_FOR',
					'HTTP_FORWARDED',
					'REMOTE_ADDR'
			       ) as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true) {
				foreach ( explode(',', $_SERVER[$key]) as $ip ) {
					if( filter_var( trim($ip), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4|FILTER_FLAG_IPV6 ) !== false ) {
                        return $ip;
                    }
				}
			}
		}

	}

	// multibyte string manipulation functions
	// source : http://stackoverflow.com/questions/9361303/can-i-get-the-unicode-value-of-a-character-or-vise-versa-with-php
	// original source : PHPExcel libary (http://phpexcel.codeplex.com/)

	// get the char from unicode code
	public static function unicode2chr($o) {
		if (function_exists('mb_convert_encoding')) {
			return mb_convert_encoding('&#' . intval($o) . ';', 'UTF-8', 'HTML-ENTITIES');
		} else {
			return chr(intval($o));
		}
	}

	public static function sendErrMailReport( $htmlContent, $subject = null ){

        if ( !INIT::$SEND_ERR_MAIL_REPORT ) {
          return true ;
        }

		$mailConf = @parse_ini_file( INIT::$ROOT . '/inc/Error_Mail_List.ini', true );

        if( empty( $subject ) ){
			$subject = 'Alert from MateCat: ' . php_uname('n');
        } else {
            $subject .= ' ' . php_uname('n');
        }

		$queue_element = array_merge( array(), $mailConf );
		$queue_element['subject'] = $subject;
		$queue_element['body'] = '<pre>' . self::_getBackTrace() . "<br />" . $htmlContent . '</pre>';

		WorkerClient::init( new AMQHandler() );
		\WorkerClient::enqueue( 'MAIL', '\AsyncTasks\Workers\ErrMailWorker', $queue_element, array( 'persistent' => WorkerClient::$_HANDLER->persistent ) );

		Log::doLog( 'Message has been sent' );
		return true;

	}

	protected static function _getBackTrace() {

		$trace = debug_backtrace();
		$now   = date( 'Y-m-d H:i:s' );

		$ip = Utils::getRealIpAddr();

		$stringDataInfo = "[$now (User IP: $ip)]";

		if ( isset( $trace[ 2 ][ 'class' ] ) ) {
			$stringDataInfo .= " " . $trace[ 2 ][ 'class' ] . "-> ";
		}
		if ( isset( $trace[ 2 ][ 'function' ] ) ) {
			$stringDataInfo .= $trace[ 2 ][ 'function' ] . " ";
		}
		$stringDataInfo .= "(line:" . $trace[ 1 ][ 'line' ] . ")";

		return $stringDataInfo;

	}

    public static function getPHPVersion() {
        // PHP_VERSION_ID is available as of PHP 5.2.7, if our
        // version is lower than that, then emulate it
        if (! defined ( 'PHP_VERSION_ID' )) {
            $version = explode ( '.', PHP_VERSION );

            define ( 'PHP_VERSION_ID', ($version [0] * 10000 + $version [1] * 100 + $version [2]) );
        }

        // PHP_VERSION_ID is defined as a number, where the higher the number
        // is, the newer a PHP version is used. It's defined as used in the above
        // expression:
        //
        // $version_id = $major_version * 10000 + $minor_version * 100 + $release_version;
        //
        // Now with PHP_VERSION_ID we can check for features this PHP version
        // may have, this doesn't require to use version_compare() everytime
        // you check if the current PHP version may not support a feature.
        //
        // For example, we may here define the PHP_VERSION_* constants thats
        // not available in versions prior to 5.2.7


        if (PHP_VERSION_ID < 50207) {
            define ( 'PHP_MAJOR_VERSION', $version [0] );
            define ( 'PHP_MINOR_VERSION', $version [1] );
            define ( 'PHP_RELEASE_VERSION', $version [2] );
        }

    }

    public static function create_guid( $namespace = '' ) {

        static $guid = '';
        $uid  = uniqid( "", true );
        $data = $namespace;
        $data .= $_SERVER[ 'REQUEST_TIME' ];
        $data .= @$_SERVER[ 'HTTP_USER_AGENT' ];

        if ( isset( $_SERVER[ 'LOCAL_ADDR' ] ) ) {
            $data .= $_SERVER[ 'LOCAL_ADDR' ]; // Windows only
        }

        if ( isset( $_SERVER[ 'LOCAL_PORT' ] ) ) {
            $data .= $_SERVER[ 'LOCAL_PORT' ]; // Windows only
        }

        $data .= $_SERVER[ 'REMOTE_ADDR' ];
        $data .= $_SERVER[ 'REMOTE_PORT' ];
        $hash = strtoupper( hash( 'ripemd128', $uid . $guid . md5( $data ) ) );

        $guid = '{' .
                substr( $hash, 0, 8 ) .
                '-' .
                substr( $hash, 8, 4 ) .
                '-' .
                substr( $hash, 12, 4 ) .
                '-' .
                substr( $hash, 16, 4 ) .
                '-' .
                substr( $hash, 20, 12 ) .
                '}';

        \Log::doLog('created GUID', $guid ); 

        return $guid;
    }

    public static function filterLangDetectArray( $arr ) {
        return filter_var( $arr, FILTER_SANITIZE_STRING, array( 'flags' => FILTER_FLAG_STRIP_LOW ) );
    }

    public static function deleteDir( $dirPath ) {

        $iterator = new DirectoryIterator( $dirPath );

        foreach ( $iterator as $fileInfo ) {
            if ( $fileInfo->isDot() ) {
                continue;
            }
            if ( $fileInfo->isDir() ) {
                self::deleteDir( $fileInfo->getPathname() );
            } else {
                $fileName = $fileInfo->getFilename();
                if ( $fileName{0} == '.' ) {
                    continue;
                }
                $outcome = unlink( $fileInfo->getPathname() );
                if ( !$outcome ) {
                    Log::doLog( "fail deleting " . $fileInfo->getPathname() );
                }
            }
        }
        rmdir( $iterator->getPath() );

    }

    /**
     * Call the output in JSON format
     *
     */
    public static function raiseJsonExceptionError() {

        if ( function_exists( "json_last_error" ) ) {
            switch ( json_last_error() ) {
                case JSON_ERROR_NONE:
//              	  Log::doLog(' - No errors');
                    break;
                case JSON_ERROR_DEPTH:
                    $msg = ' - Maximum stack depth exceeded';
                    Log::doLog( $msg );
                    throw new Exception( $msg, JSON_ERROR_DEPTH);
                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    $msg = ' - Underflow or the modes mismatch';
                    Log::doLog( $msg );
                    throw new Exception( $msg, JSON_ERROR_STATE_MISMATCH);
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $msg =  ' - Unexpected control character found' ;
                    Log::doLog( $msg );
                    throw new Exception( $msg, JSON_ERROR_CTRL_CHAR);
                    break;
                case JSON_ERROR_SYNTAX:
                    $msg = ' - Syntax error, malformed JSON' ;
                    Log::doLog( $msg );
                    throw new Exception( $msg, JSON_ERROR_SYNTAX);
                    break;
                case JSON_ERROR_UTF8:
                    $msg =  ' - Malformed UTF-8 characters, possibly incorrectly encoded';
                    Log::doLog( $msg );
                    throw new Exception( $msg, JSON_ERROR_UTF8);
                    break;
                default:
                    $msg =  ' - Unknown error';
                    Log::doLog( $msg );
                    throw new Exception( $msg, 6);
                    break;
            }
        }

    }

	public static function array_column( array $input, $column_key, $index_key = null ) {

		$result = array();
		foreach ( $input as $k => $v ) {
			$result[ $index_key ? $v[ $index_key ] : $k ] = $v[ $column_key ];
		}

		return $result;
	}

	public static function getServerRootUrl() {
    $s = $_SERVER['HTTPS'] === 'on' ? 's' : '';
    $protocol = strtolower(substr($_SERVER['SERVER_PROTOCOL'], 0, strpos($_SERVER['SERVER_PROTOCOL'], '/'))).$s;
    $port = ($_SERVER['SERVER_PORT'] == '80') ? '' : (':'.$_SERVER['SERVER_PORT']);
    return $protocol.'://'.$_SERVER['SERVER_NAME'].$port;
	}

	// Previously in FileFormatConverter
	//remove UTF-8 BOM
	public static function stripBOM( $string, $utf = 8 ) {
		//depending on encoding, different slices are to be cut
		switch ( $utf ) {
			case 16:
				$string = substr( $string, 2 );
				break;
			case 32:
				$string = substr( $string, 4 );
				break;
			case 8:
			default:
				$string = substr( $string, 3 );
				break;
		}

		return $string;
	}

	public static function isJobBasedOnMateCatFilters($jobId) {

		try {

			$fs    = new FilesStorage();
			$files = $fs->getFilesForJob( $jobId, null );
			foreach ( $files as $file ) {
				$fileType = DetectProprietaryXliff::getInfo( $files[ 0 ][ 'xliffFilePath' ] );
				if ( $fileType[ 'proprietary_short_name' ] !== 'matecat_converter' ) {
					// If only one XLIFF is not created with MateCat Filters, we can't say
					// that the project is entirely based on new Filters
					return false;
				}
			}

			// If the flow arrives here, all the files' XLIFFs are based on new Filters
			return true;

		} catch (\Exception $e ){
			$msg = " CRITICAL: " . $jobId . " has no files in storage... " . $e->getMessage();
			Log::doLog( str_repeat("*", strlen( $msg ) + 10 ) );
			Log::doLog( "*****$msg*****" );
			Log::doLog( str_repeat("*", strlen( $msg ) + 10 ) );
		}

	}

    /**
     * uploadDirFromSessionCookie 
     *
     * @param $file_name optional file name to append to the upload path
     */
    public static function uploadDirFromSessionCookie($guid, $file_name = null) {
        return INIT::$UPLOAD_REPOSITORY . "/" . 
            $guid . '/' . 
            $file_name ; 
    }
	/**
	 * @param      $match
	 * @param      $job_tm_keys
	 * @param      $job_owner
	 * @param 	   $uid
	 *
	 * @return null|string
	 * @throws Exception
	 */
	public static function changeMemorySuggestionSource( $match, $job_tm_keys, $job_owner, $uid){
		$sug_source = $match[ 'created_by' ];
		$key        = $match[ 'memory_key' ];

		//suggestion is coming from a public TM
		if ( $sug_source == 'Matecat' ) {

			$description = "Public TM";

		} elseif( !empty( $sug_source ) && stripos( $sug_source, "MyMemory" ) === false ) {

			$description = $sug_source;

		} elseif ( preg_match( "/[a-f0-9]{8,}/", $key ) ) { // md5 Key

			//MyMemory returns the key of the match

			if ( $uid !== null ) { //user is logged and uid is set

				//check if the user can see the key.
				$memoryKey              = new TmKeyManagement_MemoryKeyStruct();
				$memoryKey->uid         = $uid;
				$memoryKey->tm_key      = new TmKeyManagement_TmKeyStruct();
				$memoryKey->tm_key->key = $key;

				$memoryKeyDao         = new TmKeyManagement_MemoryKeyDao( Database::obtain() );
				$currentUserMemoryKey = $memoryKeyDao->setCacheTTL( 3600 )->read( $memoryKey );

				if ( count( $currentUserMemoryKey ) > 0 ) {

					//the current user owns the key: show its description
					$currentUserMemoryKey = $currentUserMemoryKey[ 0 ];
					$description          = $currentUserMemoryKey->tm_key->name;

				}

			}

		}

		/**
		 * if the description is empty, get cascading default descriptions
		 */
		if ( empty( $description ) ) {
			$description = self::getDefaultKeyDescription( $key, $job_tm_keys, $job_owner );
		}

		if ( empty( $description ) ) {
			$description = "No description available"; //this should never be
		}

		return $description;
	}

	/**
	 * if the description is empty, get cascading default descriptions
	 *
	 * First get the job key description, if empty, get the job owner email
	 *
	 * @param $key
	 *
	 * @return null|string
	 * @throws Exception
	 */
	public static function getDefaultKeyDescription( $key, $job_tm_keys, $job_owner ){

		$description = null;

		$ownerKeys = TmKeyManagement_TmKeyManagement::getOwnerKeys( array( $job_tm_keys ) );

		//search the current key
		$currentKey = null;
		for ( $i = 0; $i < count( $ownerKeys ); $i++ ) {
			if ( $ownerKeys[ $i ]->key == $key ) {
				$description = $ownerKeys[ $i ]->name;
			}
		}

		//return if something was found, avoid other computations
		if ( !empty( $description ) ) return $description;

		return $job_owner;
	}

    /**
     * @param $params
     * @return string
     */
	public static function buildQueryString( $params ) {
        $querystring = implode('&', array_map(function($key, $value) {
            return "$key=" . urlencode( $value ) ;
        }, array_keys( $params ), $params ));

        return $querystring ;
    }

}

