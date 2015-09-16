<?php
class EnvWrap {

    public static $HEADERS = array();
    public static $PRODUCTION = 'production';
    public static $DEVELOPMENT = 'development' ;
    public static $TEST = 'test' ;

    public static function link( $source, $dest ) {
        if ( self::isProduction()) {
            return link($source, $dest) ;
        }
        else {
            return copy($source, $dest) ;
        }
    }

    public static function isProduction() {
        return INIT::$ENV == self::$PRODUCTION ;
    }

    public static function isTest() {
        return INIT::$ENV == self::$TEST ;
    }

    public static function header($string) {
        if ( self::isTest() ) {
            array_push( self::$HEADERS, $string);
        }
        else {
            header($string);
        }
    }
}
