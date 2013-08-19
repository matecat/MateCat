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
        curl_setopt($ch, CURLOPT_USERAGENT, "Matecat-Cattool/v" . INIT::$BUILD_NUMBER);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $d);
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
            if ( array_key_exists($key, $_SERVER ) === true) {
                foreach ( explode(',', $_SERVER[$key]) as $ip ) {
                    if ( filter_var( trim($ip), FILTER_VALIDATE_IP ) !== false) {
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

    // get the char code from a multibyte char
    public static function unicode2ord($c) {
        if (ord($c{0}) >= 0 && ord($c{0}) <= 127)
            return ord($c{0});
        if (ord($c{0}) >= 192 && ord($c{0}) <= 223)
            return (ord($c{0}) - 192) * 64 + (ord($c{1}) - 128);
        if (ord($c{0}) >= 224 && ord($c{0}) <= 239)
            return (ord($c{0}) - 224) * 4096 + (ord($c{1}) - 128) * 64 + (ord($c{2}) - 128);
        if (ord($c{0}) >= 240 && ord($c{0}) <= 247)
            return (ord($c{0}) - 240) * 262144 + (ord($c{1}) - 128) * 4096 + (ord($c{2}) - 128) * 64 + (ord($c{3}) - 128);
        if (ord($c{0}) >= 248 && ord($c{0}) <= 251)
            return (ord($c{0}) - 248) * 16777216 + (ord($c{1}) - 128) * 262144 + (ord($c{2}) - 128) * 4096 + (ord($c{3}) - 128) * 64 + (ord($c{4}) - 128);
        if (ord($c{0}) >= 252 && ord($c{0}) <= 253)
            return (ord($c{0}) - 252) * 1073741824 + (ord($c{1}) - 128) * 16777216 + (ord($c{2}) - 128) * 262144 + (ord($c{3}) - 128) * 4096 + (ord($c{4}) - 128) * 64 + (ord($c{5}) - 128);
        if (ord($c{0}) >= 254 && ord($c{0}) <= 255)    //  error
            return FALSE;
        return 0;
    }
  
//  function _uniord()
}

?>
