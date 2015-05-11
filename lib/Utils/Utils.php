<?php

class Utils {

	public static function is_assoc($array) {
		return is_array($array) AND (bool) count(array_filter(array_keys($array), 'is_string'));
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

		include_once @INIT::$UTILS_ROOT . '/phpmailer/class.phpmailer.php';
		if( !class_exists( 'PHPMailer', false ) ){
			Log::doLog( 'Mailer Error: Class PHPMailer Not Found' );
			return false;
		}

		$mailConf = @parse_ini_file( INIT::$ROOT . '/inc/Error_Mail_List.ini', true );
		if( empty($mailConf) ){

			Log::doLog( "No eMail in configuration file found. Ensure that '" . INIT::$ROOT . "/inc/Error_Mail_List.inc' exists and contains a valid mail list. One per row." );
			Log::doLog( "Message not sent." );
			return false;

		} else {

			$mail = new PHPMailer();

			$mail->IsSMTP();
			$mail->Host       = $mailConf['server_configuration']['Host'];
			$mail->Port       = $mailConf['server_configuration']['Port'];
			$mail->Sender     = $mailConf['server_configuration']['Sender'];
			$mail->Hostname   = $mailConf['server_configuration']['Hostname'];

			$mail->From       = $mailConf['server_configuration']['From'];
			$mail->FromName   = $mailConf['server_configuration']['FromName'];
			$mail->ReturnPath = $mailConf['server_configuration']['ReturnPath'];
			$mail->AddReplyTo( $mail->ReturnPath, $mail->FromName );

			if( !empty($mailConf['email_list']) ){
				foreach( $mailConf['email_list'] as $email => $uName ){
					$mail->AddAddress( $email, $uName );
				}
			}

		}

		$mail->XMailer  = 'Translated Mailer';
		$mail->CharSet = 'UTF-8';
		$mail->IsHTML();

		/*
		 *
		 * "X-Priority",
		 *  "1″ This is the most common way of setting the priority of an email.
		 *  "3″ is normal, and "5″ is the lowest.
		 *  "2″ and "4″ are in-betweens, and frankly.
		 *
		 *  I’ve never seen anything but "1″ or "3″ used.
		 *
		 * Microsoft Outlook adds these header fields when setting a message to High priority:
		 *
		 * X-Priority: 1 (Highest)
		 * X-MSMail-Priority: High
		 * Importance: High
         *
		 */
		$mail->Priority = 1;

        if( empty( $subject ) ){
		    $mail->Subject = 'Alert from Matecat: ' . php_uname('n');
        } else {
            $mail->Subject = $subject . ' ' . php_uname('n');
        }

		$mail->Body    = '<pre>' . self::_getBackTrace() . "<br />" . $htmlContent . '</pre>';

		$txtContent = preg_replace(  '|<br[\x{20}/]*>|ui', "\n\n", $htmlContent );
		$mail->AltBody = strip_tags( $txtContent );

		$mail->MsgHTML($mail->Body);

		if(!$mail->Send()) {
			Log::doLog( 'Mailer Error: ' . $mail->ErrorInfo );
			Log::doLog( "Message could not be sent: \n\n" . $mail->AltBody );
			return false;
		}

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
                if( $fileName{0} == '.' ) continue;
                unlink( $fileInfo->getPathname() );
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

}

