<?php

class FlashMessage {

    const KEY = 'flashMessages';

    const WARNING = 'warning';
    const ERROR   = 'error';
    const INFO    = 'info';
    const SERVICE = 'service';

    public static function set( $key, $value, $type = self::WARNING ) {
        Bootstrap::sessionStart();

        if ( !isset( $_SESSION[ self::KEY ] ) ) {
            $_SESSION[ self::KEY ] = [
                    self::WARNING => [],
                    self::ERROR   => [],
                    self::INFO    => []
            ];
        }

        $_SESSION[ self::KEY ] [ $type ] [] = [
                'key'   => $key,
                'value' => $value
        ];
    }

    public static function flush() {
        $out = null;
        if ( isset( $_SESSION[ self::KEY ] ) ) {
            $out = $_SESSION[ self::KEY ];
            unset( $_SESSION[ self::KEY ] );
        }

        return $out;
    }

}