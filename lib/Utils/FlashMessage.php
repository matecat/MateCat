<?php

class FlashMessage {

    const KEY = 'flashMessages' ;

    const WARNING = 'warning' ;
    const ERROR = 'error' ;
    const INFO = 'info' ;

    public static function set( $key, $message, $type = self::WARNING ) {
        Bootstrap::sessionStart();

        if ( isset( $_SESSION[ self::KEY ] ) ) {
            $_SESSION[ self::KEY ] = array(
                self::WARNING => array(),
                self::ERROR => array(),
                self::INFO => array()
            );
        }

        $_SESSION[ self::KEY ] [ $type ] [] = array(
            'key' => $key,
            'message' => $message
        );
    }

    public static function flush() {
        $out = $_SESSION[ self::KEY ] ;
        unset( $_SESSION[ self::KEY ] );
        return $out ;
    }

}