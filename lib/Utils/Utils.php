<?php

use Features\ReviewExtended\ReviewUtils as ReviewUtils;
use FilesStorage\FilesStorageFactory;
use Behat\Transliterator\Transliterator;

class Utils {

    public static function getSourcePageFromReferer() {
        return self::returnSourcePageAsInt( parse_url( @$_SERVER[ 'HTTP_REFERER' ] ) );
    }


    /**
     * @return int
     */
    public static function getSourcePage() {
        return self::returnSourcePageAsInt( parse_url( @$_SERVER[ 'REQUEST_URI' ] ) );
    }

    /**
     * @param array $url
     *
     * @return int
     */
    private static function returnSourcePageAsInt( $url ) {
        $sourcePage = Constants::SOURCE_PAGE_TRANSLATE;

        if ( !isset( $url[ 'path' ] ) ) {
            return $sourcePage;
        }

        // this regex matches /revise /revise[2-9]
        preg_match( '/revise([2-9]|\'\')?\//s', $url[ 'path' ], $matches );

        if ( count( $matches ) === 1 ) { // [0] => revise/
            $sourcePage = ReviewUtils::revisionNumberToSourcePage( Constants::SOURCE_PAGE_TRANSLATE );
        }

        if ( count( $matches ) > 1 ) { // [0] => revise2/ [1] => 2
            $sourcePage = ReviewUtils::revisionNumberToSourcePage( $matches[ 1 ] );
        }

        return $sourcePage;
    }

    /**
     * @return array
     */
    static public function getBrowser() {

        // handle Undefined index: HTTP_USER_AGENT
        if(!isset($_SERVER[ 'HTTP_USER_AGENT' ])){
            return [
                'userAgent' => null,
                'name'      => null,
                'version'   => null,
                'platform'  => null
            ];
        }

        $u_agent = $_SERVER[ 'HTTP_USER_AGENT' ];

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
        $known   = [ 'Version', $ub, 'other' ];
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

        return [
            'userAgent' => $u_agent,
            'name'      => $bname,
            'version'   => $version,
            'platform'  => $platform
        ];
    }

    public static function friendly_slug( $string ) {
        // everything to lower and no spaces begin or end
        $string = strtolower( trim( $string ) );

        //replace accent characters, depends your language is needed
        $string = Utils::replace_accents( $string );

        // adding - for spaces and union characters
        $find   = [ ' ', '&', '\r\n', '\n', '+', ',' ];
        $string = str_replace( $find, '-', $string );

        // transliterate string
        $string = Transliterator::transliterate($string);

        //delete and replace rest of special chars
        $find   = [ '/[^a-z0-9\-<>]/', '/[\-]+/', '/<[^>]*>/' ];
        $repl   = [ '', '-', '' ];
        $string = preg_replace( $find, $repl, $string );

        //return the friendly url
        return $string;
    }

    public static function replace_accents( $var ) { //replace for accents catalan spanish and more
        $a   = [
            'À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ',
            'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď',
            'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ',
            'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś',
            'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ',
            'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ'
        ];
        $b   = [
            'A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a',
            'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C',
            'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i',
            'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R',
            'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z',
            'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o'
        ];
        $var = str_replace( $a, $b, $var );

        return $var;
    }

