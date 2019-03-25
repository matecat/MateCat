<?php

use TaskRunner\Commons\ContextList;
use TaskRunner\Commons\QueueElement;

class Utils {


    /**
     * Check for browser support
     *
     * @return bool
     */
    public static function isSupportedWebBrowser($browser_info) {
        $browser_name = strtolower( $browser_info[ 'name' ] );
        $browser_platform = strtolower( $browser_info[ 'platform' ] );

        foreach ( INIT::$ENABLED_BROWSERS as $enabled_browser ) {
            if ( stripos( $browser_name, $enabled_browser ) !== false ) {
                // Safari supported only on Mac
                if (stripos( "apple safari", $browser_name ) === false ||
                        (stripos( "apple safari", $browser_name ) !== false && stripos("mac", $browser_platform) !== false) )
                    return 1;
            }
        }

        foreach ( INIT::$UNTESTED_BROWSERS as $untested_browser ) {
            if ( stripos( $browser_name, $untested_browser ) !== false ) {
                return -1;
            }
        }

        // unsupported browsers: hack for home page
        if ($_SERVER[ 'REQUEST_URI' ]=="/") return -2;

        return 0;
    }

    static public function getBrowser() {
        $u_agent  = $_SERVER[ 'HTTP_USER_AGENT' ];

        //First get the platform?
        if ( preg_match( '/linux/i', $u_agent ) ) {
            $platform = 'linux';
        } elseif ( preg_match( '/macintosh|mac os x/i', $u_agent ) ) {
            $platform = 'mac';
        } elseif ( preg_match( '/windows|win32/i', $u_agent ) ) {
            $platform = 'windows';
        } else {
            $platform = 'Unknown';
        }

        // Next get the name of the useragent yes seperately and for good reason
        if ( preg_match( '/MSIE|Trident|Edge/i', $u_agent ) && !preg_match( '/Opera/i', $u_agent ) ) {
            $bname = 'Internet Explorer';
            $ub    = "MSIE";
        } elseif ( preg_match( '/Firefox/i', $u_agent ) ) {
            $bname = 'Mozilla Firefox';
            $ub    = "Firefox";
        } elseif ( preg_match( '/Chrome/i', $u_agent ) and !preg_match( '/OPR/i', $u_agent ) ) {
            $bname = 'Google Chrome';
            $ub    = "Chrome";
        } elseif ( preg_match( '/Opera|OPR/i', $u_agent ) ) {
            $bname = 'Opera';
            $ub    = "Opera";
        } elseif ( preg_match( '/Safari/i', $u_agent ) ) {
            $bname = 'Apple Safari';
            $ub    = "Safari";
        } elseif ( preg_match( '/AppleWebKit/i', $u_agent ) ) {
            $bname = 'Apple Safari';
            $ub    = "Safari";
        } elseif ( preg_match( '/Netscape/i', $u_agent ) ) {
            $bname = 'Netscape';
            $ub    = "Netscape";
        } elseif ( preg_match( '/Mozilla/i', $u_agent ) ) {
            $bname = 'Mozilla Generic';
            $ub    = "Mozillageneric";
        } else {
            $bname = 'Unknown';
            $ub    = "Unknown";
        }
        // finally get the correct version number
        $known   = array( 'Version', $ub, 'other' );
        $pattern = '#(?<browser>' . join( '|', $known ) . ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
        if ( !preg_match_all( $pattern, $u_agent, $matches ) ) {
            // we have no matching number just continue
        }

        // see how many we have
        $i = count( $matches[ 'browser' ] );
        if ( $i != 1 ) {
            //we will have two since we are not using 'other' argument yet
            //see if version is before or after the name
            if ( strripos( $u_agent, "Version" ) < strripos( $u_agent, $ub ) ) {
                $version = $matches[ 'version' ][ 0 ];
            } else {
                $version = @$matches[ 'version' ][ 1 ];
            }
        } else {
            $version = $matches[ 'version' ][ 0 ];
        }

        // check if we have a number
        if ( $version == null || $version == "" ) {
            $version = "?";
        }

        return array(
                'userAgent' => $u_agent,
                'name'      => $bname,
                'version'   => $version,
                'platform'  => $platform,
                'pattern'   => $pattern
        );
    }

