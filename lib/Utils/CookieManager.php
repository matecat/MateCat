<?php

/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 19/04/22
 * Time: 18:41
 *
 */
class CookieManager {

    /**
     * @param        $name
     * @param string $value
     * @param array  $options
     *
     * @return bool
     */
    public static function setCookie( $name, $value = "", array $options = [] ) {

        if ( version_compare( PHP_VERSION, '7.3.0' ) >= 0 ) {
            return setcookie( $name, $value, $options );
        } else {
            return setcookie(
                    $name,
                    $value,
                    $options[ 'expires' ],
                    $options[ 'path' ] . "; samesite=" . $options[ 'samesite' ],
                    $options[ 'domain' ],
                    $options[ 'secure' ],
                    $options[ 'httponly' ]
            );
        }

    }

}