    public static function getGlobalMessage() {
        $retString = '';
        if ( file_exists( INIT::$ROOT . "/inc/.globalmessage.ini" ) ) {
            $globalMessage = parse_ini_file( INIT::$ROOT . "/inc/.globalmessage.ini" );
            if ( ( new DateTime( $globalMessage[ 'expire' ] ) )->getTimestamp() > time() ) {
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

    public static function encryptPass( $clear_pass, $salt ) {
        $pepperedPass = hash_hmac( "sha256", $clear_pass . $salt, INIT::$AUTHSECRET );
        return password_hash( $pepperedPass, PASSWORD_DEFAULT );
    }

    public static function verifyPass( $clear_pass, $salt, $db_hashed_pass ) {
        $pepperedPass = hash_hmac( "sha256", $clear_pass . $salt, INIT::$AUTHSECRET );
        return password_verify( $pepperedPass, $db_hashed_pass );
    }

    /**
     * Generate 128bit password with real uniqueness over single process instance
     *   N.B. Concurrent requests can collide ( Ex: fork )
     *
     * Minimum Password Length 12 Characters
     *
     * WARNING: the obtained random string MUST NOT be used for security purposes
     *
     * @param int  $maxlength
     * @param bool $more_entropy
     *
     * @return bool|string
     */
    public static function randomString( $maxlength = 12, $more_entropy = false ) {

        $_pwd = md5( uniqid( '', true ) );

        if ( $more_entropy ) {
            $_pwd = base64_encode( $_pwd ); //we want more characters not only [0-9a-f]
        }

        $pwd = substr( $_pwd, 0, 6 ) . substr( $_pwd, -8, 6 ); //exclude last 2 char2 because they can be == sign

        if ( $maxlength > 12 ) {
            while ( strlen( $pwd ) < $maxlength ) {
                $pwd .= self::randomString();
            }
            $pwd = substr( $pwd, 0, $maxlength );
        }

        return $pwd;

    }

    /**
     * @param $string
     * @return bool
     */
    public static function isJson( $string ) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    public static function mysqlTimestamp( $time ) {
        return date( 'Y-m-d H:i:s', $time );
    }

    public static function api_timestamp( $date_string ) {
        if ( $date_string == null ) {
            return null;
        } else {
            $datetime = new \DateTime( $date_string );

            return $datetime->format( 'c' );
        }
    }

    public static function underscoreToCamelCase( $string ) {
        return str_replace( ' ', '', ucwords( str_replace( '_', ' ', $string ) ) );
    }

    /**
     * @param $string
     *
     * @return string
     */
    public static function trimAndLowerCase($string)
    {
        return trim(strtolower($string));
    }

    /**
     * Removes the empty elements from the end of an array
     *
     * @param array $array
     * @return array|mixed
     */
    public static function popArray(array $array)
    {
        if(end($array) === ''){
            array_pop($array);

            return self::popArray($array);
        }

        return $array;
    }

    /**
     * @param $params
     * @param $required_keys
     *
     * @return mixed
     * @throws Exception
     */
    public static function ensure_keys( $params, $required_keys ) {
        $missing = [];

        foreach ( $required_keys as $key ) {
            if ( !array_key_exists( $key, $params ) ) {
                $missing[] = $key;
            }
        }

        if ( count( $missing ) > 0 ) {
            throw new Exception( "Missing keys: " . implode( ', ', $missing ) );
        }

        return $params;
    }

    public static function is_assoc( $array ) {
        return is_array( $array ) and (bool)count( array_filter( array_keys( $array ), 'is_string' ) );
    }

    public static function curlFile( $filePath ) {
        $curlFile = "@$filePath";
        // CURLfile is available with PHP 5.5 and higher versions
        if ( version_compare( PHP_VERSION, '5.5.0' ) >= 0 ) {
            $curlFile = new CURLFile( $filePath );
        }

        return $curlFile;
    }

    public static function getRealIpAddr() {

        foreach ( [
                      'HTTP_CLIENT_IP',
                      'HTTP_X_FORWARDED_FOR',
                      'HTTP_X_FORWARDED',
                      'HTTP_X_CLUSTER_CLIENT_IP',
                      'HTTP_FORWARDED_FOR',
                      'HTTP_FORWARDED',
                      'REMOTE_ADDR'
                  ] as $key ) {
            if ( isset( $_SERVER[ $key ] ) ) {
                foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
                    if ( filter_var( trim( $ip ), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 ) !== false ) {
                        return $ip;
                    }
                }
            }
        }

    }

    public static function sendErrMailReport( $htmlContent, $subject = null ) {

        if ( !INIT::$SEND_ERR_MAIL_REPORT ) {
            return true;
        }

        $mailConf = @parse_ini_file( INIT::$ROOT . '/inc/Error_Mail_List.ini', true );

        if ( empty( $subject ) ) {
            $subject = 'Alert from MateCat: ' . php_uname( 'n' );
        } else {
            $subject .= ' ' . php_uname( 'n' );
        }

        $queue_element              = array_merge( [], $mailConf );
        $queue_element[ 'subject' ] = $subject;
        $queue_element[ 'body' ]    = '<pre>' . self::_getBackTrace() . "<br />" . $htmlContent . '</pre>';

        WorkerClient::init( new AMQHandler() );
        \WorkerClient::enqueue( 'MAIL', '\AsyncTasks\Workers\ErrMailWorker', $queue_element, [ 'persistent' => WorkerClient::$_HANDLER->persistent ] );

        Log::doJsonLog( 'Message has been sent' );

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

    /**
     * Alias of uuid4
     * @see Utils::uuid4()
     *
     * @return string|null
     * @throws Exception
     */
    public static function createToken() {
        return self::uuid4();
    }

    /**
     * Generate a valid UUID Version 4
     *
     * @see https://digitalbunker.dev/understanding-how-uuids-are-generated/
     * @see https://www.rfc-editor.org/rfc/rfc4122.html
     *
     * @return string|void
     * @throws Exception
     */
    public static function uuid4() {

        // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
        if ( PHP_MAJOR_VERSION >= 7 ) {
            $data = random_bytes( 16 );
        } else {
            $data = openssl_random_pseudo_bytes( 16 );
        }

        assert( strlen( $data ) == 16 );

        // Set version to 0100
        $data[ 6 ] = chr( ord( $data[ 6 ] ) & 0x0f | 0x40 );
        // Set bits 6-7 to 10
        $data[ 8 ] = chr( ord( $data[ 8 ] ) & 0x3f | 0x80 );

        // Output the 36 character UUID.
        return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
    }

    /**
     * @param $token
     *
     * @return bool
     */
    public static function isTokenValid( $token = null ) {
        if ( empty( $token ) || !preg_match( '|^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$|', $token ) ) {
            return false;
        }

        return true;
    }

    /**
     *
     * Remove Un-Wanted Chars from string nameexit
     *
     * @param (string) $string
     *
     * @return string
     * @throws Exception
     */
    public static function fixFileName( $stringName, $directory = null, $upCount = true ) {
        $string = filter_var( $stringName, FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_NO_ENCODE_QUOTES ] );
        while ( is_file( $directory . DIRECTORY_SEPARATOR . $string ) && $upCount ) {
            $string = static::upCountName( $string );
        }
        return $string;
    }

    protected function upCountNameCallback( $matches ) {
        $index = isset( $matches[ 1 ] ) ? intval( $matches[ 1 ] ) + 1 : 1;
        $ext   = isset( $matches[ 2 ] ) ? $matches[ 2 ] : '';
        return '_(' . $index . ')' . $ext;
    }

    protected static function upCountName( $name ) {
        return preg_replace_callback(
            '/(?:(?:_\(([\d]+)\))?(\.[^.]+))?$/', [ '\Utils', 'upCountNameCallback' ], $name, 1
        );
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

    /**
     * @param $arr
     *
     * @return mixed
     */
    public static function filterLangDetectArray( $arr ) {
        return filter_var( $arr, FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW ] );
    }

    /**
     * @param $dirPath
     */
    public static function deleteDir( $dirPath ) {

        if(is_dir($dirPath)){
            $iterator = new DirectoryIterator( $dirPath );

            foreach ( $iterator as $fileInfo ) {
                if ( $fileInfo->isDot() ) {
                    continue;
                }
                if ( $fileInfo->isDir() ) {
                    self::deleteDir( $fileInfo->getPathname() );
                } else {
                    $fileName = $fileInfo->getFilename();
                    if ( $fileName[0] == '.' ) {
                        continue;
                    }
                    $outcome = unlink( $fileInfo->getPathname() );
                    if ( !$outcome ) {
                        Log::doJsonLog( "fail deleting " . $fileInfo->getPathname() );
                    }
                }
            }
            rmdir( $iterator->getPath() );
        }
    }

    /**
     * Call the output in JSON format
     *
     * @param bool $raise
     *
     * @return null|string
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
                    $msg = ' - Unexpected control character found';
                    break;
                case JSON_ERROR_SYNTAX:
                    $msg = ' - Syntax error, malformed JSON';
                    break;
                case JSON_ERROR_UTF8:
                    $msg = ' - Malformed UTF-8 characters, possibly incorrectly encoded';
                    break;
                default:
                    $msg = ' - Unknown error';
                    break;
            }

            if ( $raise && $error != JSON_ERROR_NONE ) {
                throw new Exception( $msg, $error );
            } elseif ( $error != JSON_ERROR_NONE ) {
                return $msg;
            }

        }

    }

    //Array_column() is not supported on PHP 5.4, so i'll rewrite it
    public static function array_column( array $input, $column_key, $index_key = null ) {

        if ( function_exists( 'array_column' ) ) {
            return array_column( $input, $column_key, $index_key );
        }

        $result = [];
        foreach ( $input as $k => $v ) {
            $result[ $index_key ? $v[ $index_key ] : $k ] = $v[ $column_key ];
        }

        return $result;
    }

    // Previously in FileFormatConverter
    //remove UTF-8 BOM
    public static function stripFileBOM( $string, $utf = 8 ) {
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

    public static function stripBOM( $string ) {
        //PATCH TO FIX BOM INSERTIONS
        return str_replace( "\xEF\xBB\xBF", '', $string );
    }

    public static function isJobBasedOnMateCatFilters( $jobId ) {

        return true; //TODO refactory on the project creation to store info about which file is converted

        try {

            $fs    = FilesStorageFactory::create();
            $files = $fs->getFilesForJob( $jobId );
            foreach ( $files as $file ) {
                $fileType = \Matecat\XliffParser\XliffUtils\XliffProprietaryDetect::getInfo( $files[ 0 ][ 'xliffFilePath' ] );
                if ( $fileType[ 'proprietary_short_name' ] !== 'matecat_converter' ) {
                    // If only one XLIFF is not created with MateCat Filters, we can't say
                    // that the project is entirely based on new Filters
                    return false;
                }
            }

            // If the flow arrives here, all the files' XLIFFs are based on new Filters
            return true;

        } catch ( \Exception $e ) {
            $msg = " CRITICAL: " . $jobId . " has no files in storage... " . $e->getMessage();
            Log::doJsonLog( str_repeat( "*", strlen( $msg ) + 10 ) );
            Log::doJsonLog( "*****$msg*****" );
            Log::doJsonLog( str_repeat( "*", strlen( $msg ) + 10 ) );
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
    public static function uploadDirFromSessionCookie( $guid, $file_name = null ) {
        return INIT::$UPLOAD_REPOSITORY . "/" .
            $guid . '/' .
            $file_name;
    }

    /**
     * @param       $match
     * @param       $job_tm_keys
     * @param       $job_owner
     * @param       $uid
     *
     * @return null|string
     * @throws Exception
     */
    public static function changeMemorySuggestionSource( $match, $job_tm_keys, $job_owner, $uid ) {
        $sug_source = $match[ 'created_by' ];
        $key        = $match[ 'memory_key' ];

        if ( strtolower( $sug_source ) == 'matecat' ) {
            // Enter this case if created_by is matecat, we show PUBLIC_TM
            $description = Constants::PUBLIC_TM;

        } elseif ( !empty( $sug_source ) && stripos( $sug_source, "MyMemory" ) === false ) {
            // This case if for other sources from MyMemory that are public but we must
            // show the specific name of the source.
            $description = $sug_source;

        } elseif ( preg_match( "/[a-f0-9]{8,}/", $key ) ) { // md5 Key
            // This condition is for md5 keys
            $description = self::keyNameFromUserKeyring( $uid, $key );

            if ( empty( $description ) ) {
                $description = self::getDefaultKeyDescription( $key, $job_tm_keys );
            }
        }

        if ( empty( $description ) ) {
            $description = Constants::PUBLIC_TM;
        }

        return $description;
    }

    public static function keyNameFromUserKeyring( $uid, $key ) {
        if ( $uid === null ) {
            return null;
        }

        //check if the user can see the key.
        $memoryKey              = new TmKeyManagement_MemoryKeyStruct();
        $memoryKey->uid         = $uid;
        $memoryKey->tm_key      = new TmKeyManagement_TmKeyStruct();
        $memoryKey->tm_key->key = $key;

        $memoryKeyDao         = new TmKeyManagement_MemoryKeyDao( Database::obtain() );
        $currentUserMemoryKey = $memoryKeyDao->setCacheTTL( 3600 )->read( $memoryKey );
        if ( count( $currentUserMemoryKey ) > 0 ) {
            $currentUserMemoryKey = $currentUserMemoryKey[ 0 ];
            $name                 = trim( $currentUserMemoryKey->tm_key->name );

            if ( empty( $name ) ) {
                $name = Constants::NO_DESCRIPTION_TM;
            }

            return $name;
        }

        return null;
    }

    /**
     * Returns description for a key. If not found then default to "Private TM".
     *
     * @param $key
     * @param $job_tm_keys
     *
     * @return null|string
     */
    public static function getDefaultKeyDescription( $key, $job_tm_keys ) {
        $ownerKeys   = TmKeyManagement_TmKeyManagement::getOwnerKeys( [ $job_tm_keys ] );
        $description = Constants::NO_DESCRIPTION_TM;

        //search the current key
        $currentKey = null;
        for ( $i = 0; $i < count( $ownerKeys ); $i++ ) {
            $name = trim( $ownerKeys[ $i ]->name );

            if ( $ownerKeys[ $i ]->key == $key && !empty( $name ) ) {
                $description = $ownerKeys[ $i ]->name;
            }

        }

        if ( empty( $description ) ) {
            $description = Constants::NO_DESCRIPTION_TM;
        }

        return $description;
    }

    /**
     * stringsAreEqual
     *
     * This function needs to handle a special case. When old translation has been saved from a pre-translated XLIFF,
     * encoding is different than the one receiveed from the UI. Quotes are different for instance.
     *
     * So we compare the decoded version of the two strings. Should always work.
     *
     * @param $stringA
     * @param $stringB
     *
     * @return bool
     */
    public static function stringsAreEqual( $stringA, $stringB ) {
        $old = html_entity_decode( $stringA, ENT_XML1 | ENT_QUOTES );
        $new = html_entity_decode( $stringB, ENT_XML1 | ENT_QUOTES );

        return $new == $old;
    }

    /**
     * shortcut to htmlentities (UTF-8 charset)
     * avoiding double-encoding
     *
     * @param $string
     *
     * @return string
     */
    public static function htmlentitiesToUft8WithoutDoubleEncoding( $string ) {
        return htmlentities( $string, ENT_QUOTES, 'UTF-8', false );
    }

    /**
     * @param string $format
     * @return array
     */
    public static function allowedLanguages($format = 'rfc3066code')
    {
        $allowedLanguages = [];

        $file = \INIT::$UTILS_ROOT . '/Langs/supported_langs.json';
        $string = file_get_contents( $file );
        $langs = json_decode( $string, true );

        foreach ($langs['langs'] as $lang){
            $allowedLanguages[] = (isset($lang[$format])) ? $lang[$format] : $lang['rfc3066code'];
        }

        return $allowedLanguages;
    }

    /**
     * @param string $language
     * @param string $format
     * @return bool
     */
    public static function isValidLanguage($language, $format = 'rfc3066code')
    {
        $allowedLanguages = Utils::allowedLanguages($format);

        return in_array($language, $allowedLanguages);
    }

    /**
     * @param $rfc3066code
     * @return |null
     */
    public static function getLocalizedLanguage($rfc3066code)
    {
        $file = \INIT::$UTILS_ROOT . '/Langs/supported_langs.json';
        $string = file_get_contents( $file );
        $langs = json_decode( $string, true );

        foreach ($langs['langs'] as $lang){
            if($lang['rfc3066code'] === $rfc3066code){
                return @$lang['localized'][0]['en'];
            }
        }

        return null;
    }

    /**
     * @param string $phrase
     * @param int $max_words
     * @return mixed
     */
    public static function truncatePhrase($phrase, $max_words){

        $phrase_array = explode(' ',$phrase);
        if(count($phrase_array) > $max_words && $max_words > 0){
            $phrase = implode(' ',array_slice($phrase_array, 0, $max_words));
        }

        return $phrase;
    }

    /**
     * Examples:
     *
     * it-IT  ---> it
     * es-419 ---> es
     * to-TO ----> ton
     *
     * @param $rfc3066code
     * @return string|null
     */
    public static function convertLanguageToIsoCode($rfc3066code)
    {
        $file = \INIT::$UTILS_ROOT . '/Langs/supported_langs.json';
        $string = file_get_contents( $file );
        $langs = json_decode( $string, true );

        foreach ($langs['langs'] as $lang){
            if($rfc3066code === $lang['rfc3066code']){
                return $lang['isocode'];
            }
        }

        return null;
    }

    /**
     * This escape is need by
     * javascript JSON.parse() function
     *
     * @param array $data
     * @return string
     */
    public static function escapeJsonEncode($data){
        return str_replace("\\\"","\\\\\\\"", json_encode($data));
    }

    /**
     * This function strips html tag, but preserves hrefs.
     *
     * Example:
     *
     * This is the link: <a href="https://matecat.com">click here</a> -----> This is the link: click here(https://matecat.com)
     *
     * @param string $html
     * @return string
     */
    public static function stripTagsPreservingHrefs($html)
    {
        $htmlDom = new DOMDocument('1.0', 'UTF-8');
        $htmlDom->formatOutput = false;

        @$htmlDom->loadHTML($html);

        $links = $htmlDom->getElementsByTagName('a');

        /** @var DOMElement $link */
        foreach($links as $link){
            $linkLabel = $link->nodeValue;
            $linkHref = $link->getAttribute('href');
            $link->nodeValue = $linkLabel . "(".str_replace("\\\"","", $linkHref).")";
        }

        $html = $htmlDom->saveHtml($htmlDom->documentElement);
        $html = utf8_decode($html);

        $strippedHtml = strip_tags($html);
        $strippedHtml = ltrim($strippedHtml);
        $strippedHtml = rtrim($strippedHtml);

        return $strippedHtml;
    }

    /**
     * @param $email
     * @throws \Exception
     */
    public static function validateEmailAddress($email)
    {
        $clean_email = filter_var($email,FILTER_SANITIZE_EMAIL);

        if( $email !== $clean_email or !filter_var($email,FILTER_VALIDATE_EMAIL) ){
            throw new \Exception($email . " is not a valid email address");
        }
    }

    /**
     * @param $list
     * @return array
     */
    public static function validateEmailList($list){

        $aValid = [];
        foreach ( explode( ',', $list ) AS $sEmailAddress ) {
            $sEmailAddress = trim( $sEmailAddress );
            if( empty( $sEmailAddress ) ) continue;
            $aValid[ $sEmailAddress ] = filter_var( $sEmailAddress, FILTER_VALIDATE_EMAIL );
        }

        $invalidEmails = array_keys( $aValid, false );

        if( !empty( $invalidEmails ) ){
            throw new InvalidArgumentException( "Not valid e-mail provided: " . implode( ", ", $invalidEmails ), -6 );
        }

        return array_keys( $aValid );

    }
}
