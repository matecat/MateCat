<?php

use Behat\Transliterator\Transliterator;
use Features\ReviewExtended\ReviewUtils as ReviewUtils;
use Model\Database;
use Model\TmKeyManagement\MemoryKeyDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use Utils\AsyncTasks\Workers\ErrMailWorker;
use Utils\TmKeyManagement\TmKeyStruct;
use Utils\TmKeyManagement\TmKeyManager;

class Utils {

    public static function getSourcePageFromReferer(): int {
        return self::returnSourcePageAsInt( parse_url( $_SERVER[ 'HTTP_REFERER' ] ?? null ) );
    }


    /**
     * @return int
     */
    public static function getSourcePage(): int {
        return self::returnSourcePageAsInt( parse_url( $_SERVER[ 'REQUEST_URI' ] ?? null ) );
    }

    /**
     * @param array $url
     *
     * @return int
     */
    private static function returnSourcePageAsInt( array $url ): int {
        $sourcePage = Constants::SOURCE_PAGE_TRANSLATE;

        if ( !isset( $url[ 'path' ] ) ) {
            return $sourcePage;
        }

        // this regex matches /revise /revise[2-9]
        preg_match( '/revise([2-9]|\'\')?\//', $url[ 'path' ], $matches );

        if ( count( $matches ) === 1 ) { // [0] => revise/
            $sourcePage = ReviewUtils::revisionNumberToSourcePage( Constants::SOURCE_PAGE_TRANSLATE );
        }

        if ( count( $matches ) > 1 ) { // [0] => revise2/ [1] => 2
            $sourcePage = ReviewUtils::revisionNumberToSourcePage( $matches[ 1 ] );
        }

        return $sourcePage;
    }

    /**
     * @param string|null $agent
     *
     * @return array
     */
    static public function getBrowser( ?string $agent = null ): array {

        // handle Undefined index: HTTP_USER_AGENT
        if ( !isset( $_SERVER[ 'HTTP_USER_AGENT' ] ) && empty( $agent ) ) {
            return [
                    'userAgent' => null,
                    'name'      => null,
                    'version'   => null,
                    'platform'  => null
            ];
        }

        $u_agent = $agent;
        if ( empty( $u_agent ) ) {
            $u_agent = $_SERVER[ 'HTTP_USER_AGENT' ];
        }

        //First get the platform?
        if ( preg_match( '/linux/i', $u_agent ) ) {
            $platform = 'Linux';
            if ( preg_match( '/android/i', $u_agent ) ) {
                $platform = 'Android';
            }
        } elseif ( preg_match( '/macintosh|mac os x/i', $u_agent ) ) {
            $platform = 'MacOSX';
            if ( preg_match( '/iphone/i', $u_agent ) ) {
                $platform = 'iOS';
            } elseif ( preg_match( '/ipad/i', $u_agent ) ) {
                $platform = 'ipadOS';
            }
        } elseif ( preg_match( '/windows|win32/i', $u_agent ) ) {
            $platform = 'Windows';
            if ( preg_match( '/phone/i', $u_agent ) ) {
                $platform = 'Windows Phone';
            }
        } else {
            $platform = 'Unknown';
        }

        // Next get the name of the useragent, yes separately and for good reason
        if ( preg_match( '/MSIE/i', $u_agent ) && !preg_match( '/Opera|OPR/i', $u_agent ) ) {
            $browserName = 'Internet Explorer';
            $ub          = "MSIE";
        } elseif ( preg_match( '|Edg.*?/|i', $u_agent ) && $platform != 'ipadOS' ) {
            $browserName = 'Microsoft Edge';
            $ub          = "Edg.*?";
        } elseif ( preg_match( '/Trident/i', $u_agent ) || preg_match( '/IEMobile/i', $u_agent ) ) {
            $browserName = 'Internet Explorer Mobile';
            $ub          = "IEMobile";
        } elseif ( preg_match( '/Firefox/i', $u_agent ) ) {
            $browserName = 'Mozilla Firefox';
            $ub          = "Firefox";
        } elseif ( preg_match( '/Chrome/i', $u_agent ) and !preg_match( '/Opera|OPR/i', $u_agent ) ) {
            $browserName = 'Google Chrome';
            $ub          = "Chrome";
        } elseif ( preg_match( '/Opera|OPR/i', $u_agent ) ) {
            $browserName = 'Opera';
            $ub          = "Opera";
        } elseif ( preg_match( '/Safari/i', $u_agent ) || preg_match( '/applewebkit.*\(.*khtml.*like.*gecko.*\).*mobile.*$/i', $u_agent ) ) {
            $browserName = 'Apple Safari';
            $ub          = "Safari|Version";
            if ( $platform == 'iOS' || preg_match( '/Mobile/i', $u_agent ) ) {
                $browserName = 'Mobile Safari';
            }
        } else {
            $browserName = 'Unknown';
            $ub          = "Unknown";
        }
        // finally, get the correct version number
        $known   = [ 'Version', $ub, 'other' ];
        $pattern = '#(?<browser>' . join( '|', $known ) . ')[/ ]+(?<version>[0-9.|a-zA-Z._]*)#i';
        if ( !preg_match_all( $pattern, $u_agent, $matches ) ) {
            // we have no matching number, continue
        }

        // see how many we have
        $i = count( $matches[ 'browser' ] );
        if ( $i > 1 ) {

            //we will have two since we are not using 'other' argument yet
            //see if the version is before or after the name
            //if it is before then use the name's version
            if ( strtolower( $matches[ 'browser' ][ 0 ] ) == 'version' && strtolower( $matches[ 'browser' ][ 1 ] ) != 'safari' ) {
                $version = $matches[ 'version' ][ 1 ] ?? null;
            } else {
                $version = $matches[ 'version' ][ 0 ] ?? null;
            }

        } else {
            $version = $matches[ 'version' ][ 0 ] ?? null;
        }

        // check if we have a number
        if ( $version == null || $version == "" ) {
            $version = "?";
        }

        return [
                'userAgent' => $u_agent,
                'name'      => $browserName,
                'version'   => $version,
                'platform'  => $platform
        ];
    }