    public static function friendly_slug($string) {
        // everything to lower and no spaces begin or end
        $string = strtolower(trim($string));

        //replace accent characters, depends your language is needed
        $string = Utils::replace_accents($string);

        // adding - for spaces and union characters
        $find = array(' ', '&', '\r\n', '\n', '+',',');
        $string = str_replace ($find, '-', $string);

        //delete and replace rest of special chars
        $find = array('/[^a-z0-9\-<>]/', '/[\-]+/', '/<[^>]*>/');
        $repl = array('', '-', '');
        $string = preg_replace ($find, $repl, $string);

        //return the friendly url
        return $string;
    }

    public static function replace_accents($var){ //replace for accents catalan spanish and more
        $a = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ');
        $b = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o');
        $var= str_replace($a, $b,$var);
        return $var;
    }

    public static function getGlobalMessage() {
        $retString = '';
        if ( file_exists( INIT::$ROOT . "/inc/.globalmessage.ini" ) ) {
            $globalMessage              = parse_ini_file( INIT::$ROOT . "/inc/.globalmessage.ini" );
            if( ( new DateTime( $globalMessage[ 'expire' ] ) )->getTimestamp() > time() ){
                $resObject = [
                        'msg'    => $globalMessage[ 'message' ],
                        'token'  => md5( $globalMessage[ 'message' ] ),
                        'expire' => ( new DateTime( $globalMessage[ 'expire' ] ) )->format( DateTime::W3C )
                ];
                $retString = json_encode( [ $resObject ] );
            }
        }
        return [ 'messages' => $retString ];
    }


    /**
     *
     *  TODO: this is a helper function to be used when transitioning to
     *  more decoupled implementations.
     */
    public static function userIsLogged() {
        return isset($_SESSION['uid']) ;
    }

    public static function encryptPass( $clear_pass, $salt ) {
        return sha1( $clear_pass . $salt );
    }

    public static function randomString( $maxlength = 15 ) {

        $_pwd = base64_encode( md5( uniqid( '', true ) ) ); //we want more characters not only [0-9a-f]
        $pwd  = substr( $_pwd, 0, 6 ) . substr( $_pwd, -8, 6 ); //exclude last 2 char2 because they can be == sign

        if ( $maxlength > 15 ) {
            while ( strlen( $pwd ) < $maxlength ) {
                $pwd .= self::randomString();
            }
            $pwd = substr( $pwd, 0, $maxlength );
        }

        return $pwd;

    }


    public static function mysqlTimestamp( $time ) {
        return date( 'Y-m-d H:i:s', $time );
    }

	public static function api_timestamp( $date_string ) {
        if ( $date_string == null ) {
            return null ;
        } else {
            $datetime = new \DateTime( $date_string );
            return $datetime->format( 'c' );
        }
    }