    /**
     * @param $string
     *
     * @return string
     */
    public static function friendly_slug( $string ): string {
        // everything to lower and no spaces begin or end
        $string = strtolower( trim( $string ) );

        //replace accent characters, depends on your language is needed
        $string = Utils::replace_accents( $string );

        // adding - for spaces and union characters
        $find   = [ ' ', '&', '\r\n', '\n', '+', ',' ];
        $string = str_replace( $find, '-', $string );

        // transliterate string
        $string = Transliterator::transliterate( $string );

        // avoid empty strings
        if(empty($string)){
            $string = "-";
        }

        //delete and replace rest of special chars
        $find = [ '/[^a-z0-9\-<>]/', '/-+/', '/<[^>]*>/' ];
        $repl = [ '', '-', '' ];

        //return the friendly url
        return preg_replace( $find, $repl, $string );
    }

    public static function replace_accents( $var ): string { //replace it for accents catalan spanish and more
        $a = [
                'À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ',
                'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď',
                'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ',
                'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś',
                'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ',
                'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ'
        ];
        $b = [
                'A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a',
                'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C',
                'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i',
                'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R',
                'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z',
                'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o'
        ];

        return str_replace( $a, $b, $var );
    }

    public static function encryptPass( $clear_pass, $salt ): string {
        $pepperedPass = hash_hmac( "sha256", $clear_pass . $salt, INIT::$AUTHSECRET );

        return password_hash( $pepperedPass, PASSWORD_DEFAULT );
    }

    public static function verifyPass( $clear_pass, $salt, $db_hashed_pass ): bool {
        $pepperedPass = hash_hmac( "sha256", $clear_pass . $salt, INIT::$AUTHSECRET );

        return password_verify( $pepperedPass, $db_hashed_pass );
    }

    /**
     * Generate 128bit password with real uniqueness over single process instance
     *   N.B. Concurrent requests can collide (Ex: fork)
     *
     * Minimum Password Length of 12 Characters
     *
     * WARNING: the obtained random string MUST NOT be used for security, @use self::uuid4 instead.
     *
     * @param int  $maxlength
     * @param bool $more_entropy
     *
     * @return bool|string
     */
    public static function randomString( int $maxlength = 12, bool $more_entropy = false ): string {

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
     *
     * @return bool
     */
    public static function isJson( $string ): bool {
        json_decode( $string );

        return json_last_error() === JSON_ERROR_NONE;
    }

    public static function mysqlTimestamp( $time ): string {
        $date = date( 'Y-m-d H:i:s', $time );
        if ( !$date ) {
            $date = date( 'Y-m-d H:i:s', time() );
        }

        return $date;
    }

    /**
     * @throws Exception
     */
    public static function api_timestamp( $date_string ): ?string {
        if ( $date_string == null ) {
            return null;
        } else {
            $datetime = new DateTime( $date_string );

            return $datetime->format( 'c' );
        }
    }

    public static function underscoreToCamelCase( $string ): string {
        return str_replace( ' ', '', ucwords( str_replace( '_', ' ', $string ) ) );
    }

    /**
     * @param $string
     *
     * @return string
     */
    public static function trimAndLowerCase( $string ): string {
        return trim( strtolower( $string ) );
    }

    /**
     * Removes the empty elements from the end of an array
     *
     * @param array $array
     *
     * @return array
     */
    public static function removeEmptyStringFromTail( array $array ): array {

        if ( end( $array ) === '' ) {
            array_pop( $array );

            return self::removeEmptyStringFromTail( $array );
        }

        return $array;

    }

    /**
     * @param array $params
     * @param array $required_keys
     *
     * @return array
     * @throws Exception
     */
    public static function ensure_keys( array $params, array $required_keys ): array {
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

    /**
     * @return string|null
     */
    public static function getRealIpAddr(): ?string {

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

        return null;

    }

    /**
     * @throws Exception
     */
    public static function sendErrMailReport( string $htmlContent, string $subject = null ): void {

        if ( !INIT::$SEND_ERR_MAIL_REPORT ) {
            return;
        }

        $mailConf = @parse_ini_file( INIT::$ROOT . '/inc/Error_Mail_List.ini', true );

        if ( empty( $subject ) ) {
            $subject = 'Alert from Matecat: ' . php_uname( 'n' );
        } else {
            $subject .= ' ' . php_uname( 'n' );
        }

        $queue_element              = array_merge( [], $mailConf );
        $queue_element[ 'subject' ] = $subject;
        $queue_element[ 'body' ]    = '<pre>' . self::_getBackTrace() . "<br />" . $htmlContent . '</pre>';

        WorkerClient::enqueue( 'MAIL', ErrMailWorker::class, $queue_element, [ 'persistent' => WorkerClient::$_HANDLER->persistent ] );

        Log::doJsonLog( 'Message has been sent' );

    }

    protected static function _getBackTrace(): string {

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

        // Output the 36-character UUID.
        return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );

    }

    /**
     * @param string|null $token
     *
     * @return bool
     */
    public static function isTokenValid( ?string $token = null ): bool {
        if ( empty( $token ) || !preg_match( '|^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$|', $token ) ) {
            return false;
        }

        return true;
    }


    /**
     * Fixes the file name by sanitizing the given string and ensuring uniqueness in the specified directory.
     *
     * @param string      $stringName The original file name to fix.
     * @param string|null $directory  Optional. The directory where the file is located. Default is null.
     * @param bool        $upCount    Optional. Whether to increment the count number if the file name already exists. Default is true.
     *
     * @return string The fixed file name.
     */
    public static function fixFileName( string $stringName, string $directory = null, bool $upCount = true ): string {
        $string = filter_var( $stringName, FILTER_SANITIZE_STRING, [ 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_NO_ENCODE_QUOTES ] );
        while ( is_file( $directory . DIRECTORY_SEPARATOR . $string ) && $upCount ) {
            $string = static::upCountName( $string );
        }

        return $string;
    }

    /**
     * Callback function used to update the count name in the given name by replacing it with the incremented count number.
     *
     * @param array $matches The matches array containing the count number and extension.
     *                       The count number is stored in index 1 and the extension is stored in index 2.
     *
     * @return string The updated name with the incremented count number.
     */
    protected function upCountNameCallback( array $matches ): string {
        $index = isset( $matches[ 1 ] ) ? intval( $matches[ 1 ] ) + 1 : 1;
        $ext   = $matches[ 2 ] ?? '';

        return '_(' . $index . ')' . $ext;
    }

    /**
     * Updates the count name in the given name by replacing it with the incremented count number.
     *
     * @param string $name The name to update the count in.
     *
     * @return string The updated name with the incremented count number.
     */
    protected static function upCountName( string $name ): string {
        return preg_replace_callback(
                '/(?:(?:_\((\d+)\))?(\.[^.]+))?$/', [ '\Utils', 'upCountNameCallback' ], $name, 1
        );
    }


    /**
     * Check if a file name is valid to prevent directory traversal attacks.
     *
     * @param string $fileUpName The file name to check.
     *
     * @return bool Returns true if the file name is valid, false otherwise.
     */
    public static function isValidFileName( string $fileUpName ): bool {

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
     * @param $dirPath
     */
    public static function deleteDir( $dirPath ) {

        if ( is_dir( $dirPath ) ) {
            $iterator = new DirectoryIterator( $dirPath );

            foreach ( $iterator as $fileInfo ) {
                if ( $fileInfo->isDot() ) {
                    continue;
                }
                if ( $fileInfo->isDir() ) {
                    self::deleteDir( $fileInfo->getPathname() );
                } else {
                    $fileName = $fileInfo->getFilename();
                    if ( $fileName[ 0 ] == '.' ) {
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
     * @return void|string
     * @throws Exception
     */
    public static function raiseJsonExceptionError( bool $raise = true ) {

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

    // Previously in FileFormatConverter
    //remove UTF-8 BOM
    public static function stripFileBOM( $string, $utf = 8 ): string {
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

    /**
     * uploadDirFromSessionCookie
     *
     * @oaram $guid string
     * @param $guid
     * @param $file_name string|null optional file name to append to the upload path
     *
     * @return string
     */
    public static function uploadDirFromSessionCookie( $guid, string $file_name = null ): string {
        return INIT::$UPLOAD_REPOSITORY . "/" .
                $guid . '/' .
                $file_name;
    }

    /**
     * @param array    $match
     * @param string   $job_tm_keys
     * @param int|null $uid
     *
     * @return null|string
     * @throws Exception
     */
    public static function changeMemorySuggestionSource( array $match, string $job_tm_keys, ?int $uid = null ): string {
        $sug_source = $match[ 'created_by' ];
        $key        = $match[ 'memory_key' ];

        if ( strtolower( $sug_source ) == 'matecat' ) {
            // Enter this case if created_by is matecat, we show PUBLIC_TM
            $description = Constants::PUBLIC_TM;

        } elseif ( !empty( $sug_source ) && stripos( $sug_source, "MyMemory" ) === false ) {
            // This case if for other sources from MyMemory that are public, but we must
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

    /**
     * @throws Exception
     */
    public static function keyNameFromUserKeyring( $uid, $key ): ?string {
        if ( $uid === null ) {
            return null;
        }

        //check if the user can see the key.
        $memoryKey              = new MemoryKeyStruct();
        $memoryKey->uid         = $uid;
        $memoryKey->tm_key      = new TmKeyStruct();
        $memoryKey->tm_key->key = $key;

        $memoryKeyDao         = new MemoryKeyDao( Database::obtain() );
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
     * @param string $key
     * @param string $job_tm_keys
     *
     * @return null|string
     * @throws Exception
     */
    public static function getDefaultKeyDescription( string $key, string $job_tm_keys ): string {
        $ownerKeys   = TmKeyManager::getOwnerKeys( [ $job_tm_keys ] );
        $description = Constants::NO_DESCRIPTION_TM;

        //search the current key
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
     * @param string $stringA
     * @param string $stringB
     *
     * @return bool
     */
    public static function stringsAreEqual( string $stringA, string $stringB ): bool {
        return $stringA == $stringB;
    }

    /**
     * shortcut to htmlentities (UTF-8 charset)
     * avoiding double-encoding
     *
     * @param $string
     *
     * @return string
     */
    public static function htmlentitiesToUft8WithoutDoubleEncoding( $string ): string {
        return htmlentities( $string, ENT_QUOTES, 'UTF-8', false );
    }

    /**
     * @param string $phrase
     * @param int    $max_words
     *
     * @return string
     */
    public static function truncatePhrase( string $phrase, int $max_words ): string {

        $phrase_array = explode( ' ', $phrase );
        if ( count( $phrase_array ) > $max_words && $max_words > 0 ) {
            $phrase = implode( ' ', array_slice( $phrase_array, 0, $max_words ) );
        }

        return $phrase;
    }

    /**
     * This function strips html tag, but preserves hrefs.
     *
     * Example:
     *
     * This is the link: <a href="https://matecat.com">click here</a> -----> This is the link: click here(https://matecat.com)
     *
     * @param string $html
     *
     * @return string
     */
    public static function stripTagsPreservingHrefs( string $html ): string {
        $htmlDom               = new DOMDocument( '1.0', 'UTF-8' );
        $htmlDom->formatOutput = false;

        @$htmlDom->loadHTML( $html );

        $links = $htmlDom->getElementsByTagName( 'a' );
        $images = $htmlDom->getElementsByTagName( 'img' );

        // replace <a> with a label(href)
        /** @var DOMElement $link */
        foreach ( $links as $link ) {
            $linkLabel       = $link->nodeValue;
            $linkHref        = $link->getAttribute( 'href' );
            $link->nodeValue = $linkLabel . "(" . str_replace( "\\\"", "", $linkHref ) . ")";
        }

        // replace <img> with src
        $i = $images->length - 1;
        while ($i > -1) {
            $image = $images->item($i);
            $src = $image->getAttribute( 'src' );
            $newElement = $htmlDom->createTextNode($src);
            $image->parentNode->replaceChild($newElement, $image);
            $i--;
        }

        $html = $htmlDom->saveHtml( $htmlDom->documentElement );
        $html = utf8_decode( $html );

        $strippedHtml = strip_tags( $html );
        $strippedHtml = ltrim( $strippedHtml );

        return rtrim( $strippedHtml );
    }

    /**
     * @param $list
     *
     * @return array
     */
    public static function validateEmailList( $list ): array {

        $aValid = [];
        foreach ( explode( ',', $list ) as $sEmailAddress ) {
            $sEmailAddress = trim( $sEmailAddress );
            if ( empty( $sEmailAddress ) ) {
                continue;
            }
            $aValid[ $sEmailAddress ] = filter_var( $sEmailAddress, FILTER_VALIDATE_EMAIL );
        }

        $invalidEmails = array_keys( $aValid, false );

        if ( !empty( $invalidEmails ) ) {
            throw new InvalidArgumentException( "Not valid e-mail provided: " . implode( ", ", $invalidEmails ), -6 );
        }

        return array_keys( $aValid );
    }

    /**
     * @param array $arr
     * @return bool
     */
    public static function arrayIsList(array $arr): bool {
        if ($arr === []) {
            return true;
        }

        return array_keys($arr) === range(0, count($arr) - 1);
    }

    /**
     * @param $nameString
     * @return bool|string
     */
    public static function sanitizeName( $nameString ) {

        $nameString = preg_replace( '/[^\p{L}0-9a-zA-Z_.\-]/u', "_", $nameString );
        $nameString = preg_replace( '/_{2,}/', "_", $nameString );
        $nameString = str_replace( '_.', ".", $nameString );

        // project name validation
        $pattern = '/^[\p{L}\s0-9a-zA-Z_.\-]+$/u';

        if ( !preg_match( $pattern, $nameString ) ) {
            return false;
        }

        return $nameString;

    }
}