    public static function underscoreToCamelCase( $string ) {
        return str_replace( ' ', '', ucwords( str_replace( '_', ' ', $string ) ) );
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

    public static function createToken( $namespace = '' ) {

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

    /**
     * @param $token
     *
     * @return bool
     */
    public static function isTokenValid( $token = null ){
        if( empty( $token ) || !preg_match( '|^\{[A-F0-9]{8}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{12}\}$|', $token ) ){
            return false;
        }
        return true;
    }

    public static function isValidFileName( $fileUpName ) {

        if (
                stripos( $fileUpName, '../' ) !== false ||
                stripos( $fileUpName, '/../' ) !== false ||
                stripos( $fileUpName, '/..' ) !== false ||
                stripos( $fileUpName, '%2E%2E%2F' ) !== false ||
                stripos( $fileUpName, '%2F%2E%2E%2F' ) !== false ||
                stripos( $fileUpName, '%2F%2E%2E' ) !== false ||
                stripos( $fileUpName, '.' ) === 0 ||
                stripos( $fileUpName, '%2E' ) === 0
        ) {
            //Directory Traversal!
            return false;
        }

        return true;

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
     * @param bool $raise
     *
     * @throws Exception
     */
    public static function raiseJsonExceptionError( $raise = true ) {

        if ( function_exists( "json_last_error" ) ) {

            $error = json_last_error();

            switch ( $error ) {
                case JSON_ERROR_NONE:
                    $msg = null; # - No errors
                    break;
                case JSON_ERROR_DEPTH:
                    $msg = ' - Maximum stack depth exceeded';
                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    $msg = ' - Underflow or the modes mismatch';
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $msg =  ' - Unexpected control character found' ;
                    break;
                case JSON_ERROR_SYNTAX:
                    $msg = ' - Syntax error, malformed JSON' ;
                    break;
                case JSON_ERROR_UTF8:
                    $msg =  ' - Malformed UTF-8 characters, possibly incorrectly encoded';
                    break;
                default:
                    $msg =  ' - Unknown error';
                    break;
            }

            if( $raise && $error != JSON_ERROR_NONE ){
                Log::doLog( $msg );
                throw new Exception( $msg, $error );
            }

        }

    }

    //Array_column() is not supported on PHP 5.4, so i'll rewrite it
	public static function array_column( array $input, $column_key, $index_key = null ) {

        if ( function_exists( 'array_column' ) ) {
            return array_column( $input, $column_key, $index_key );
        }

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
     * @oaram $guid string
     * @param $file_name string optional file name to append to the upload path
     *
     * @return string
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

		if ( strtolower( $sug_source ) == 'matecat' ) {
		    // Enter this case if created_by is matecat, we show PUBLIC_TM
			$description = Constants::PUBLIC_TM ;

		} elseif( !empty( $sug_source ) && stripos( $sug_source, "MyMemory" ) === false ) {
		    // This case if for other sources from MyMemory that are public but we must
            // show the specific name of the source.
			$description = $sug_source;

		} elseif ( preg_match( "/[a-f0-9]{8,}/", $key ) ) { // md5 Key
			// This condition is for md5 keys
            $description = self::keyNameFromUserKeyring( $uid, $key ) ;

            if ( empty( $description ) ) {
                $description = self::getDefaultKeyDescription( $key, $job_tm_keys );
            }
		}

		if ( empty( $description ) ) {
		    $description = Constants::PUBLIC_TM ;
        }

		return $description;
	}

	public static function keyNameFromUserKeyring( $uid, $key ) {
	    if ( $uid === null ) {
	        return null ;
        }

        //check if the user can see the key.
        $memoryKey              = new TmKeyManagement_MemoryKeyStruct();
        $memoryKey->uid         = $uid;
        $memoryKey->tm_key      = new TmKeyManagement_TmKeyStruct();
        $memoryKey->tm_key->key = $key;

        $memoryKeyDao         = new TmKeyManagement_MemoryKeyDao( Database::obtain() );
        $currentUserMemoryKey = $memoryKeyDao->setCacheTTL( 3600 )->read( $memoryKey );
        if ( count( $currentUserMemoryKey ) >  0 ) {
            $currentUserMemoryKey = $currentUserMemoryKey[ 0 ];
            $name = trim($currentUserMemoryKey->tm_key->name);

            if ( empty($name) ) {
                $name = Constants::NO_DESCRIPTION_TM ;
            }

            return $name ;
        }

        return null ;
    }

    /**
     * Returns description for a key. If not found then default to "Private TM".
     *
     * @param $key
     * @param $job_tm_keys
     *
     * @return null|string
     */
	public static function getDefaultKeyDescription( $key, $job_tm_keys ){
		$ownerKeys = TmKeyManagement_TmKeyManagement::getOwnerKeys( array( $job_tm_keys ) );
		$description = Constants::NO_DESCRIPTION_TM ;

		//search the current key
		$currentKey = null;
		for ( $i = 0; $i < count( $ownerKeys ); $i++ ) {
		    $name = trim( $ownerKeys[ $i ]->name );

			if ( $ownerKeys[ $i ]->key == $key && !empty($name) )  {
				$description = $ownerKeys[ $i ]->name;
			}

		}

        if ( empty( $description ) ) {
            $description = Constants::NO_DESCRIPTION_TM ;
        }

		return $description ;
	}

}

